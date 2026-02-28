<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AppDatabaseNotification;
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

        $result['push'] = $this->push->sendToUsers(
            $ids,
            (string) ($payload['title'] ?? $title),
            (string) ($payload['message'] ?? $message),
            $payload
        );

        return $result;
    }
}
