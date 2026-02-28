<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class OrderPaymentService
{
    public function __construct(private readonly SnippePay $snippePay)
    {
    }

    public function startClientPayment(Order $order, User $client, string $channel, ?string $phoneNumber = null): array
    {
        $channel = strtolower(trim($channel));
        if (!in_array($channel, ['mobile', 'card'], true)) {
            throw new RuntimeException('Chagua channel ya malipo: mobile au card.');
        }

        $order = Order::query()->findOrFail((int) $order->id);
        if ((int) $order->client_id !== (int) $client->id) {
            throw new RuntimeException('Huruhusiwi kufanya malipo kwenye oda hii.');
        }

        if (in_array((string) ($order->status ?? ''), ['completed', 'cancelled'], true)) {
            throw new RuntimeException('Huwezi kuanzisha malipo kwa oda iliyofungwa.');
        }

        $settledStatuses = ['held', 'released', 'refunded'];
        if (in_array((string) ($order->payment_status ?? ''), $settledStatuses, true)) {
            throw new RuntimeException('Malipo tayari yamekamilika kwenye oda hii.');
        }

        $amount = (int) round((float) ($order->price_total ?? 0));
        if ($amount <= 0) {
            throw new RuntimeException('Kiasi cha kulipa si sahihi.');
        }

        $name = trim((string) ($client->name ?? 'Client'));
        if ($name === '') {
            $name = 'Client';
        }
        [$firstName, $lastName] = $this->splitName($name);
        $email = trim((string) ($client->email ?? ''));
        if ($email === '') {
            $email = 'client' . (int) $client->id . '@getglamo.com';
        }

        $reference = '';
        $paymentUrl = '';
        $gatewayStatus = '';

        if ($channel === 'mobile') {
            $msisdn = Phone::normalizeTzMsisdn((string) ($phoneNumber ?? $client->phone ?? ''));
            if ($msisdn === null) {
                throw new RuntimeException('Namba ya simu ya kulipia si sahihi.');
            }

            $payload = [
                'payment_type' => 'mobile',
                'details' => [
                    'amount' => $amount,
                    'currency' => 'TZS',
                ],
                'phone_number' => $msisdn,
                'customer' => [
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'email' => $email,
                ],
                'external_reference' => (string) ($order->order_no ?? ''),
                'metadata' => [
                    'order_id' => (string) (int) $order->id,
                    'order_no' => (string) ($order->order_no ?? ''),
                    'client_id' => (string) (int) $order->client_id,
                    'provider_id' => (string) (int) $order->provider_id,
                    'channel' => 'mobile',
                ],
                'webhook_url' => $this->snippePay->webhookUrl(),
            ];

            $response = $this->snippePay->createPayment($payload, 'order-mobile-' . (int) $order->id . '-' . time());
            $reference = $this->extractReference($response);
            $paymentUrl = $this->extractPaymentUrl($response);
            $gatewayStatus = $this->extractGatewayStatus($response);
        } else {
            $sessionPayload = [
                'amount' => $amount,
                'currency' => 'TZS',
                'external_reference' => (string) ($order->order_no ?? ''),
                'success_redirect_url' => route('snippe.done'),
                'cancel_redirect_url' => route('snippe.cancel'),
                'allowed_methods' => ['card'],
                'metadata' => [
                    'order_id' => (string) (int) $order->id,
                    'order_no' => (string) ($order->order_no ?? ''),
                    'client_id' => (string) (int) $order->client_id,
                    'provider_id' => (string) (int) $order->provider_id,
                    'channel' => 'card',
                    'customer_email' => $email,
                ],
            ];

            $response = $this->snippePay->createPaymentSession($sessionPayload, 'order-card-' . (int) $order->id . '-' . time());
            $reference = $this->extractSessionReference($response);
            $paymentUrl = $this->extractPaymentUrl($response);
            $gatewayStatus = $this->extractGatewayStatus($response);
        }

        if ($reference === '') {
            throw new RuntimeException('Imeshindikana kupata payment reference kutoka gateway.');
        }

        $mappedStatus = $this->mapGatewayStatus($gatewayStatus);

        DB::transaction(function () use ($order, $channel, $reference, $mappedStatus): void {
            $locked = Order::query()->whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) ($locked->status ?? ''), ['completed', 'cancelled'], true)) {
                throw new RuntimeException('Oda hii imefungwa tayari.');
            }

            if (in_array((string) ($locked->payment_status ?? ''), ['held', 'released', 'refunded'], true)) {
                throw new RuntimeException('Malipo tayari yamekamilika kwenye oda hii.');
            }

            $updates = [
                'payment_method' => 'prepay',
                'payment_channel' => $channel,
                'payment_status' => $mappedStatus,
            ];

            if (Schema::hasColumn('orders', 'payment_provider')) {
                $updates['payment_provider'] = 'snippe';
            }
            if (Schema::hasColumn('orders', 'payment_reference')) {
                $updates['payment_reference'] = $reference;
            }

            $locked->update($updates);
        });

        return [
            'channel' => $channel,
            'reference' => $reference,
            'payment_url' => $paymentUrl !== '' ? $paymentUrl : null,
            'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : null,
            'order_payment_status' => $mappedStatus,
        ];
    }

    public function refreshClientPayment(Order $order, User $client): array
    {
        $order = Order::query()->findOrFail((int) $order->id);
        if ((int) $order->client_id !== (int) $client->id) {
            throw new RuntimeException('Huruhusiwi kufanya tendo hili.');
        }

        $channel = strtolower(trim((string) ($order->payment_channel ?? '')));
        if ($channel === '') {
            throw new RuntimeException('Payment channel haijawekwa.');
        }

        $reference = trim((string) ($order->payment_reference ?? ''));
        if ($reference === '') {
            throw new RuntimeException('Payment reference haipo. Anzisha malipo kwanza.');
        }

        if (in_array((string) ($order->payment_status ?? ''), ['released', 'refunded'], true)) {
            return [
                'gateway_status' => (string) ($order->payment_status ?? ''),
                'order_payment_status' => (string) ($order->payment_status ?? ''),
                'reference' => $reference,
                'payment_url' => null,
            ];
        }

        $gateway = $channel === 'card'
            ? $this->snippePay->getPaymentSession($reference)
            : $this->snippePay->getPayment($reference);

        $gatewayStatus = $this->extractGatewayStatus($gateway);
        $paymentUrl = $this->extractPaymentUrl($gateway);
        $mappedStatus = $this->mapGatewayStatus($gatewayStatus);

        DB::transaction(function () use ($order, $mappedStatus): void {
            $locked = Order::query()->whereKey((int) $order->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) ($locked->payment_status ?? ''), ['released', 'refunded'], true)) {
                return;
            }

            if (in_array((string) ($locked->status ?? ''), ['completed', 'cancelled'], true) && $mappedStatus !== 'held') {
                return;
            }

            $locked->update([
                'payment_status' => $mappedStatus,
            ]);
        });

        return [
            'gateway_status' => $gatewayStatus !== '' ? $gatewayStatus : null,
            'order_payment_status' => $mappedStatus,
            'reference' => $reference,
            'payment_url' => $paymentUrl !== '' ? $paymentUrl : null,
        ];
    }

    private function extractReference(array $response): string
    {
        return trim((string) (
            data_get($response, 'data.reference')
            ?: data_get($response, 'data.payment_reference')
            ?: data_get($response, 'reference')
        ));
    }

    private function extractSessionReference(array $response): string
    {
        return trim((string) (
            data_get($response, 'data.session_reference')
            ?: data_get($response, 'data.reference')
            ?: data_get($response, 'session_reference')
            ?: data_get($response, 'reference')
        ));
    }

    private function extractPaymentUrl(array $response): string
    {
        return trim((string) (
            data_get($response, 'data.payment_url')
            ?: data_get($response, 'data.checkout_url')
            ?: data_get($response, 'data.url')
            ?: data_get($response, 'payment_url')
            ?: data_get($response, 'checkout_url')
            ?: data_get($response, 'url')
        ));
    }

    private function extractGatewayStatus(array $response): string
    {
        return strtolower(trim((string) (
            data_get($response, 'data.status')
            ?: data_get($response, 'data.payment_status')
            ?: data_get($response, 'status')
            ?: data_get($response, 'payment_status')
        )));
    }

    private function mapGatewayStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid', 'successful', 'succeeded', 'complete', 'completed' => 'held',
            'failed', 'cancelled', 'canceled', 'expired', 'rejected' => 'failed',
            default => 'pending',
        };
    }

    private function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
        if ($name === '') {
            return ['Client', 'Glamo'];
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = (string) ($parts[0] ?? 'Client');
        $last = (string) ($parts[count($parts) - 1] ?? 'Glamo');
        if ($last === '') {
            $last = 'Glamo';
        }

        return [$first, $last];
    }
}
