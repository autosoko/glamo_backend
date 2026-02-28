<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\BeemOtp;
use App\Services\PushNotificationService;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    use ApiResponse;

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $name = trim((string) $data['name']);
        if ($name === '') {
            return $this->fail('Jina halipaswi kuwa tupu.', 422);
        }

        $updates = ['name' => $name];

        if (array_key_exists('phone', $data)) {
            $rawPhone = trim((string) ($data['phone'] ?? ''));
            if ($rawPhone !== '') {
                $normalized = Phone::normalizeTzMsisdn($rawPhone);
                if ($normalized === null) {
                    return $this->fail('Namba ya simu si sahihi. Tumia 07XXXXXXXX au 2557XXXXXXXX.', 422);
                }

                if ((string) ($user->phone ?? '') !== $normalized) {
                    return $this->fail('Kubadili namba ya simu kunahitaji verification ya OTP kwanza.', 422, [
                        'phone' => ['Badilisha namba kupitia OTP verification flow.'],
                    ]);
                }

                $usedByOther = User::query()
                    ->where('phone', $normalized)
                    ->where('id', '!=', (int) $user->id)
                    ->exists();

                if ($usedByOther) {
                    return $this->fail('Namba ya simu tayari inatumika kwenye akaunti nyingine.', 422, [
                        'phone' => ['Namba tayari inatumika.'],
                    ]);
                }

                $updates['phone'] = $normalized;
            }
        }

        $user->forceFill($updates)->save();
        $user->loadMissing('provider');

        return $this->ok([
            'user' => $this->profilePayload($user),
        ], 'Profile imehifadhiwa.');
    }

    public function uploadProfileImage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'profile_image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['profile_image'];

        $user->loadMissing('provider');
        $newPath = '';
        $oldPath = '';

        if ($user->provider) {
            $oldPath = trim((string) ($user->provider->profile_image_path ?? ''));
            $newPath = (string) $file->store('providers/' . (int) $user->provider->id . '/profile', 'public');
            $user->provider->update([
                'profile_image_path' => $newPath,
            ]);
        } else {
            $oldPath = trim((string) ($user->profile_image_path ?? ''));
            $newPath = (string) $file->store('users/' . (int) $user->id . '/profile', 'public');
            $user->forceFill([
                'profile_image_path' => $newPath,
            ])->save();
        }

        if ($oldPath !== '' && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->refresh()->loadMissing('provider');

        return $this->ok([
            'user' => $this->profilePayload($user),
        ], 'Picha ya profile imehifadhiwa.');
    }

    public function requestPhoneChangeOtp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'phone' => ['required_without:phone_local', 'nullable', 'string', 'max:30'],
            'phone_local' => ['required_without:phone', 'nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'max:4'],
        ]);

        $normalized = $this->normalizePhoneChangeInput($data);
        if ($normalized === null) {
            return $this->fail('Namba ya simu si sahihi. Tumia 07XXXXXXXX au 2557XXXXXXXX.', 422);
        }

        if ((string) ($user->phone ?? '') === $normalized) {
            return $this->fail('Namba hii tayari ipo kwenye akaunti yako.', 422);
        }

        $usedByOther = User::query()
            ->where('phone', $normalized)
            ->where('id', '!=', (int) $user->id)
            ->exists();

        if ($usedByOther) {
            return $this->fail('Namba ya simu tayari inatumika kwenye akaunti nyingine.', 422, [
                'phone' => ['Namba tayari inatumika.'],
            ]);
        }

        $cacheKey = $this->phoneChangeOtpCacheKey((int) $user->id, $normalized);
        $ttlSeconds = 300;
        $otpProvider = 'local';
        $otpValue = null;
        $debugOtp = null;

        $hasBeemCreds = (bool) (config('beem.api_key') && config('beem.secret_key'));
        if ($hasBeemCreds) {
            $pinId = app(BeemOtp::class)->requestPin($normalized);
            if (!$pinId) {
                return $this->fail('Imeshindikana kutuma OTP ya simu. Jaribu tena.', 500);
            }

            $otpProvider = 'beem';
            $otpValue = $pinId;
            $ttlSeconds = max(60, (int) app(BeemOtp::class)->ttlMinutes() * 60);
        } else {
            if (!config('app.debug')) {
                return $this->fail('Imeshindikana kutuma OTP ya simu. Wasiliana na admin kuweka Beem credentials.', 500);
            }

            $otpProvider = 'local';
            $otpValue = (string) random_int(100000, 999999);
            $debugOtp = config('app.debug') ? $otpValue : null;

            Log::info('API phone-change OTP generated (local fallback)', [
                'user_id' => (int) $user->id,
                'phone' => $this->maskPhone($normalized),
                'otp' => $otpValue,
            ]);
        }

        Cache::put($cacheKey, [
            'provider' => $otpProvider,
            'value' => $otpValue,
            'user_id' => (int) $user->id,
            'phone' => $normalized,
            'old_phone' => (string) ($user->phone ?? ''),
        ], now()->addSeconds($ttlSeconds));

        return $this->ok([
            'destination_masked' => $this->maskPhone($normalized),
            'expires_in_seconds' => $ttlSeconds,
            'debug_otp' => $debugOtp,
        ], 'OTP imetumwa.');
    }

    public function verifyPhoneChangeOtp(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'phone' => ['required_without:phone_local', 'nullable', 'string', 'max:30'],
            'phone_local' => ['required_without:phone', 'nullable', 'string', 'max:30'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\\d{6}$/'],
        ]);

        $normalized = $this->normalizePhoneChangeInput($data);
        if ($normalized === null) {
            return $this->fail('Namba ya simu si sahihi. Tumia 07XXXXXXXX au 2557XXXXXXXX.', 422);
        }

        $cacheKey = $this->phoneChangeOtpCacheKey((int) $user->id, $normalized);
        $cached = Cache::get($cacheKey);
        if (!is_array($cached)) {
            return $this->fail('OTP imeisha muda au haipo. Omba tena OTP.', 422);
        }

        if ((int) ($cached['user_id'] ?? 0) !== (int) $user->id) {
            return $this->fail('OTP haifanani na akaunti hii.', 422);
        }

        $otp = (string) $data['otp'];
        $provider = (string) ($cached['provider'] ?? '');
        $otpValue = (string) ($cached['value'] ?? '');

        $isValid = false;
        if ($provider === 'beem') {
            $isValid = $otpValue !== '' && app(BeemOtp::class)->verifyPin($otpValue, $otp);
        } else {
            $isValid = $otpValue !== '' && hash_equals($otpValue, $otp);
        }

        if (!$isValid) {
            return $this->fail('OTP si sahihi au muda umeisha.', 422);
        }

        $usedByOther = User::query()
            ->where('phone', $normalized)
            ->where('id', '!=', (int) $user->id)
            ->exists();

        if ($usedByOther) {
            return $this->fail('Namba ya simu tayari inatumika kwenye akaunti nyingine.', 422, [
                'phone' => ['Namba tayari inatumika.'],
            ]);
        }

        $oldPhone = (string) ($user->phone ?? '');
        $user->forceFill([
            'phone' => $normalized,
            'otp_verified_at' => now(),
        ])->save();

        $user->loadMissing('provider');
        if ($user->provider) {
            $providerPhone = (string) ($user->provider->phone_public ?? '');
            if ($providerPhone === '' || $providerPhone === $oldPhone) {
                $user->provider->forceFill([
                    'phone_public' => $normalized,
                ])->save();
            }
        }

        Cache::forget($cacheKey);

        $user->refresh()->loadMissing('provider');

        return $this->ok([
            'user' => $this->profilePayload($user),
        ], 'Namba ya simu imebadilishwa kikamilifu.');
    }

    public function updateLocation(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $user->update([
            'last_lat' => (float) $data['lat'],
            'last_lng' => (float) $data['lng'],
            'last_location_at' => now(),
        ]);

        if ($user->provider) {
            $user->provider->update([
                'current_lat' => (float) $data['lat'],
                'current_lng' => (float) $data['lng'],
                'last_location_at' => now(),
            ]);
        }

        return $this->ok([
            'last_lat' => (float) $user->last_lat,
            'last_lng' => (float) $user->last_lng,
            'last_location_at' => optional($user->last_location_at)->toIso8601String(),
        ], 'Location imehifadhiwa.');
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $limit = max(1, min(100, (int) $request->query('limit', 50)));
        $notifications = $this->notificationsQueryForUser($user)
            ->limit($limit)
            ->get();

        return $this->ok([
            'notifications' => $notifications->map(function ($item): array {
                return [
                    'id' => (string) $item->id,
                    'type' => (string) $item->type,
                    'data' => (array) ($item->data ?? []),
                    'read_at' => optional($item->read_at)->toIso8601String(),
                    'created_at' => optional($item->created_at)->toIso8601String(),
                ];
            })->values()->all(),
        ]);
    }

    public function markNotificationRead(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $notification = $this->notificationsQueryForUser($user)
            ->where('id', $id)
            ->first();
        if (!$notification) {
            return $this->fail('Notification haipo.', 404);
        }

        $notification->markAsRead();

        return $this->ok([], 'Notification imesomwa.');
    }

    public function registerPushToken(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'in:android,ios,web'],
            'app_variant' => ['nullable', 'string', 'max:40'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $token = trim((string) $data['token']);
        if ($token === '') {
            return $this->fail('Token haipaswi kuwa tupu.', 422);
        }

        $tokenHash = hash('sha256', $token);

        $record = DevicePushToken::query()->firstOrNew([
            'token_hash' => $tokenHash,
        ]);

        $record->fill([
            'user_id' => (int) $user->id,
            'token' => $token,
            'platform' => (string) $data['platform'],
            'app_variant' => isset($data['app_variant']) ? trim((string) $data['app_variant']) : null,
            'device_id' => isset($data['device_id']) ? trim((string) $data['device_id']) : null,
            'is_active' => true,
            'last_seen_at' => now(),
            'updated_at' => now(),
        ]);

        if (!$record->exists) {
            $record->fail_count = 0;
            $record->last_sent_at = null;
        }

        $record->save();

        return $this->ok([
            'token_id' => (int) $record->id,
            'platform' => (string) $record->platform,
            'app_variant' => $record->app_variant ?: null,
            'device_id' => $record->device_id ?: null,
            'is_active' => (bool) $record->is_active,
            'last_seen_at' => optional($record->last_seen_at)->toIso8601String(),
            'push_config' => app(PushNotificationService::class)->status(),
        ], 'Push token imehifadhiwa.');
    }

    public function revokePushToken(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $token = trim((string) $data['token']);
        if ($token === '') {
            return $this->fail('Token haipaswi kuwa tupu.', 422);
        }

        $tokenHash = hash('sha256', $token);

        $updated = DevicePushToken::query()
            ->where('user_id', (int) $user->id)
            ->where('token_hash', $tokenHash)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);

        return $this->ok([
            'revoked' => $updated > 0,
        ], $updated > 0 ? 'Push token imeondolewa.' : 'Token haikupatikana kwa account hii.');
    }

    private function profilePayload(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'email' => $user->email ?: null,
            'phone' => $user->phone ?: null,
            'role' => (string) ($user->role ?? 'client'),
            'profile_image_url' => (string) ($user->profile_image_url ?? ''),
            'provider' => $user->provider ? [
                'id' => (int) $user->provider->id,
                'display_name' => (string) ($user->provider->display_name ?? ''),
                'profile_image_url' => (string) ($user->provider->profile_image_url ?? ''),
            ] : null,
        ];
    }

    private function normalizePhoneChangeInput(array $data): ?string
    {
        $rawPhone = trim((string) ($data['phone'] ?? ''));
        if ($rawPhone === '') {
            $rawPhone = trim((string) ($data['phone_local'] ?? ''));
        }

        if ($rawPhone === '') {
            return null;
        }

        return Phone::normalizeTzMsisdn($rawPhone);
    }

    private function phoneChangeOtpCacheKey(int $userId, string $phone): string
    {
        $digits = preg_replace('/\\D+/', '', (string) $phone);

        return 'phone-change-otp:' . $userId . ':' . $digits;
    }

    private function notificationsQueryForUser(User $user): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', get_class($user))
            ->where('notifiable_id', (int) $user->id)
            ->latest('created_at');
    }

    private function maskPhone(string $digits): string
    {
        $digits = preg_replace('/\\D+/', '', $digits);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 4) {
            return '+' . $digits;
        }

        $last2 = substr($digits, -2);
        $start = substr($digits, 0, min(3, strlen($digits)));
        return '+' . $start . '******' . $last2;
    }
}
