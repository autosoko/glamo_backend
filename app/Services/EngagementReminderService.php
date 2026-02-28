<?php

namespace App\Services;

use App\Models\NotificationCampaignState;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class EngagementReminderService
{
    public const CAMPAIGN_PROVIDER_DEBT = 'provider_debt';
    public const CAMPAIGN_NO_BOOKING = 'client_no_booking';
    public const CAMPAIGN_LAST_BOOKING_4_DAYS = 'client_last_booking_4_days';

    public function __construct(private readonly AppNotificationService $notifications)
    {
    }

    public function run(string $campaign = 'all'): array
    {
        return match ($campaign) {
            self::CAMPAIGN_PROVIDER_DEBT => [self::CAMPAIGN_PROVIDER_DEBT => $this->sendProviderDebtReminders()],
            self::CAMPAIGN_NO_BOOKING => [self::CAMPAIGN_NO_BOOKING => $this->sendNoBookingReminders()],
            self::CAMPAIGN_LAST_BOOKING_4_DAYS => [self::CAMPAIGN_LAST_BOOKING_4_DAYS => $this->sendLastBookingReminders()],
            default => [
                self::CAMPAIGN_PROVIDER_DEBT => $this->sendProviderDebtReminders(),
                self::CAMPAIGN_NO_BOOKING => $this->sendNoBookingReminders(),
                self::CAMPAIGN_LAST_BOOKING_4_DAYS => $this->sendLastBookingReminders(),
            ],
        };
    }

    private function sendProviderDebtReminders(): array
    {
        $threshold = (float) config('glamo_pricing.provider_debt_block_threshold', 10000);
        if ($threshold <= 0) {
            $threshold = 10000;
        }

        $result = [
            'checked' => 0,
            'sent' => 0,
            'skipped_cooldown' => 0,
        ];

        Provider::query()
            ->whereNotNull('user_id')
            ->where('debt_balance', '>', $threshold)
            ->orderBy('id')
            ->chunkById(200, function ($providers) use (&$result, $threshold): void {
                foreach ($providers as $provider) {
                    $result['checked']++;
                    $userId = (int) ($provider->user_id ?? 0);
                    if ($userId <= 0) {
                        continue;
                    }

                    if (! $this->canSend($userId, self::CAMPAIGN_PROVIDER_DEBT, 60)) {
                        $result['skipped_cooldown']++;
                        continue;
                    }

                    $this->notifications->sendToUsers(
                        [$userId],
                        'provider_debt_reminder',
                        'Deni lako limezidi TZS ' . number_format($threshold, 0),
                        'Lipa deni lako ili urudi online na uendelee kupata oda.',
                        [
                            'provider_id' => (string) (int) $provider->id,
                            'debt_balance' => (string) round((float) ($provider->debt_balance ?? 0), 2),
                            'target_screen' => 'provider_debt',
                        ],
                        true
                    );

                    $this->markSent($userId, self::CAMPAIGN_PROVIDER_DEBT);
                    $result['sent']++;
                }
            });

        return $result;
    }

    private function sendNoBookingReminders(): array
    {
        $result = [
            'checked' => 0,
            'sent' => 0,
            'skipped_cooldown' => 0,
        ];

        User::query()
            ->where('role', 'client')
            ->where('created_at', '<=', now()->subHours(2))
            ->whereDoesntHave('clientOrders')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$result): void {
                foreach ($users as $user) {
                    $result['checked']++;

                    if (! $this->canSend((int) $user->id, self::CAMPAIGN_NO_BOOKING, 120)) {
                        $result['skipped_cooldown']++;
                        continue;
                    }

                    $this->notifications->sendToUsers(
                        [(int) $user->id],
                        'client_no_booking_reminder',
                        'Karibu Glamo',
                        'Weka booking yako ya kwanza sasa upate huduma bora nyumbani.',
                        [
                            'target_screen' => 'services',
                        ],
                        true
                    );

                    $this->markSent((int) $user->id, self::CAMPAIGN_NO_BOOKING);
                    $result['sent']++;
                }
            });

        return $result;
    }

    private function sendLastBookingReminders(): array
    {
        $result = [
            'checked' => 0,
            'sent' => 0,
            'skipped_cooldown' => 0,
        ];

        $cutoff = now()->subDays(4);

        User::query()
            ->where('role', 'client')
            ->whereHas('clientOrders')
            ->whereDoesntHave('clientOrders', fn (Builder $query) => $query->where('created_at', '>=', $cutoff))
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$result): void {
                foreach ($users as $user) {
                    $result['checked']++;

                    if (! $this->canSend((int) $user->id, self::CAMPAIGN_LAST_BOOKING_4_DAYS, 24 * 60)) {
                        $result['skipped_cooldown']++;
                        continue;
                    }

                    $this->notifications->sendToUsers(
                        [(int) $user->id],
                        'client_last_booking_4_days',
                        'Boresha mwonekano wako leo',
                        'Ni siku kadhaa zimepita tangu booking yako ya mwisho. Panga huduma nyingine sasa.',
                        [
                            'target_screen' => 'services',
                        ],
                        true
                    );

                    $this->markSent((int) $user->id, self::CAMPAIGN_LAST_BOOKING_4_DAYS);
                    $result['sent']++;
                }
            });

        return $result;
    }

    private function canSend(int $userId, string $campaignKey, int $intervalMinutes): bool
    {
        $state = NotificationCampaignState::query()
            ->where('user_id', $userId)
            ->where('campaign_key', $campaignKey)
            ->first();

        if (! $state || $state->last_sent_at === null) {
            return true;
        }

        return now()->gte($state->last_sent_at->copy()->addMinutes(max(1, $intervalMinutes)));
    }

    private function markSent(int $userId, string $campaignKey): void
    {
        NotificationCampaignState::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'campaign_key' => $campaignKey,
            ],
            [
                'last_sent_at' => now(),
            ]
        );
    }
}
