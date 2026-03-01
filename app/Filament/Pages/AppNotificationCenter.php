<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PushNotificationService;
use App\Support\AppVariant;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use UnitEnum;

class AppNotificationCenter extends Page
{
    protected static ?string $title = 'App Notifications';

    protected static ?string $navigationLabel = 'App Notifications';

    protected static string | UnitEnum | null $navigationGroup = 'Mawasiliano';

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'app-notification-center';

    protected string $view = 'filament.pages.app-notification-center';

    public string $audience = 'clients';

    public string $notificationTitle = '';

    public string $notificationMessage = '';

    public string $targetScreen = 'home';

    public array $audienceOptions = [];

    public array $audienceStats = [];

    public array $pushStatus = [];

    public array $lastResult = [];

    public static function canAccess(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->audienceOptions = $this->availableAudienceOptions();

        if (!array_key_exists($this->audience, $this->audienceOptions)) {
            $this->audience = 'clients';
        }

        $this->refreshStats();
    }

    public function updatedAudience(): void
    {
        $this->refreshStats();
    }

    public function dispatchNotification(): void
    {
        $this->validate([
            'audience' => ['required', Rule::in(array_keys($this->audienceOptions))],
            'notificationTitle' => ['required', 'string', 'max:140'],
            'notificationMessage' => ['required', 'string', 'max:5000'],
            'targetScreen' => ['nullable', 'string', 'max:100'],
        ]);

        $title = trim($this->notificationTitle);
        $message = trim($this->notificationMessage);
        $screen = trim($this->targetScreen) !== '' ? trim($this->targetScreen) : 'home';
        $audience = $this->audience;
        $appVariants = $this->audienceVariants($audience);

        $summary = [
            'recipients' => 0,
            'database_sent' => 0,
            'push_tokens' => 0,
            'push_attempted' => 0,
            'push_sent' => 0,
            'push_failed' => 0,
            'push_invalidated' => 0,
        ];

        $this->audienceQuery($audience)
            ->select('users.id')
            ->orderBy('users.id')
            ->chunkById(300, function ($users) use (&$summary, $title, $message, $screen, $audience, $appVariants): void {
                $userIds = collect($users)
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values();

                if ($userIds->isEmpty()) {
                    return;
                }

                $result = app(AppNotificationService::class)->sendToUsers(
                    $userIds,
                    'admin_manual',
                    $title,
                    $message,
                    [
                        'type' => 'admin_manual',
                        'source' => 'admin_app_notification',
                        'audience' => $audience,
                        'app_variants' => $appVariants,
                        'target_screen' => $screen,
                    ],
                    true
                );

                $summary['recipients'] += (int) ($result['users'] ?? 0);
                $summary['database_sent'] += (int) ($result['database_sent'] ?? 0);
                $summary['push_tokens'] += (int) data_get($result, 'push.tokens', 0);
                $summary['push_attempted'] += (int) data_get($result, 'push.attempted', 0);
                $summary['push_sent'] += (int) data_get($result, 'push.sent', 0);
                $summary['push_failed'] += (int) data_get($result, 'push.failed', 0);
                $summary['push_invalidated'] += (int) data_get($result, 'push.invalidated', 0);
            }, 'id');

        $this->lastResult = $summary;

        Notification::make()
            ->title('Notification imetumwa')
            ->body('Imetumwa kwa ' . number_format((int) $summary['recipients']) . ' watumiaji.')
            ->success()
            ->send();

        $this->notificationMessage = '';
        $this->refreshStats();
    }

    private function refreshStats(): void
    {
        $query = $this->audienceQuery($this->audience);
        $total = (int) (clone $query)->count('users.id');
        $variants = $this->audienceVariants($this->audience);
        $pushRegistered = (int) (clone $query)
            ->whereHas('devicePushTokens', function (Builder $builder) use ($variants): Builder {
                return $builder
                    ->where('is_active', true)
                    ->when(!empty($variants), function (Builder $tokenQuery) use ($variants): Builder {
                        return $tokenQuery->where(function (Builder $variantQuery) use ($variants): void {
                            $variantQuery->whereIn('app_variant', $variants)
                                ->orWhereNull('app_variant');
                        });
                    });
            })
            ->count('users.id');

        $this->audienceStats = [
            'total' => $total,
            'push_registered' => $pushRegistered,
            'without_push' => max(0, $total - $pushRegistered),
        ];

        $this->pushStatus = app(PushNotificationService::class)->status();
    }

    private function audienceQuery(string $audience): Builder
    {
        return match ($audience) {
            'providers' => User::query()->where('role', 'provider'),
            'all' => User::query()->whereIn('role', ['client', 'provider']),
            default => User::query()->where('role', 'client'),
        };
    }

    private function availableAudienceOptions(): array
    {
        $definitions = AppVariant::definitions();

        return [
            'clients' => 'Glamo - Mteja (' . data_get($definitions, AppVariant::CLIENT . '.package_id', 'com.beautful.link') . ')',
            'providers' => 'Glamo Pro - Mtoa Huduma (' . data_get($definitions, AppVariant::PROVIDER . '.package_id', 'com.glamopro.link') . ')',
            'all' => 'App Zote (Glamo + Glamo Pro)',
        ];
    }

    private function audienceVariants(string $audience): array
    {
        return match ($audience) {
            'providers' => [AppVariant::PROVIDER],
            'all' => [AppVariant::CLIENT, AppVariant::PROVIDER],
            default => [AppVariant::CLIENT],
        };
    }
}
