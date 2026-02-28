<?php

namespace App\Services;

use App\Mail\OrderCreatedMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotifier
{
    public function __construct(
        private readonly BeemSms $beemSms,
        private readonly AppNotificationService $appNotifications,
    )
    {
    }

    public function notifyCreated(Order $order): void
    {
        $order->loadMissing(['client', 'provider.user', 'service']);

        $this->notifyClient($order);
        $this->notifyProvider($order);
        $this->notifyInAppAndPush($order);
    }

    private function notifyClient(Order $order): void
    {
        $clientEmail = trim((string) data_get($order, 'client.email'));
        if ($clientEmail !== '') {
            try {
                Mail::to($clientEmail)->send(new OrderCreatedMail($order, 'client'));
            } catch (\Throwable $e) {
                Log::warning('Order notify client email failed', [
                    'order_id' => (int) $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $clientPhone = trim((string) data_get($order, 'client.phone'));
        if ($clientPhone === '') {
            return;
        }

        $providerName = $this->firstName((string) data_get($order, 'provider.display_name'));
        $sms = $this->limitSms(
            'Glamo: Oda ' . (string) ($order->order_no ?? '') . ' imepokelewa. '
            . $providerName . ' atakupigia muda si mrefu. '
            . 'Jumla TZS ' . number_format((float) ($order->price_total ?? 0), 0) . '.'
        );

        $this->beemSms->sendMessage($clientPhone, $sms, (int) data_get($order, 'client.id'));
    }

    private function notifyProvider(Order $order): void
    {
        $providerEmail = trim((string) data_get($order, 'provider.user.email'));
        if ($providerEmail !== '') {
            try {
                Mail::to($providerEmail)->send(new OrderCreatedMail($order, 'provider'));
            } catch (\Throwable $e) {
                Log::warning('Order notify provider email failed', [
                    'order_id' => (int) $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $providerPhone = trim((string) (data_get($order, 'provider.user.phone') ?? data_get($order, 'provider.phone_public')));
        if ($providerPhone === '') {
            return;
        }

        $clientName = $this->firstName((string) data_get($order, 'client.name'));
        $serviceName = trim((string) data_get($order, 'service.name'));

        $sms = $this->limitSms(
            'Glamo: Oda mpya ' . (string) ($order->order_no ?? '')
            . ' kutoka ' . $clientName
            . ($serviceName !== '' ? ' (' . $serviceName . ')' : '')
            . '. Fungua dashboard/app sasa.'
        );

        $this->beemSms->sendMessage($providerPhone, $sms, (int) data_get($order, 'provider.id'));
    }

    private function firstName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'Mtoa huduma';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
        return (string) ($parts[0] ?? $name);
    }

    private function limitSms(string $message, int $max = 150): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $message) ?? '');
        if ($text === '') {
            return '';
        }

        if (strlen($text) <= $max) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $max - 3))) . '...';
    }

    private function notifyInAppAndPush(Order $order): void
    {
        $orderNo = (string) ($order->order_no ?? '#' . $order->id);
        $serviceName = trim((string) data_get($order, 'service.name', ''));

        $clientId = (int) data_get($order, 'client.id', $order->client_id);
        if ($clientId > 0) {
            $this->appNotifications->sendToUsers(
                [$clientId],
                'order_created_client',
                'Oda yako imepokelewa',
                'Oda ' . $orderNo . ' imepokelewa na inashughulikiwa.',
                [
                    'order_id' => (string) (int) $order->id,
                    'order_no' => $orderNo,
                    'service' => $serviceName,
                    'target_screen' => 'order_details',
                ],
                true
            );
        }

        $providerUserId = (int) data_get($order, 'provider.user_id', 0);
        if ($providerUserId > 0) {
            $this->appNotifications->sendToUsers(
                [$providerUserId],
                'order_created_provider',
                'Una oda mpya',
                'Oda ' . $orderNo . ' imeingia. Fungua app uithibitishe.',
                [
                    'order_id' => (string) (int) $order->id,
                    'order_no' => $orderNo,
                    'service' => $serviceName,
                    'target_screen' => 'provider_order_details',
                ],
                true
            );
        }
    }
}
