<?php

namespace App\Services;

use App\Mail\GlamoAnnouncementMail;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserBroadcastService
{
    public const SEGMENT_ALL = 'all';
    public const SEGMENT_CLIENTS = 'clients';
    public const SEGMENT_PROVIDERS = 'providers';
    public const SEGMENT_PROVIDERS_ONLINE = 'providers_online';
    public const SEGMENT_PROVIDERS_OFFLINE = 'providers_offline';
    public const SEGMENT_CLIENTS_INACTIVE_7_DAYS = 'clients_inactive_7_days';
    public const SEGMENT_CLIENTS_NO_BOOKING = 'clients_no_booking';

    public function __construct(
        private readonly BeemSms $beemSms,
        private readonly AppNotificationService $appNotifications,
    )
    {
    }

    public function segmentOptions(): array
    {
        return [
            self::SEGMENT_ALL => 'Wote (wateja + watoa huduma)',
            self::SEGMENT_CLIENTS => 'Wateja wote',
            self::SEGMENT_PROVIDERS => 'Watoa huduma wote',
            self::SEGMENT_PROVIDERS_ONLINE => 'Watoa huduma online',
            self::SEGMENT_PROVIDERS_OFFLINE => 'Watoa huduma offline',
            self::SEGMENT_CLIENTS_INACTIVE_7_DAYS => 'Wateja wasiofanya booking wiki iliyopita',
            self::SEGMENT_CLIENTS_NO_BOOKING => 'Wateja waliosajiliwa bila booking yoyote',
        ];
    }

    public function globalRegistrationCounts(): array
    {
        return $this->registrationCounts($this->baseAudienceQuery());
    }

    public function segmentRegistrationCounts(string $segment): array
    {
        return $this->registrationCounts($this->queryForSegment($segment));
    }

    public function sendToSegment(
        string $segment,
        string $subject,
        string $message,
        bool $sendSms = false,
        array $meta = []
    ): array {
        return $this->sendToUserQuery($this->queryForSegment($segment), $subject, $message, $sendSms, $meta);
    }

    public function notifyClientsForNewService(Service $service): array
    {
        $service->loadMissing('serviceCategory');

        $category = trim((string) data_get($service, 'serviceCategory.name'));
        $price = (float) ($service->base_price ?? 0);
        $duration = (int) ($service->duration_minutes ?? 60);

        $subject = 'Huduma mpya imeongezwa Glamo';
        $title = 'Huduma mpya imewasili';

        $parts = [
            "Huduma: {$service->name}",
            $category !== '' ? "Category: {$category}" : null,
            "Bei ya kuanzia: TZS " . number_format($price, 0),
            "Muda wa makadirio: {$duration} dk",
            'Tembelea huduma zote na weka booking sasa.',
        ];

        $message = implode("\n", array_values(array_filter($parts)));

        return $this->sendToUserQuery(
            User::query()->whereIn('role', ['client', 'provider']),
            $subject,
            $message,
            true,
            [
                'title' => $title,
                'button_text' => 'Angalia huduma',
                'button_url' => rtrim((string) config('services.glamo.website_url', 'https://getglamo.com'), '/') . '/huduma',
                'source' => 'service_created',
                'service_id' => $service->id,
                'target_screen' => 'services',
                'type' => 'new_service',
            ]
        );
    }

    public function notifyNearbyClientsForApprovedProvider(Provider $provider, float $radiusKm = 5): array
    {
        $provider->loadMissing('user');

        $lat = $this->asFloat($provider->current_lat);
        $lng = $this->asFloat($provider->current_lng);

        if ($lat === null || $lng === null) {
            $lat = $this->asFloat(data_get($provider, 'user.last_lat'));
            $lng = $this->asFloat(data_get($provider, 'user.last_lng'));
        }

        if ($lat === null || $lng === null) {
            return [
                'recipients' => 0,
                'email_attempted' => 0,
                'email_sent' => 0,
                'email_failed' => 0,
                'sms_attempted' => 0,
                'sms_sent' => 0,
                'sms_failed' => 0,
                'missing_email' => 0,
                'missing_phone' => 0,
                'reason' => 'provider_location_missing',
            ];
        }

        $clientIds = User::query()
            ->where('role', 'client')
            ->whereNotNull('last_lat')
            ->whereNotNull('last_lng')
            ->select('users.id')
            ->selectRaw(
                '(6371 * ACOS(COS(RADIANS(?)) * COS(RADIANS(users.last_lat)) * COS(RADIANS(users.last_lng) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(users.last_lat)))) AS distance_km',
                [$lat, $lng, $lat]
            )
            ->having('distance_km', '<=', max(0.1, $radiusKm))
            ->pluck('id')
            ->all();

        if (empty($clientIds)) {
            return [
                'recipients' => 0,
                'email_attempted' => 0,
                'email_sent' => 0,
                'email_failed' => 0,
                'sms_attempted' => 0,
                'sms_sent' => 0,
                'sms_failed' => 0,
                'missing_email' => 0,
                'missing_phone' => 0,
                'reason' => 'no_nearby_clients',
            ];
        }

        $providerName = trim((string) data_get($provider, 'display_name'));

        $skillNames = collect((array) ($provider->selected_skills ?? []))
            ->map(fn ($item) => ucwords(str_replace(['-', '_'], ' ', strtolower((string) $item))))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $skillsText = !empty($skillNames) ? implode(', ', $skillNames) : 'Huduma mbalimbali';

        $subject = 'Mtoa huduma mpya yuko karibu nawe';
        $title = 'Mtoa huduma mpya ameidhinishwa karibu yako';
        $message = implode("\n", array_filter([
            $providerName !== '' ? "Jina: {$providerName}" : null,
            "Anatoa: {$skillsText}",
            'Sasa unaweza kuweka booking kwa urahisi ndani ya Glamo.',
        ]));

        return $this->sendToUserQuery(
            User::query()->whereIn('id', $clientIds),
            $subject,
            $message,
            true,
            [
                'title' => $title,
                'button_text' => 'Weka booking sasa',
                'button_url' => rtrim((string) config('services.glamo.website_url', 'https://getglamo.com'), '/') . '/huduma',
                'source' => 'provider_approved_nearby',
                'provider_id' => $provider->id,
                'radius_km' => $radiusKm,
                'target_screen' => 'services',
                'type' => 'provider_approved_nearby',
            ]
        );
    }

    private function queryForSegment(string $segment): Builder
    {
        return match ($segment) {
            self::SEGMENT_CLIENTS => User::query()->where('role', 'client'),
            self::SEGMENT_PROVIDERS => User::query()->where('role', 'provider'),
            self::SEGMENT_PROVIDERS_ONLINE => User::query()
                ->where('role', 'provider')
                ->whereHas('provider', fn (Builder $query) => $query->where('online_status', 'online')),
            self::SEGMENT_PROVIDERS_OFFLINE => User::query()
                ->where('role', 'provider')
                ->whereHas('provider', fn (Builder $query) => $query->where('online_status', 'offline')),
            self::SEGMENT_CLIENTS_INACTIVE_7_DAYS => User::query()
                ->where('role', 'client')
                ->whereHas('clientOrders')
                ->whereDoesntHave('clientOrders', fn (Builder $query) => $query->where('created_at', '>=', now()->subDays(7))),
            self::SEGMENT_CLIENTS_NO_BOOKING => User::query()
                ->where('role', 'client')
                ->whereDoesntHave('clientOrders'),
            default => $this->baseAudienceQuery(),
        };
    }

    private function baseAudienceQuery(): Builder
    {
        return User::query()->whereIn('role', ['client', 'provider']);
    }

    private function registrationCounts(Builder $query): array
    {
        $total = (clone $query)->count('users.id');

        $emailRegistered = (clone $query)
            ->whereNotNull('users.email')
            ->whereRaw("TRIM(users.email) <> ''")
            ->count('users.id');

        $phoneRegistered = (clone $query)
            ->whereNotNull('users.phone')
            ->whereRaw("TRIM(users.phone) <> ''")
            ->count('users.id');

        $pushRegistered = (clone $query)
            ->whereHas('devicePushTokens', fn (Builder $builder) => $builder->where('is_active', true))
            ->count('users.id');

        $both = (clone $query)
            ->whereNotNull('users.email')
            ->whereRaw("TRIM(users.email) <> ''")
            ->whereNotNull('users.phone')
            ->whereRaw("TRIM(users.phone) <> ''")
            ->count('users.id');

        $emailOnly = (clone $query)
            ->whereNotNull('users.email')
            ->whereRaw("TRIM(users.email) <> ''")
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('users.phone')
                    ->orWhereRaw("TRIM(users.phone) = ''");
            })
            ->count('users.id');

        $phoneOnly = (clone $query)
            ->whereNotNull('users.phone')
            ->whereRaw("TRIM(users.phone) <> ''")
            ->where(function (Builder $builder): void {
                $builder
                    ->whereNull('users.email')
                    ->orWhereRaw("TRIM(users.email) = ''");
            })
            ->count('users.id');

        return [
            'total' => (int) $total,
            'email_registered' => (int) $emailRegistered,
            'phone_registered' => (int) $phoneRegistered,
            'push_registered' => (int) $pushRegistered,
            'both' => (int) $both,
            'email_only' => (int) $emailOnly,
            'phone_only' => (int) $phoneOnly,
        ];
    }

    private function sendToUserQuery(
        Builder $query,
        string $subject,
        string $message,
        bool $sendSms,
        array $meta = []
    ): array {
        $subject = trim($subject);
        $message = trim($message);

        $title = trim((string) ($meta['title'] ?? $subject));
        $buttonText = trim((string) ($meta['button_text'] ?? 'Tembelea Glamo'));
        $buttonUrl = trim((string) ($meta['button_url'] ?? config('services.glamo.website_url', 'https://getglamo.com')));

        $result = [
            'recipients' => 0,
            'email_attempted' => 0,
            'email_sent' => 0,
            'email_failed' => 0,
            'sms_attempted' => 0,
            'sms_sent' => 0,
            'sms_failed' => 0,
            'missing_email' => 0,
            'missing_phone' => 0,
            'database_sent' => 0,
            'push_tokens' => 0,
            'push_attempted' => 0,
            'push_sent' => 0,
            'push_failed' => 0,
            'push_invalidated' => 0,
        ];

        (clone $query)
            ->select('users.id', 'users.name', 'users.email', 'users.phone')
            ->orderBy('users.id')
            ->chunkById(200, function (Collection $users) use (
                &$result,
                $subject,
                $title,
                $message,
                $sendSms,
                $buttonText,
                $buttonUrl,
                $meta
            ): void {
                $userIds = [];

                foreach ($users as $user) {
                    /** @var User $user */
                    $result['recipients']++;
                    $userIds[] = (int) $user->id;

                    $email = trim((string) $user->email);
                    if ($email !== '') {
                        $result['email_attempted']++;

                        try {
                            Mail::to($email)->send(new GlamoAnnouncementMail(
                                subjectLine: $subject,
                                title: $title,
                                messageText: $message,
                                buttonText: $buttonText,
                                buttonUrl: $buttonUrl !== '' ? $buttonUrl : null
                            ));

                            $result['email_sent']++;
                        } catch (\Throwable $e) {
                            $result['email_failed']++;
                            Log::warning('Broadcast email failed', [
                                'user_id' => $user->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    } else {
                        $result['missing_email']++;
                    }

                    if (! $sendSms) {
                        continue;
                    }

                    $phone = trim((string) $user->phone);
                    if ($phone === '') {
                        $result['missing_phone']++;
                        continue;
                    }

                    $result['sms_attempted']++;
                    $smsBody = $this->limitSms('Glamo: ' . $subject . '. ' . $message);
                    $sent = $this->beemSms->sendMessage($phone, $smsBody, (int) $user->id);

                    if ($sent) {
                        $result['sms_sent']++;
                    } else {
                        $result['sms_failed']++;
                    }
                }

                $pushMeta = array_merge($meta, [
                    'source' => (string) data_get($meta, 'source', 'broadcast'),
                    'button_text' => $buttonText,
                    'button_url' => $buttonUrl,
                    'target_screen' => (string) data_get($meta, 'target_screen', 'home'),
                    'type' => (string) data_get($meta, 'type', 'admin_broadcast'),
                ]);

                $notifResult = $this->appNotifications->sendToUsers(
                    $userIds,
                    (string) $pushMeta['type'],
                    $title !== '' ? $title : $subject,
                    $message,
                    $pushMeta,
                    true
                );

                $result['database_sent'] += (int) ($notifResult['database_sent'] ?? 0);
                $result['push_tokens'] += (int) data_get($notifResult, 'push.tokens', 0);
                $result['push_attempted'] += (int) data_get($notifResult, 'push.attempted', 0);
                $result['push_sent'] += (int) data_get($notifResult, 'push.sent', 0);
                $result['push_failed'] += (int) data_get($notifResult, 'push.failed', 0);
                $result['push_invalidated'] += (int) data_get($notifResult, 'push.invalidated', 0);
            }, 'id');

        return $result;
    }

    private function limitSms(string $message, int $maxLength = 300): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', strip_tags($message)));

        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, max(0, $maxLength - 3)) . '...';
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
