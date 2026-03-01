<?php

namespace App\Services;

use App\Models\DevicePushToken;
use App\Support\AppVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function status(): array
    {
        $enabled = (bool) config('push_notifications.enabled', true);
        $provider = (string) config('push_notifications.provider', 'fcm_legacy');
        $hasServerKey = trim((string) config('push_notifications.fcm_legacy.server_key', '')) !== '';
        $hasProjectId = trim((string) config('push_notifications.fcm_v1.project_id', '')) !== '';
        $hasServiceAccount = $this->hasFcmV1CredentialsConfigured();

        $configured = false;
        if ($enabled) {
            if ($provider === 'fcm_legacy') {
                $configured = $hasServerKey;
            } elseif ($provider === 'fcm_v1') {
                $configured = $hasProjectId && $hasServiceAccount;
            }
        }

        return [
            'enabled' => $enabled,
            'provider' => $provider,
            'configured' => $configured,
            'has_server_key' => $hasServerKey,
            'has_project_id' => $hasProjectId,
            'has_service_account' => $hasServiceAccount,
        ];
    }

    public function sendToUsers(
        array|Collection $userIds,
        string $title,
        string $message,
        array $data = [],
        array $options = []
    ): array
    {
        $ids = collect($userIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        $status = $this->status();

        $result = [
            'configured' => (bool) ($status['configured'] ?? false),
            'provider' => (string) ($status['provider'] ?? 'fcm_legacy'),
            'users' => $ids->count(),
            'tokens' => 0,
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'invalidated' => 0,
        ];

        if ($ids->isEmpty() || ! $result['configured']) {
            return $result;
        }

        $tokens = DevicePushToken::query()
            ->whereIn('user_id', $ids->all())
            ->where('is_active', true)
            ->when(!empty($this->resolvedVariants($data, $options)), function ($query) use ($data, $options) {
                $variants = $this->resolvedVariants($data, $options);

                $query->where(function ($builder) use ($variants): void {
                    $builder->whereIn('app_variant', $variants)
                        ->orWhereNull('app_variant');
                });
            })
            ->get(['id', 'token']);

        if ($tokens->isEmpty()) {
            return $result;
        }

        $result['tokens'] = $tokens->count();

        $provider = (string) ($result['provider'] ?? 'fcm_legacy');
        $chunkSize = $provider === 'fcm_v1'
            ? (int) config('push_notifications.fcm_v1.chunk_size', 100)
            : (int) config('push_notifications.fcm_legacy.chunk_size', 500);
        if ($chunkSize <= 0) {
            $chunkSize = $provider === 'fcm_v1' ? 100 : 500;
        }

        $safeData = $this->stringifyData(array_merge($data, [
            'title' => $title,
            'message' => $message,
        ]));

        foreach ($tokens->chunk($chunkSize) as $chunk) {
            $chunkResult = $provider === 'fcm_v1'
                ? $this->sendV1Chunk($chunk, $title, $message, $safeData)
                : $this->sendLegacyChunk($chunk, $title, $message, $safeData);
            $result['attempted'] += (int) ($chunkResult['attempted'] ?? 0);
            $result['sent'] += (int) ($chunkResult['sent'] ?? 0);
            $result['failed'] += (int) ($chunkResult['failed'] ?? 0);
            $result['invalidated'] += (int) ($chunkResult['invalidated'] ?? 0);
        }

        return $result;
    }

    private function resolvedVariants(array $data, array $options): array
    {
        return AppVariant::normalize(
            $options['app_variants']
            ?? $data['app_variants']
            ?? $data['app_variant']
            ?? null
        );
    }

    private function sendLegacyChunk(Collection $chunk, string $title, string $message, array $data): array
    {
        $attempted = $chunk->count();
        if ($attempted === 0) {
            return [
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'invalidated' => 0,
            ];
        }

        $tokenStrings = $chunk->pluck('token')->values()->all();
        $tokenIds = $chunk->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $endpoint = (string) config('push_notifications.fcm_legacy.endpoint', 'https://fcm.googleapis.com/fcm/send');
        $serverKey = trim((string) config('push_notifications.fcm_legacy.server_key', ''));
        $timeout = (int) config('push_notifications.fcm_legacy.timeout_seconds', 10);
        if ($timeout <= 0) {
            $timeout = 10;
        }

        $payload = [
            'registration_ids' => $tokenStrings,
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $message,
                'sound' => 'default',
            ],
            'data' => $data,
        ];

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'key=' . $serverKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            $this->markChunkFailure($tokenIds);

            Log::warning('Push send exception', [
                'error' => $e->getMessage(),
                'tokens' => $attempted,
            ]);

            return [
                'attempted' => $attempted,
                'sent' => 0,
                'failed' => $attempted,
                'invalidated' => 0,
            ];
        }

        if (! $response->successful()) {
            $this->markChunkFailure($tokenIds);

            Log::warning('Push send failed response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'tokens' => $attempted,
            ]);

            return [
                'attempted' => $attempted,
                'sent' => 0,
                'failed' => $attempted,
                'invalidated' => 0,
            ];
        }

        $json = $response->json();
        $results = is_array(data_get($json, 'results')) ? data_get($json, 'results') : [];

        $sent = 0;
        $failed = 0;
        $invalidatedIds = [];
        $sentIds = [];
        $failedIds = [];

        foreach ($results as $index => $entry) {
            $tokenId = (int) ($tokenIds[$index] ?? 0);
            $error = trim((string) data_get($entry, 'error', ''));

            if ($error === '') {
                $sent++;
                if ($tokenId > 0) {
                    $sentIds[] = $tokenId;
                }
                continue;
            }

            $failed++;
            if ($tokenId > 0) {
                $failedIds[] = $tokenId;
            }

            if ($this->isPermanentTokenError($error) && $tokenId > 0) {
                $invalidatedIds[] = $tokenId;
            }
        }

        if (count($results) < $attempted) {
            $missing = $attempted - count($results);
            $failed += $missing;
            $failedIds = array_merge($failedIds, array_slice($tokenIds, count($results)));
        }

        $this->markChunkSent($sentIds);
        $this->markChunkFailure($failedIds);
        $invalidated = $this->invalidateTokens($invalidatedIds);

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'invalidated' => $invalidated,
        ];
    }

    private function sendV1Chunk(Collection $chunk, string $title, string $message, array $data): array
    {
        $attempted = $chunk->count();
        if ($attempted === 0) {
            return [
                'attempted' => 0,
                'sent' => 0,
                'failed' => 0,
                'invalidated' => 0,
            ];
        }

        $projectId = trim((string) config('push_notifications.fcm_v1.project_id', ''));
        $accessToken = $this->fcmV1AccessToken();

        $tokenIds = $chunk
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($projectId === '' || $accessToken === null) {
            $this->markChunkFailure($tokenIds);

            Log::warning('Push v1 not ready', [
                'has_project_id' => $projectId !== '',
                'has_access_token' => $accessToken !== null,
                'tokens' => $attempted,
            ]);

            return [
                'attempted' => $attempted,
                'sent' => 0,
                'failed' => $attempted,
                'invalidated' => 0,
            ];
        }

        $endpoint = $this->fcmV1Endpoint($projectId);
        $sent = 0;
        $failed = 0;
        $sentIds = [];
        $failedIds = [];
        $invalidatedIds = [];
        $didRefreshAuth = false;

        foreach ($chunk as $entry) {
            $tokenId = (int) data_get($entry, 'id', 0);
            $deviceToken = trim((string) data_get($entry, 'token', ''));

            if ($tokenId <= 0 || $deviceToken === '') {
                $failed++;
                if ($tokenId > 0) {
                    $failedIds[] = $tokenId;
                }
                continue;
            }

            $send = $this->sendV1Single($endpoint, $accessToken, $deviceToken, $title, $message, $data);

            if ((bool) ($send['token_expired'] ?? false) && !$didRefreshAuth) {
                $refreshedToken = $this->fcmV1AccessToken(true);
                if ($refreshedToken !== null) {
                    $accessToken = $refreshedToken;
                    $didRefreshAuth = true;
                    $send = $this->sendV1Single($endpoint, $accessToken, $deviceToken, $title, $message, $data);
                }
            }

            if ((bool) ($send['sent'] ?? false)) {
                $sent++;
                $sentIds[] = $tokenId;
                continue;
            }

            $failed++;
            $failedIds[] = $tokenId;

            if ((bool) ($send['permanent_error'] ?? false)) {
                $invalidatedIds[] = $tokenId;
            }
        }

        $this->markChunkSent($sentIds);
        $this->markChunkFailure($failedIds);
        $invalidated = $this->invalidateTokens($invalidatedIds);

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
            'invalidated' => $invalidated,
        ];
    }

    private function sendV1Single(
        string $endpoint,
        string $accessToken,
        string $deviceToken,
        string $title,
        string $message,
        array $data
    ): array {
        $timeout = (int) config('push_notifications.fcm_v1.timeout_seconds', 10);
        if ($timeout <= 0) {
            $timeout = 10;
        }

        $payload = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout($timeout)
                ->withToken($accessToken)
                ->acceptJson()
                ->post($endpoint, $payload);
        } catch (\Throwable $e) {
            Log::warning('Push v1 send exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'sent' => false,
                'permanent_error' => false,
                'token_expired' => false,
            ];
        }

        if ($response->successful()) {
            return [
                'sent' => true,
                'permanent_error' => false,
                'token_expired' => false,
            ];
        }

        $json = $response->json();
        $status = strtoupper(trim((string) data_get($json, 'error.status', '')));
        $errorMessage = trim((string) data_get($json, 'error.message', ''));
        $tokenExpired = $response->status() === 401 || $status === 'UNAUTHENTICATED';

        Log::warning('Push v1 send failed response', [
            'http_status' => $response->status(),
            'error_status' => $status,
            'error_message' => $errorMessage,
        ]);

        return [
            'sent' => false,
            'permanent_error' => $this->isPermanentV1TokenError($status, $errorMessage),
            'token_expired' => $tokenExpired,
        ];
    }

    private function fcmV1Endpoint(string $projectId): string
    {
        $template = trim((string) config('push_notifications.fcm_v1.endpoint_template', ''));
        if ($template === '') {
            $template = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
        }

        return str_replace('{project_id}', $projectId, $template);
    }

    private function fcmV1AccessToken(bool $forceRefresh = false): ?string
    {
        $credentials = $this->loadFcmV1Credentials();
        if ($credentials === null) {
            return null;
        }

        $clientEmail = trim((string) ($credentials['client_email'] ?? ''));
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $tokenUri = trim((string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token'));
        $scope = trim((string) config('push_notifications.fcm_v1.scope', 'https://www.googleapis.com/auth/firebase.messaging'));

        if ($clientEmail === '' || $privateKey === '' || $tokenUri === '' || $scope === '') {
            Log::warning('Push v1 credentials are incomplete');
            return null;
        }

        $cacheKey = 'push:fcmv1:token:' . sha1($clientEmail . '|' . $tokenUri . '|' . $scope);
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        } else {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && trim($cached) !== '') {
                return trim($cached);
            }
        }

        $assertion = $this->buildServiceAccountJwt($clientEmail, $privateKey, $tokenUri, $scope);
        if ($assertion === null) {
            return null;
        }

        $timeout = (int) config('push_notifications.fcm_v1.timeout_seconds', 10);
        if ($timeout <= 0) {
            $timeout = 10;
        }

        try {
            $response = Http::timeout($timeout)
                ->asForm()
                ->post($tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Push v1 token request exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Push v1 token request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $accessToken = trim((string) data_get($response->json(), 'access_token', ''));
        $expiresIn = (int) data_get($response->json(), 'expires_in', 3600);
        if ($accessToken === '') {
            Log::warning('Push v1 token response missing access_token');
            return null;
        }

        $ttl = max(60, $expiresIn - 120);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));

        return $accessToken;
    }

    private function hasFcmV1CredentialsConfigured(): bool
    {
        $json = trim((string) config('push_notifications.fcm_v1.credentials_json', ''));
        if ($json !== '') {
            return true;
        }

        $path = trim((string) config('push_notifications.fcm_v1.credentials_path', ''));
        if ($path === '') {
            return false;
        }

        return is_file($path) && is_readable($path);
    }

    private function loadFcmV1Credentials(): ?array
    {
        $rawJson = trim((string) config('push_notifications.fcm_v1.credentials_json', ''));

        if ($rawJson === '') {
            $path = trim((string) config('push_notifications.fcm_v1.credentials_path', ''));
            if ($path === '') {
                Log::warning('Push v1 credentials path/json not configured');
                return null;
            }

            if (!is_file($path) || !is_readable($path)) {
                Log::warning('Push v1 credentials file not readable', [
                    'path' => $path,
                ]);
                return null;
            }

            $rawJson = (string) file_get_contents($path);
        }

        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded)) {
            Log::warning('Push v1 credentials JSON is invalid');
            return null;
        }

        $clientEmail = trim((string) ($decoded['client_email'] ?? ''));
        $privateKey = (string) ($decoded['private_key'] ?? '');
        $privateKey = str_replace('\n', "\n", $privateKey);
        $privateKey = str_replace(["\r\n", "\r"], "\n", $privateKey);
        $tokenUri = trim((string) ($decoded['token_uri'] ?? 'https://oauth2.googleapis.com/token'));

        if ($clientEmail === '' || trim($privateKey) === '' || $tokenUri === '') {
            Log::warning('Push v1 credentials missing required fields');
            return null;
        }

        return [
            'client_email' => $clientEmail,
            'private_key' => $privateKey,
            'token_uri' => $tokenUri,
        ];
    }

    private function buildServiceAccountJwt(string $clientEmail, string $privateKey, string $tokenUri, string $scope): ?string
    {
        $now = time();

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $clientEmail,
            'scope' => $scope,
            'aud' => $tokenUri,
            'iat' => $now - 30,
            'exp' => $now + 3300,
        ];

        $headerJson = json_encode($header, JSON_UNESCAPED_SLASHES);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($headerJson === false || $payloadJson === false) {
            return null;
        }

        $unsigned = $this->base64UrlEncode($headerJson) . '.' . $this->base64UrlEncode($payloadJson);
        $signature = '';
        $signed = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            Log::warning('Push v1 JWT signing failed');
            return null;
        }

        return $unsigned . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function markChunkSent(array $tokenIds): void
    {
        $tokenIds = array_values(array_unique(array_filter(array_map('intval', $tokenIds))));
        if (empty($tokenIds)) {
            return;
        }

        DevicePushToken::query()
            ->whereIn('id', $tokenIds)
            ->update([
                'last_sent_at' => now(),
                'fail_count' => 0,
                'updated_at' => now(),
            ]);
    }

    private function markChunkFailure(array $tokenIds): void
    {
        $tokenIds = array_values(array_unique(array_filter(array_map('intval', $tokenIds))));
        if (empty($tokenIds)) {
            return;
        }

        DevicePushToken::query()
            ->whereIn('id', $tokenIds)
            ->increment('fail_count');
    }

    private function invalidateTokens(array $tokenIds): int
    {
        $tokenIds = array_values(array_unique(array_filter(array_map('intval', $tokenIds))));
        if (empty($tokenIds)) {
            return 0;
        }

        return (int) DevicePushToken::query()
            ->whereIn('id', $tokenIds)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function stringifyData(array $data): array
    {
        $safe = [];
        foreach ($data as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = (string) ($value ?? '');
                continue;
            }

            $safe[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $safe;
    }

    private function isPermanentTokenError(string $error): bool
    {
        return in_array($error, [
            'NotRegistered',
            'InvalidRegistration',
            'MissingRegistration',
            'MismatchSenderId',
            'InvalidPackageName',
            'Unregistered',
        ], true);
    }

    private function isPermanentV1TokenError(string $status, string $message): bool
    {
        $status = strtoupper(trim($status));
        $message = strtolower(trim($message));

        if (in_array($status, ['UNREGISTERED', 'SENDER_ID_MISMATCH', 'NOT_FOUND'], true)) {
            return true;
        }

        if ($status === 'INVALID_ARGUMENT') {
            return str_contains($message, 'registration token')
                || str_contains($message, 'not a valid fcm registration token')
                || str_contains($message, 'requested entity was not found');
        }

        return false;
    }

}
