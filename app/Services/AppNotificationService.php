<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use App\Support\AppVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AppNotificationService
{
    public function __construct(private readonly PushNotificationService $push)
    {
    }

    public function sendToUsers(
        array|Collection $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
        bool $storeInDatabase = true
    ): array {
        $ids = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $payload = array_merge([
            'type' => trim($type),
            'title' => trim($title),
            'message' => trim($message),
        ], $data);

        $result = [
            'users' => $ids->count(),
            'database_sent' => 0,
            'push' => [
                'configured' => false,
                'users' => $ids->count(),
                'tokens' => 0,
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'invalidated' => 0,
            ],
        ];

        if ($ids->isEmpty()) {
            return $result;
        }

        if ($storeInDatabase) {
            try {
                $existingUserIds = User::query()
                    ->whereIn('id', $ids->all())
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($existingUserIds->isNotEmpty()) {
                    $now = now();
                    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($json === false) {
                        $json = '{}';
                    }

                    $rows = $existingUserIds
                        ->map(function (int $userId) use ($now, $json): array {
                            return [
                                'id' => (string) Str::uuid(),
                                'type' => AppDatabaseNotification::class,
                                'notifiable_type' => User::class,
                                'notifiable_id' => $userId,
                                'data' => $json,
                                'read_at' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        })
                        ->values()
                        ->all();

                    if (!empty($rows)) {
                        DB::table('notifications')->insert($rows);
                        $result['database_sent'] = count($rows);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Database notification store failed', [
                    'users' => $ids->count(),
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result['push'] = $this->dispatchPushNotifications(
            $ids,
            (string) ($payload['title'] ?? $title),
            (string) ($payload['message'] ?? $message),
            $payload
        );

        return $result;
    }

    private function dispatchPushNotifications(Collection $ids, string $title, string $message, array $payload): array
    {
        $explicitVariants = AppVariant::normalize($payload['app_variants'] ?? $payload['app_variant'] ?? null);

        if (!empty($explicitVariants)) {
            return $this->push->sendToUsers($ids, $title, $message, $payload, [
                'app_variants' => $explicitVariants,
            ]);
        }

        $users = User::query()
            ->whereIn('id', $ids->all())
            ->get(['id', 'role']);

        $targets = [];
        $assignedIds = [];

        foreach ($users as $user) {
            $variant = AppVariant::fromRole((string) ($user->role ?? ''));
            $userId = (int) $user->id;

            if ($variant === null || $userId <= 0) {
                continue;
            }

            $targets[$variant] ??= [];
            $targets[$variant][] = $userId;
            $assignedIds[] = $userId;
        }

        $fallbackIds = $ids
            ->diff(collect($assignedIds)->values())
            ->values()
            ->all();

        $aggregate = [
            'configured' => false,
            'provider' => (string) config('push_notifications.provider', 'fcm_legacy'),
            'users' => $ids->count(),
            'tokens' => 0,
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'invalidated' => 0,
        ];

        foreach ($targets as $variant => $userIds) {
            $pushResult = $this->push->sendToUsers($userIds, $title, $message, $payload, [
                'app_variants' => [$variant],
            ]);
            $aggregate = $this->mergePushResult($aggregate, $pushResult);
        }

        if (!empty($fallbackIds)) {
            $pushResult = $this->push->sendToUsers($fallbackIds, $title, $message, $payload);
            $aggregate = $this->mergePushResult($aggregate, $pushResult);
        }

        return $aggregate;
    }

    private function mergePushResult(array $aggregate, array $result): array
    {
        $aggregate['configured'] = (bool) ($aggregate['configured'] || ($result['configured'] ?? false));
        $aggregate['tokens'] += (int) ($result['tokens'] ?? 0);
        $aggregate['attempted'] += (int) ($result['attempted'] ?? 0);
        $aggregate['sent'] += (int) ($result['sent'] ?? 0);
        $aggregate['failed'] += (int) ($result['failed'] ?? 0);
        $aggregate['invalidated'] += (int) ($result['invalidated'] ?? 0);

        if (!empty($result['provider'])) {
            $aggregate['provider'] = (string) $result['provider'];
        }

        return $aggregate;
    }
}
