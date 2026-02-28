<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Provider;
use App\Models\ProviderWalletLedger;
use App\Models\ProviderWithdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProviderWalletService
{
    public function releaseEscrow(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (!Schema::hasColumn('orders', 'escrow_released_at')) {
                return $order;
            }

            if ($order->escrow_released_at !== null) {
                return $order;
            }

            if ((string) ($order->status ?? '') !== 'completed') {
                return $order;
            }

            if ((string) ($order->payment_method ?? '') !== 'prepay') {
                return $order;
            }

            if ((string) ($order->payment_status ?? '') !== 'held') {
                return $order;
            }

            if (!Schema::hasColumn('providers', 'wallet_balance') || !Schema::hasTable('provider_wallet_ledgers')) {
                return $order;
            }

            $provider = Provider::whereKey((int) $order->provider_id)->lockForUpdate()->first();
            if (!$provider) {
                return $order;
            }

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

            $updates = [
                'payment_status' => 'released',
                'escrow_released_at' => now(),
            ];
            if (Schema::hasColumn('orders', 'payout_amount')) {
                $updates['payout_amount'] = $payout;
            }

            $order->update($updates);

            return $order->fresh();
        });
    }

    public function requestWithdrawal(Provider $provider, float $amount, ?string $method = null, ?string $destination = null): ProviderWithdrawal
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            abort(422, 'Invalid amount.');
        }

        abort_unless(Schema::hasColumn('providers', 'wallet_balance'), 422);
        abort_unless(Schema::hasTable('provider_wallet_ledgers'), 422);

        return DB::transaction(function () use ($provider, $amount, $method, $destination) {
            $provider = Provider::whereKey($provider->id)->lockForUpdate()->firstOrFail();

            $current = (float) ($provider->wallet_balance ?? 0);
            if ($amount > $current) {
                abort(422, 'Insufficient wallet balance.');
            }

            $newWallet = $current - $amount;
            $provider->update(['wallet_balance' => $newWallet]);

            ProviderWalletLedger::create([
                'provider_id' => $provider->id,
                'order_id' => null,
                'type' => 'withdrawal_debit',
                'amount' => -1 * $amount,
                'balance_after' => $newWallet,
                'note' => 'Withdrawal request',
            ]);

            return ProviderWithdrawal::create([
                'provider_id' => $provider->id,
                'amount' => $amount,
                'method' => $method,
                'destination' => $destination,
                'status' => 'pending',
                'reference' => null,
                'paid_at' => null,
                'rejected_reason' => null,
            ]);
        });
    }

    public function failWithdrawalAndReverse(ProviderWithdrawal $withdrawal, string $reason): ProviderWithdrawal
    {
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Withdrawal failed';
        }

        abort_unless(Schema::hasColumn('providers', 'wallet_balance'), 422);
        abort_unless(Schema::hasTable('provider_wallet_ledgers'), 422);

        return DB::transaction(function () use ($withdrawal, $reason) {
            $withdrawal = ProviderWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (in_array((string) $withdrawal->status, ['paid', 'failed', 'rejected'], true)) {
                return $withdrawal;
            }

            $provider = Provider::whereKey((int) $withdrawal->provider_id)->lockForUpdate()->first();
            if (!$provider) {
                $withdrawal->update([
                    'status' => 'failed',
                    'rejected_reason' => $reason,
                ]);
                return $withdrawal;
            }

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
                    'note' => 'Withdrawal reversal',
                ]);
            }

            $withdrawal->update([
                'status' => 'failed',
                'rejected_reason' => $reason,
            ]);

            return $withdrawal->fresh();
        });
    }
}
