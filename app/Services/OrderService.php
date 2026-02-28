<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\ProviderWalletLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            // Generate order number
            $orderNo = 'GL-' . strtoupper(Str::random(10));

            $commissionPercent = (float) config('glamo_pricing.commission_percent', 10);
            if ($commissionPercent < 0) {
                $commissionPercent = 0;
            }
            $commissionRate = round($commissionPercent / 100, 4);
            $priceTotal = (float) $data['price_total'];
            $commissionAmount = round($priceTotal * $commissionRate, 2);
            $payoutAmount = round(max(0, $priceTotal - $commissionAmount), 2);

            $payload = [
                'order_no' => $orderNo,
                'client_id' => $data['client_id'],
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'status' => 'pending',
                'client_lat' => $data['client_lat'],
                'client_lng' => $data['client_lng'],
                'address_text' => $data['address_text'] ?? null,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'price_total' => $priceTotal,
                'price_subtotal' => $data['price_subtotal'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'coupon_id' => $data['coupon_id'] ?? null,
                'coupon_code' => $data['coupon_code'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'payment_channel' => $data['payment_channel'] ?? null,
                'payment_status' => $data['payment_status'] ?? null,
                'payment_provider' => $data['payment_provider'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'price_service' => $data['price_service'] ?? null,
                'price_materials' => $data['price_materials'] ?? null,
                'price_travel' => $data['price_travel'] ?? null,
                'price_usage' => $data['price_usage'] ?? null,
                'travel_distance_km' => $data['travel_distance_km'] ?? null,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'payout_amount' => $payoutAmount,
                'finish_code' => (string) random_int(1000, 9999), // optional
            ];

            // Backward compatible: only include optional columns if they exist.
            foreach (['price_subtotal', 'discount_amount', 'coupon_id', 'coupon_code', 'payment_method', 'payment_channel', 'payment_status', 'payment_provider', 'payment_reference', 'payout_amount'] as $col) {
                if (!Schema::hasColumn('orders', $col)) {
                    unset($payload[$col]);
                }
            }

            $order = Order::create($payload);

            $provider = Provider::whereKey((int) $data['provider_id'])->lockForUpdate()->first();
            if ($provider && (string) ($provider->online_status ?? '') === 'online') {
                $provider->update([
                    'online_status' => 'offline',
                    'offline_reason' => 'Ana oda mpya inayosubiri uthibitisho wa mwisho.',
                ]);
            }

            if (Schema::hasTable('order_items')) {
                $now = now();

                $items = $data['items'] ?? null;
                $rows = [];

                if (is_array($items) && !empty($items)) {
                    foreach ($items as $it) {
                        $serviceId = (int) ($it['service_id'] ?? 0);
                        if ($serviceId <= 0) {
                            continue;
                        }

                        $svc = (float) ($it['price_service'] ?? 0);
                        $mat = (float) ($it['price_materials'] ?? 0);
                        $use = (float) ($it['price_usage'] ?? 0);
                        $pct = (float) ($it['usage_percent'] ?? config('glamo_pricing.usage_percent', 5));
                        $line = (float) ($it['line_total'] ?? ($svc + $mat + $use));

                        $rows[] = [
                            'order_id' => $order->id,
                            'service_id' => $serviceId,
                            'price_service' => $svc,
                            'price_materials' => $mat,
                            'price_usage' => $use,
                            'usage_percent' => $pct,
                            'line_total' => $line,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (empty($rows)) {
                    $serviceId = (int) ($data['service_id'] ?? 0);
                    if ($serviceId > 0) {
                        $svc = (float) ($data['price_service'] ?? $priceTotal);
                        $mat = (float) ($data['price_materials'] ?? 0);
                        $use = (float) ($data['price_usage'] ?? 0);
                        $pct = (float) config('glamo_pricing.usage_percent', 5);

                        $rows[] = [
                            'order_id' => $order->id,
                            'service_id' => $serviceId,
                            'price_service' => $svc,
                            'price_materials' => $mat,
                            'price_usage' => $use,
                            'usage_percent' => $pct,
                            'line_total' => ($svc + $mat + $use),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if (!empty($rows)) {
                    DB::table('order_items')->insert($rows);
                }
            }

            return $order;
        });
    }

    public function acceptOrder(Order $order, Provider $provider, bool $goNow = true, mixed $scheduledAt = null): Order
    {
        return DB::transaction(function () use ($order, $provider, $goNow, $scheduledAt) {

            // lock rows
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $provider = Provider::whereKey($provider->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== 'pending') {
                abort(422, 'Order is not pending.');
            }
            if ($order->provider_id !== $provider->id) {
                abort(403, 'Not your order.');
            }

            // If client prepaid, provider should only accept after payment is confirmed (held in escrow).
            if (
                Schema::hasColumn('orders', 'payment_method')
                && (string) ($order->payment_method ?? '') === 'prepay'
                && Schema::hasColumn('orders', 'payment_status')
                && (string) ($order->payment_status ?? '') !== 'held'
            ) {
                abort(422, 'Payment not confirmed yet.');
            }
            if ($provider->approval_status !== 'approved') {
                abort(422, 'Provider not approved.');
            }
            if (in_array($provider->online_status, ['busy','blocked_debt'], true)) {
                abort(422, 'Provider not available.');
            }

            $now = now();
            $scheduledFor = null;

            if (!$goNow) {
                if ($scheduledAt instanceof Carbon) {
                    $scheduledFor = $scheduledAt->copy();
                } else {
                    $raw = trim((string) $scheduledAt);
                    if ($raw === '') {
                        abort(422, 'Chagua tarehe na saa ya kwenda kwa mteja.');
                    }

                    try {
                        $scheduledFor = Carbon::parse($raw);
                    } catch (\Throwable $e) {
                        abort(422, 'Ratiba uliyochagua si sahihi.');
                    }
                }

                if (!$scheduledFor || $scheduledFor->lte($now)) {
                    abort(422, 'Ratiba ya kwenda baadaye lazima iwe muda ujao.');
                }

                $requiredColumns = [
                    'suspended_at',
                    'suspended_until_at',
                    'suspension_note',
                    'resumed_at',
                    'schedule_notified_at',
                ];

                foreach ($requiredColumns as $column) {
                    if (!Schema::hasColumn('orders', $column)) {
                        abort(422, 'Feature ya ratiba ya kwenda baadaye haijakamilika kwenye database.');
                    }
                }
            }

            // Mark order as on the way immediately, or suspend it if provider selected "later".
            if ($goNow) {
                $orderUpdates = [
                    'status' => 'on_the_way',
                    'accepted_at' => $now,
                ];
                if (Schema::hasColumn('orders', 'on_the_way_at')) {
                    $orderUpdates['on_the_way_at'] = $now;
                }
            } else {
                $orderUpdates = [
                    'status' => 'suspended',
                    'accepted_at' => $now,
                    'suspended_at' => $now,
                    'suspended_until_at' => $scheduledFor,
                    'suspension_note' => 'Provider amepanga kwenda: ' . $scheduledFor->format('d/m/Y H:i'),
                    'resumed_at' => null,
                    'schedule_notified_at' => null,
                ];

                if (Schema::hasColumn('orders', 'on_the_way_at')) {
                    $orderUpdates['on_the_way_at'] = null;
                }
            }
            $order->update($orderUpdates);

            if ($goNow) {
                $provider->update([
                    'online_status' => 'busy',
                    'offline_reason' => null,
                ]);
            } else {
                $provider->update([
                    'online_status' => 'online',
                    'offline_reason' => null,
                ]);
            }

            $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
            if ($debtBlock <= 0) {
                $debtBlock = 10000;
            }

            $method = (string) ($order->payment_method ?? '');
            $isCash = $method === '' || $method === 'cash';

            // Cash orders: commission becomes provider debt (client paid provider directly).
            if ($isCash) {
                $commission = (float) ($order->commission_amount ?? 0);
                $newBalance = (float) ($provider->debt_balance ?? 0) + $commission;

                $provider->update([
                    'debt_balance' => $newBalance,
                ]);

                $commissionPercent = (float) config('glamo_pricing.commission_percent', 10);
                $percentLabel = rtrim(rtrim(number_format($commissionPercent, 2, '.', ''), '0'), '.');
                ProviderLedger::create([
                    'provider_id' => $provider->id,
                    'type' => 'commission_debit',
                    'order_id' => $order->id,
                    'amount' => $commission,
                    'balance_after' => $newBalance,
                    'note' => ($percentLabel === '' ? '0' : $percentLabel) . '% commission for order ' . $order->order_no,
                ]);

                // Auto block if over threshold
                if ((float) $provider->debt_balance > $debtBlock) {
                    $provider->update([
                        'online_status' => 'blocked_debt',
                        'offline_reason' => 'Debt over ' . number_format($debtBlock, 0) . '. Please pay to continue.',
                    ]);
                }
            }

            return $order->fresh();
        });
    }

    public function completeOrder(Order $order, Provider $provider, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $provider, $note) {

            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $provider = Provider::whereKey($provider->id)->lockForUpdate()->firstOrFail();

            if (!in_array($order->status, ['accepted','in_progress','on_the_way'], true)) {
                abort(422, 'Order cannot be completed.');
            }
            if ($order->provider_id !== $provider->id) {
                abort(403, 'Not your order.');
            }

            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completion_note' => $note,
            ]);

            // Prepay orders: release escrow to provider wallet AFTER completion.
            if (
                Schema::hasColumn('orders', 'payment_method')
                && Schema::hasColumn('orders', 'payment_status')
                && Schema::hasColumn('orders', 'escrow_released_at')
                && Schema::hasColumn('providers', 'wallet_balance')
                && Schema::hasTable('provider_wallet_ledgers')
                && (string) ($order->payment_method ?? '') === 'prepay'
                && (string) ($order->payment_status ?? '') === 'held'
                && $order->escrow_released_at === null
            ) {
                $payout = (float) ($order->payout_amount ?? 0);
                if ($payout <= 0) {
                    $payout = max(0, (float) ($order->price_total ?? 0) - (float) ($order->commission_amount ?? 0));
                }

                $newWallet = (float) ($provider->wallet_balance ?? 0) + $payout;
                $provider->update(['wallet_balance' => $newWallet]);

                ProviderWalletLedger::create([
                    'provider_id' => $provider->id,
                    'order_id' => $order->id,
                    'type' => 'escrow_release',
                    'amount' => $payout,
                    'balance_after' => $newWallet,
                    'note' => 'Escrow released for order ' . (string) ($order->order_no ?? ''),
                ]);

                $order->update([
                    'payment_status' => 'released',
                    'escrow_released_at' => now(),
                ]);
            }

            // provider returns online only if allowed
            $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
            if ($debtBlock <= 0) {
                $debtBlock = 10000;
            }

            $hasBlockingOrders = Order::query()
                ->where('provider_id', (int) $provider->id)
                ->where('id', '!=', (int) $order->id)
                ->whereNotIn('status', ['completed', 'cancelled', 'suspended'])
                ->exists();

            $debt = max(0, (float) ($provider->debt_balance ?? 0));
            $isDebtBlocked = $debt > $debtBlock
                || ((string) ($provider->online_status ?? '') === 'blocked_debt' && $debt >= $debtBlock);

            if ($hasBlockingOrders) {
                $provider->update([
                    'online_status' => 'offline',
                    'offline_reason' => 'Ana oda nyingine inayoendelea.',
                ]);
            } elseif ($isDebtBlocked) {
                $provider->update([
                    'online_status' => 'blocked_debt',
                    'offline_reason' => 'Debt over ' . number_format($debtBlock, 0) . '. Please pay.',
                ]);
            } else {
                $provider->update([
                    'online_status' => 'offline',
                    'offline_reason' => null,
                ]);
            }

            // increment quick stats
            $provider->increment('total_orders');

            return $order->fresh();
        });
    }
}
