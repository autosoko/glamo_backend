<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\ProviderWalletLedger;
use App\Models\ProviderWithdrawal;
use App\Services\BeemSms;
use App\Services\OrderNotifier;
use App\Services\OrderService;
use App\Services\ProviderDebtService;
use App\Support\CheckoutPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SnippeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $rawBody = (string) $request->getContent();

        $eventHeader = (string) $request->header('X-Webhook-Event', '');
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');
        $signature = (string) $request->header('X-Webhook-Signature', '');

        if (!$this->verifySignature($rawBody, $timestamp, $signature)) {
            Log::warning('Snippe webhook rejected (signature)', [
                'event' => $eventHeader,
            ]);
            return response()->json(['ok' => false], 401);
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Log::warning('Snippe webhook rejected (invalid JSON)');
            return response()->json(['ok' => false], 400);
        }

        $type = trim($eventHeader !== '' ? $eventHeader : (string) (data_get($payload, 'type') ?? ''));

        try {
            return match ($type) {
                'payment.completed' => $this->handlePaymentCompleted($payload),
                'payment.failed' => $this->handlePaymentFailed($payload),
                'payout.completed' => $this->handlePayoutCompleted($payload),
                'payout.failed' => $this->handlePayoutFailed($payload),
                default => response()->json(['ok' => true]),
            };
        } catch (\Throwable $e) {
            Log::error('Snippe webhook handler failed', [
                'type' => $type,
                'message' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false], 500);
        }
    }

    private function verifySignature(string $rawBody, string $timestamp, string $signature): bool
    {
        $secret = trim((string) config('snippe.webhook_secret', ''));
        if ($secret === '') {
            // Allow local/dev environments to receive webhooks without verification.
            return true;
        }

        $timestampInt = (int) $timestamp;
        if ($timestamp === '' || $timestampInt <= 0) {
            return false;
        }

        $tolerance = (int) config('snippe.webhook_tolerance', 300);
        if ($tolerance > 0) {
            $age = abs(time() - $timestampInt);
            if ($age > $tolerance) {
                return false;
            }
        }

        $sig = trim($signature);
        if ($sig === '') {
            return false;
        }

        // Common prefixes (just in case)
        $sig = preg_replace('/^sha256=/i', '', $sig);
        $sig = trim((string) $sig);

        // Try common signing formats to match Snippe docs/examples.
        $signed1 = $timestamp . '.' . $rawBody;
        $hex1 = hash_hmac('sha256', $signed1, $secret);
        $hex2 = hash_hmac('sha256', $rawBody, $secret);
        $b641 = base64_encode(hash_hmac('sha256', $signed1, $secret, true));
        $b642 = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        $candidates = [$hex1, $hex2, $b641, $b642];

        foreach ($candidates as $cand) {
            if (hash_equals($cand, $sig)) {
                return true;
            }

            // Allow hex case differences (docs show hex-like values)
            if (
                preg_match('/^[A-Fa-f0-9]{64}$/', $cand)
                && preg_match('/^[A-Fa-f0-9]{64}$/', $sig)
                && hash_equals(strtolower($cand), strtolower($sig))
            ) {
                return true;
            }
        }

        return false;
    }

    private function handlePaymentCompleted(array $payload)
    {
        $reference = (string) (data_get($payload, 'data.reference') ?? '');
        $externalReference = (string) (data_get($payload, 'data.external_reference') ?? '');

        $providerPayment = $this->findProviderPaymentByRefs($reference, $externalReference);
        if ($providerPayment) {
            app(ProviderDebtService::class)->markPaymentAsPaid($providerPayment);
            return response()->json(['ok' => true]);
        }

        $pendingOrder = $this->createOrderFromPendingCheckoutPayment($payload, $reference, $externalReference);
        if ($pendingOrder) {
            return response()->json(['ok' => true]);
        }

        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'payment_status')) {
            return response()->json(['ok' => true]);
        }

        $order = $this->findOrderByPaymentRefs($reference, $externalReference, $payload);
        if (!$order) {
            Log::warning('Snippe payment.completed: order/provider payment not found', [
                'reference' => $reference,
                'external_reference' => $externalReference,
            ]);
            return response()->json(['ok' => true]);
        }

        $notifyPaymentReceived = false;

        DB::transaction(function () use ($order, $reference, &$notifyPaymentReceived) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((string) ($locked->payment_method ?? '') !== 'prepay') {
                return;
            }

             $wasHeld = (string) ($locked->payment_status ?? '') === 'held';

            $updates = [
                'payment_status' => 'held',
            ];

            if (Schema::hasColumn('orders', 'payment_provider')) {
                $updates['payment_provider'] = 'snippe';
            }
            if (Schema::hasColumn('orders', 'payment_reference') && $reference !== '') {
                $updates['payment_reference'] = $reference;
            }

            $locked->update($updates);
            $notifyPaymentReceived = !$wasHeld;
        });

        if ($notifyPaymentReceived) {
            $fresh = Order::query()->with(['client:id,name,phone', 'provider.user:id,name,phone', 'provider:id,user_id,phone_public', 'service:id,name'])->find((int) $order->id);
            if ($fresh) {
                $this->sendClientPaymentReceiptSms($fresh);
                $this->sendProviderPaymentReceivedSms($fresh);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function createOrderFromPendingCheckoutPayment(array $payload, string $reference, string $externalReference): ?Order
    {
        if (!Schema::hasTable('orders')) {
            return null;
        }

        $token = $this->resolveCheckoutToken($payload, $reference, $externalReference);
        if ($token === '') {
            return null;
        }

        $pending = Cache::get(CheckoutPayment::pendingKey($token));
        if (!is_array($pending)) {
            return null;
        }

        $existingOrderId = (int) Cache::get(CheckoutPayment::resultKey($token), 0);
        if ($existingOrderId > 0) {
            $existing = Order::query()->find($existingOrderId);
            if ($existing) {
                return $existing;
            }
        }

        $createdOrder = null;

        DB::transaction(function () use (&$createdOrder, $pending, $reference) {
            if ($reference !== '' && Schema::hasColumn('orders', 'payment_reference')) {
                $existing = Order::query()
                    ->where('payment_reference', $reference)
                    ->latest()
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    $createdOrder = $existing;
                    return;
                }
            }

            $clientId = (int) ($pending['client_id'] ?? 0);
            $providerId = (int) ($pending['provider_id'] ?? 0);
            $serviceId = (int) ($pending['service_id'] ?? 0);

            if ($clientId <= 0 || $providerId <= 0 || $serviceId <= 0) {
                Log::warning('Pending checkout payment payload invalid', [
                    'client_id' => $clientId,
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                ]);
                return;
            }

            $couponId = (int) ($pending['coupon_id'] ?? 0);
            if ($couponId > 0) {
                $coupon = Coupon::query()->whereKey($couponId)->lockForUpdate()->first();
                if ($coupon) {
                    $coupon->increment('used_count');
                }
            }

            $createdOrder = app(OrderService::class)->createOrder([
                'client_id' => $clientId,
                'provider_id' => $providerId,
                'service_id' => $serviceId,
                'client_lat' => (float) ($pending['client_lat'] ?? 0),
                'client_lng' => (float) ($pending['client_lng'] ?? 0),
                'address_text' => trim((string) ($pending['address_text'] ?? '')) ?: null,
                'price_subtotal' => (float) ($pending['price_subtotal'] ?? 0),
                'discount_amount' => (float) ($pending['discount_amount'] ?? 0),
                'coupon_id' => $couponId > 0 ? $couponId : null,
                'coupon_code' => trim((string) ($pending['coupon_code'] ?? '')) ?: null,
                'payment_method' => 'prepay',
                'payment_channel' => (string) ($pending['payment_channel'] ?? 'mobile'),
                'payment_status' => 'held',
                'payment_provider' => 'snippe',
                'payment_reference' => trim((string) ($reference ?: ($pending['payment_reference'] ?? ''))),
                'price_total' => (float) ($pending['price_total'] ?? 0),
                'price_service' => (float) ($pending['price_service'] ?? 0),
                'price_materials' => (float) ($pending['price_materials'] ?? 0),
                'price_travel' => (float) ($pending['price_travel'] ?? 0),
                'price_usage' => (float) ($pending['price_usage'] ?? 0),
                'travel_distance_km' => (float) ($pending['travel_distance_km'] ?? 0),
                'items' => (array) ($pending['items'] ?? []),
            ]);
        });

        if (!$createdOrder) {
            return null;
        }

        Cache::put(CheckoutPayment::resultKey($token), (int) $createdOrder->id, now()->addDay());
        Cache::forget(CheckoutPayment::pendingKey($token));
        if ($reference !== '') {
            Cache::forget(CheckoutPayment::referenceKey($reference));
        }

        try {
            app(OrderNotifier::class)->notifyCreated($createdOrder);
        } catch (\Throwable $e) {
            Log::warning('Pending checkout order notifier failed', [
                'order_id' => (int) $createdOrder->id,
                'error' => $e->getMessage(),
            ]);
        }

        $fresh = Order::query()->with([
            'client:id,name,phone',
            'provider.user:id,name,phone',
            'provider:id,user_id,phone_public',
            'service:id,name',
        ])->find((int) $createdOrder->id);

        if ($fresh) {
            $this->sendClientPaymentReceiptSms($fresh);
            $this->sendProviderPaymentReceivedSms($fresh);
            return $fresh;
        }

        return $createdOrder;
    }

    private function handlePaymentFailed(array $payload)
    {
        $reference = (string) (data_get($payload, 'data.reference') ?? '');
        $externalReference = (string) (data_get($payload, 'data.external_reference') ?? '');

        $providerPayment = $this->findProviderPaymentByRefs($reference, $externalReference);
        if ($providerPayment) {
            DB::transaction(function () use ($providerPayment, $reference) {
                $locked = ProviderPayment::whereKey((int) $providerPayment->id)->lockForUpdate()->firstOrFail();
                if ((string) ($locked->status ?? '') === 'paid') {
                    return;
                }

                $updates = ['status' => 'failed'];
                if ($reference !== '') {
                    $updates['reference'] = $reference;
                }

                $locked->update($updates);
            });

            return response()->json(['ok' => true]);
        }

        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'payment_status')) {
            return response()->json(['ok' => true]);
        }

        $order = $this->findOrderByPaymentRefs($reference, $externalReference, $payload);
        if (!$order) {
            Log::warning('Snippe payment.failed: order/provider payment not found', [
                'reference' => $reference,
                'external_reference' => $externalReference,
            ]);
            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($order, $reference) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ((string) ($locked->payment_method ?? '') !== 'prepay') {
                return;
            }

            $updates = [
                'payment_status' => 'failed',
            ];

            if (Schema::hasColumn('orders', 'payment_provider')) {
                $updates['payment_provider'] = 'snippe';
            }
            if (Schema::hasColumn('orders', 'payment_reference') && $reference !== '') {
                $updates['payment_reference'] = $reference;
            }

            $locked->update($updates);
        });

        return response()->json(['ok' => true]);
    }

    private function handlePayoutCompleted(array $payload)
    {
        $reference = (string) (data_get($payload, 'data.reference') ?? '');
        if ($reference === '') {
            return response()->json(['ok' => true]);
        }

        DB::transaction(function () use ($reference) {
            if (Schema::hasTable('provider_withdrawals')) {
                /** @var ProviderWithdrawal|null $withdrawal */
                $withdrawal = ProviderWithdrawal::where('reference', $reference)->lockForUpdate()->first();
                if ($withdrawal && !in_array((string) $withdrawal->status, ['paid', 'rejected'], true)) {
                    $withdrawal->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'rejected_reason' => null,
                    ]);
                }
            }

            // Refund payouts (client refund) - mark order payment status as refunded.
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'refund_reference') && Schema::hasColumn('orders', 'payment_status')) {
                $order = Order::where('refund_reference', $reference)->lockForUpdate()->first();
                if ($order && (string) ($order->payment_status ?? '') === 'refund_pending') {
                    $order->update([
                        'payment_status' => 'refunded',
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function handlePayoutFailed(array $payload)
    {
        $reference = (string) (data_get($payload, 'data.reference') ?? '');
        if ($reference === '') {
            return response()->json(['ok' => true]);
        }

        $reason = (string) (data_get($payload, 'data.message') ?? data_get($payload, 'data.reason') ?? '');
        $reason = $reason !== '' ? $reason : 'Payout failed';

        DB::transaction(function () use ($reference, $reason) {
            if (Schema::hasTable('provider_withdrawals')) {
                /** @var ProviderWithdrawal|null $withdrawal */
                $withdrawal = ProviderWithdrawal::where('reference', $reference)->lockForUpdate()->first();

                if ($withdrawal && !in_array((string) $withdrawal->status, ['paid', 'failed', 'rejected'], true)) {
                    $withdrawal->update([
                        'status' => 'failed',
                        'rejected_reason' => $reason,
                    ]);

                    // Reverse wallet debit so provider doesn't lose money due to payout failure.
                    if (Schema::hasColumn('providers', 'wallet_balance') && Schema::hasTable('provider_wallet_ledgers')) {
                        $provider = Provider::whereKey((int) $withdrawal->provider_id)->lockForUpdate()->first();
                        if ($provider) {
                            $amount = (float) ($withdrawal->amount ?? 0);
                            if ($amount > 0) {
                                $newWallet = (float) ($provider->wallet_balance ?? 0) + $amount;
                                $provider->update(['wallet_balance' => $newWallet]);

                                ProviderWalletLedger::create([
                                    'provider_id' => $provider->id,
                                    'order_id' => null,
                                    'type' => 'withdrawal_reversal',
                                    'amount' => $amount,
                                    'balance_after' => $newWallet,
                                    'note' => 'Withdrawal reversal (payout failed)',
                                ]);
                            }
                        }
                    }
                }
            }

            // Refund payouts (client refund) - mark order payment status as refund_failed.
            if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'refund_reference') && Schema::hasColumn('orders', 'payment_status')) {
                $order = Order::where('refund_reference', $reference)->lockForUpdate()->first();
                if ($order && (string) ($order->payment_status ?? '') === 'refund_pending') {
                    $order->update([
                        'payment_status' => 'refund_failed',
                    ]);
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    private function findProviderPaymentByRefs(string $reference, string $externalReference): ?ProviderPayment
    {
        if (!Schema::hasTable('provider_payments')) {
            return null;
        }

        $reference = trim($reference);
        $externalReference = trim($externalReference);

        if ($reference !== '') {
            $payment = ProviderPayment::query()
                ->where('reference', $reference)
                ->latest()
                ->first();
            if ($payment) {
                return $payment;
            }
        }

        if ($externalReference !== '') {
            $byRef = ProviderPayment::query()
                ->where('reference', $externalReference)
                ->latest()
                ->first();
            if ($byRef) {
                return $byRef;
            }

            if (preg_match('/^provider-debt-(\d+)$/i', $externalReference, $m)) {
                $id = (int) ($m[1] ?? 0);
                if ($id > 0) {
                    return ProviderPayment::query()->whereKey($id)->first();
                }
            }
        }

        return null;
    }

    private function findOrderByPaymentRefs(string $reference, string $externalReference, array $payload = []): ?Order
    {
        $reference = trim($reference);
        $externalReference = trim($externalReference);

        $metaOrderId = (int) (
            data_get($payload, 'data.metadata.order_id')
            ?? data_get($payload, 'metadata.order_id')
            ?? 0
        );

        if ($metaOrderId > 0) {
            $byId = Order::query()->whereKey($metaOrderId)->first();
            if ($byId) {
                return $byId;
            }
        }

        if ($externalReference !== '') {
            $byOrderNo = Order::query()->where('order_no', $externalReference)->latest()->first();
            if ($byOrderNo) {
                return $byOrderNo;
            }
        }

        if ($reference !== '' && Schema::hasColumn('orders', 'payment_reference')) {
            $byReference = Order::query()->where('payment_reference', $reference)->latest()->first();
            if ($byReference) {
                return $byReference;
            }
        }

        return null;
    }

    private function resolveCheckoutToken(array $payload, string $reference, string $externalReference): string
    {
        $token = trim((string) (
            data_get($payload, 'data.metadata.checkout_token')
            ?: data_get($payload, 'metadata.checkout_token')
        ));
        if ($token !== '') {
            return strtolower($token);
        }

        $byExternal = CheckoutPayment::tokenFromExternalReference($externalReference);
        if ($byExternal !== null && $byExternal !== '') {
            return strtolower(trim($byExternal));
        }

        $byReference = $reference !== ''
            ? Cache::get(CheckoutPayment::referenceKey($reference))
            : null;
        if (is_string($byReference) && trim($byReference) !== '') {
            return strtolower(trim($byReference));
        }

        return '';
    }

    private function sendClientPaymentReceiptSms(Order $order): void
    {
        $phone = trim((string) data_get($order, 'client.phone', ''));
        if ($phone === '') {
            return;
        }

        $orderNo = (string) ($order->order_no ?? ('#' . $order->id));
        $amount = number_format((float) ($order->price_total ?? 0), 0);
        $serviceName = trim((string) data_get($order, 'service.name', ''));

        $message = 'Glamo risiti: Malipo ya oda ' . $orderNo . ' yamepokelewa (TZS ' . $amount . ').';
        if ($serviceName !== '') {
            $message .= ' Huduma: ' . $serviceName . '.';
        }
        $message .= ' Pesa imehifadhiwa salama hadi kazi ikamilike.';

        app(BeemSms::class)->sendMessage(
            $phone,
            $this->limitSmsText($message),
            (int) data_get($order, 'client.id', $order->client_id)
        );
    }

    private function sendProviderPaymentReceivedSms(Order $order): void
    {
        $phone = trim((string) (
            data_get($order, 'provider.user.phone')
            ?: data_get($order, 'provider.phone_public')
            ?: ''
        ));
        if ($phone === '') {
            return;
        }

        $orderNo = (string) ($order->order_no ?? ('#' . $order->id));
        $amount = number_format((float) ($order->price_total ?? 0), 0);
        $message = 'Glamo: Muamala wa oda ' . $orderNo . ' umepokelewa (TZS ' . $amount . '). Unaweza kuendelea na huduma.';

        app(BeemSms::class)->sendMessage(
            $phone,
            $this->limitSmsText($message),
            (int) data_get($order, 'provider.id', $order->provider_id)
        );
    }

    private function limitSmsText(string $message, int $maxLength = 150): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $message));
        if ($text === '') {
            return '';
        }

        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(substr($text, 0, max(1, $maxLength - 3))) . '...';
    }
}
