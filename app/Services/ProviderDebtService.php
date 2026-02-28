<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\ProviderLedger;
use App\Models\ProviderPayment;
use Illuminate\Support\Facades\DB;

class ProviderDebtService
{
    public function markPaymentAsPaid(ProviderPayment $payment): ProviderPayment
    {
        return DB::transaction(function () use ($payment) {

            $payment = ProviderPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') return $payment;

            $provider = Provider::whereKey($payment->provider_id)->lockForUpdate()->firstOrFail();

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // credit reduces debt (we store as negative amount)
            $newBalance = (float) $provider->debt_balance - (float) $payment->amount;
            if ($newBalance < 0) $newBalance = 0;

            ProviderLedger::create([
                'provider_id' => $provider->id,
                'type' => 'payment_credit',
                'order_id' => null,
                'amount' => -1 * (float) $payment->amount,
                'balance_after' => $newBalance,
                'note' => 'Payment received: ' . ($payment->reference ?? 'N/A'),
            ]);

            $provider->update(['debt_balance' => $newBalance]);

            // Remove blocked_debt only when debt drops below threshold (strictly below).
            $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
            if ($debtBlock <= 0) {
                $debtBlock = 10000;
            }

            if ($provider->online_status === 'blocked_debt' && (float) $provider->debt_balance < $debtBlock) {
                $provider->update([
                    'online_status' => 'offline',
                    'offline_reason' => 'Debt lowered below threshold. You can go online.',
                ]);
            }

            return $payment->fresh();
        });
    }
}
