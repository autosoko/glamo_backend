<?php

namespace App\Services;

use App\Events\ProviderAvailabilityUpdated;
use App\Models\Order;
use App\Models\Provider;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProviderAvailabilityService
{
    private const MANUAL_OFFLINE_REASON = 'Imewekwa offline na mtoa huduma.';

    public function __construct(private readonly Dispatcher $events)
    {
    }

    public function debtBlockThreshold(): float
    {
        $debtBlock = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);

        return $debtBlock > 0 ? $debtBlock : 10000.0;
    }

    public function hasBlockingOrders(int $providerId, ?int $excludeOrderId = null, bool $ignoreSuspended = false): bool
    {
        $query = Order::query()
            ->where('provider_id', $providerId)
            ->whereNotIn('status', ['completed', 'cancelled']);

        if ($ignoreSuspended) {
            $query->where('status', '!=', 'suspended');
        }

        if ($excludeOrderId !== null && $excludeOrderId > 0) {
            $query->where('id', '!=', $excludeOrderId);
        }

        return $query->exists();
    }

    public function isDebtBlocked(Provider $provider, ?float $debtBlock = null): bool
    {
        $debtBlock = $debtBlock ?? $this->debtBlockThreshold();

        return max(0, (float) ($provider->debt_balance ?? 0)) >= $debtBlock;
    }

    public function availabilityControlState(Provider $provider, bool $hasBlockingOrders, ?float $debtBlock = null): array
    {
        $debtBlock = $debtBlock ?? $this->debtBlockThreshold();
        $debtBalance = max(0, (float) ($provider->debt_balance ?? 0));
        $onlineStatus = strtolower((string) ($provider->online_status ?? 'offline'));
        $nextAction = $onlineStatus === 'online' ? 'offline' : 'online';

        $canToggle = true;
        $reason = null;

        if ($hasBlockingOrders) {
            $canToggle = false;
            $reason = 'Una oda ambayo bado iko active. Kamilisha au gahirisha oda kwanza.';
        } elseif ($nextAction === 'online' && !$this->isApprovedForOnline($provider)) {
            $canToggle = false;
            $reason = 'Akaunti yako bado haijapitishwa kikamilifu.';
        } elseif ($nextAction === 'online' && $this->isDebtBlocked($provider, $debtBlock)) {
            $canToggle = false;
            $reason = 'Deni limefika TZS ' . number_format($debtBlock, 0) . ' au zaidi. Lipa deni liwe chini ya hapo kwanza.';
        }

        return [
            'current_status' => $onlineStatus,
            'next_action' => $nextAction,
            'can_toggle' => $canToggle,
            'reason' => $reason,
            'debt_balance' => $debtBalance,
            'debt_block_threshold' => (float) $debtBlock,
            'has_uncompleted_order' => $hasBlockingOrders,
            'is_approved' => (string) ($provider->approval_status ?? '') === 'approved',
        ];
    }

    public function sync(
        Provider $provider,
        ?int $excludeOrderId = null,
        bool $ignoreSuspended = false,
        ?string $busyReason = null,
        bool $restoreOnlineWhenEligible = false
    ): Provider {
        $debtBlock = $this->debtBlockThreshold();
        $hasBlockingOrders = $this->hasBlockingOrders((int) $provider->id, $excludeOrderId, $ignoreSuspended);
        $currentStatus = (string) ($provider->online_status ?? 'offline');
        $currentReason = $provider->offline_reason;

        $nextStatus = $currentStatus;
        $nextReason = $currentReason;

        if ($hasBlockingOrders) {
            $nextStatus = 'offline';
            $nextReason = $busyReason ?: 'Ana oda ambayo bado iko active.';
        } elseif ($this->isDebtBlocked($provider, $debtBlock)) {
            $nextStatus = 'blocked_debt';
            $nextReason = 'Deni limefika TZS ' . number_format($debtBlock, 0) . ' au zaidi. Lipa deni liwe chini ya hapo.';
        } elseif (!$this->isApprovedForOnline($provider)) {
            $nextStatus = 'offline';
            $nextReason = 'Akaunti bado haijaruhusiwa kwenda online.';
        } elseif ($restoreOnlineWhenEligible) {
            $nextStatus = 'online';
            $nextReason = null;
        } elseif ($currentStatus === 'blocked_debt') {
            $nextStatus = 'offline';
            $nextReason = 'Deni limeshuka chini ya TZS ' . number_format($debtBlock, 0) . '. Unaweza kujiweka online.';
        } elseif ($currentStatus === 'busy') {
            $nextStatus = 'offline';
            $nextReason = null;
        }

        if ($nextStatus === $currentStatus && $nextReason === $currentReason) {
            return $provider;
        }

        $provider->update([
            'online_status' => $nextStatus,
            'offline_reason' => $nextReason,
        ]);

        $provider->refresh();

        $this->queueBroadcast((int) $provider->id, $debtBlock, $hasBlockingOrders);

        return $provider;
    }

    public function markManualOnline(Provider $provider): Provider
    {
        return $this->applyStatus($provider, 'online', null);
    }

    public function markManualOffline(Provider $provider): Provider
    {
        return $this->applyStatus($provider, 'offline', self::MANUAL_OFFLINE_REASON);
    }

    private function applyStatus(Provider $provider, string $status, ?string $offlineReason): Provider
    {
        $changed = (string) ($provider->online_status ?? 'offline') !== $status
            || $provider->offline_reason !== $offlineReason;

        if ($changed) {
            $provider->update([
                'online_status' => $status,
                'offline_reason' => $offlineReason,
            ]);

            $provider->refresh();
        }

        $this->queueBroadcast((int) $provider->id);

        return $provider;
    }

    private function isApprovedForOnline(Provider $provider): bool
    {
        return (string) ($provider->approval_status ?? '') === 'approved'
            && (bool) ($provider->is_active ?? true);
    }

    private function queueBroadcast(int $providerId, ?float $debtBlock = null, ?bool $hasBlockingOrders = null): void
    {
        if ($providerId <= 0) {
            return;
        }

        DB::afterCommit(function () use ($providerId, $debtBlock, $hasBlockingOrders) {
            $provider = Provider::query()->find($providerId);
            if (!$provider) {
                return;
            }

            $resolvedDebtBlock = $debtBlock ?? $this->debtBlockThreshold();
            $resolvedBlockingOrders = $hasBlockingOrders ?? $this->hasBlockingOrders((int) $provider->id);

            try {
                $this->events->dispatch(new ProviderAvailabilityUpdated($provider, [
                    'availability_control' => $this->availabilityControlState($provider, $resolvedBlockingOrders, $resolvedDebtBlock),
                ]));
            } catch (\Throwable $e) {
                Log::warning('Provider availability dispatch failed', [
                    'provider_id' => (int) $provider->id,
                    'user_id' => (int) ($provider->user_id ?? 0),
                    'online_status' => (string) ($provider->online_status ?? 'offline'),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
