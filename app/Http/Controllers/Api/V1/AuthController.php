<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Mail\WebOtpMail;
use App\Models\Provider;
use App\Models\User;
use App\Services\BeemOtp;
use App\Services\PhoneOtpService;
use App\Services\WelcomeNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function requestOtp(Request $request)
    {
        $data = $request->validate([
            'intent' => ['required', 'in:register,reset'],
            'channel' => ['required', 'in:phone,email'],
            'role' => ['nullable', 'in:client,provider'],
            'country_code' => ['required_if:channel,phone', 'nullable', 'string', 'max:4'],
            'phone_local' => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
        ]);

        $channel = (string) $data['channel'];
        $intent = (string) $data['intent'];
        $role = (string) ($data['role'] ?? 'client');

        $destination = $this->resolveDestination($channel, $data);
        if ($destination === null) {
            return $this->fail(
                $channel === 'phone' ? 'Weka namba sahihi ya simu.' : 'Weka email sahihi.',
                422
            );
        }

        Log::info('API auth OTP request received', [
            'intent' => $intent,
            'channel' => $channel,
            'destination' => $channel === 'phone'
                ? $this->maskPhone($destination)
                : $this->maskEmail($destination),
            'role' => $role,
        ]);

        $existing = $channel === 'phone'
            ? User::where('phone', $destination)->first()
            : User::where('email', $destination)->first();

        if ($intent === 'register' && $existing && $existing->password) {
            return $this->fail('Account tayari ipo. Tafadhali ingia.', 409);
        }

        if ($intent === 'reset' && !$existing) {
            return $this->fail('Account haipo. Tafadhali jisajili.', 404);
        }

        $cacheKey = $this->otpCacheKey($channel, $destination);
        $ttlSeconds = 300;
        $otpProvider = 'local';
        $otpValue = null;
        $debugOtp = null;

        if ($channel === 'phone') {
            $issued = app(PhoneOtpService::class)->issue($destination, [
                'intent' => $intent,
                'role' => $role,
                'flow' => 'api-auth-request',
            ]);

            if (!($issued['ok'] ?? false)) {
                if (($issued['error'] ?? '') === 'missing_credentials') {
                    return $this->fail('Imeshindikana kutuma OTP ya simu. Wasiliana na admin kuweka Beem credentials.', 500);
                }

                return $this->fail('Imeshindikana kutuma OTP ya simu. Jaribu tena.', 500);
            }

            $otpProvider = (string) ($issued['provider'] ?? 'local');
            $otpValue = (string) ($issued['value'] ?? '');
            $ttlSeconds = (int) ($issued['ttl_seconds'] ?? 300);
            $debugOtp = $issued['debug_otp'] ?? null;

            Log::info('API auth OTP request dispatched', [
                'intent' => $intent,
                'channel' => $channel,
                'destination' => $this->maskPhone($destination),
                'provider' => $otpProvider,
                'ttl_seconds' => $ttlSeconds,
            ]);
        } else {
            $otpProvider = 'email';
            $otpValue = (string) random_int(100000, 999999);
            $debugOtp = config('app.debug') ? $otpValue : null;

            if (!$this->sendEmailOtp($destination, $otpValue)) {
                return $this->fail('Imeshindikana kutuma OTP ya email. Jaribu tena.', 500);
            }
        }

        Cache::put($cacheKey, [
            'provider' => $otpProvider,
            'value' => $otpValue,
            'intent' => $intent,
            'role' => $role,
        ], now()->addSeconds($ttlSeconds));

        return $this->ok([
            'intent' => $intent,
            'channel' => $channel,
            'destination_masked' => $channel === 'phone'
                ? $this->maskPhone($destination)
                : $this->maskEmail($destination),
            'expires_in_seconds' => $ttlSeconds,
            'debug_otp' => $debugOtp,
        ], 'OTP imetumwa.');
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'intent' => ['required', 'in:register,reset'],
            'channel' => ['required', 'in:phone,email'],
            'role' => ['nullable', 'in:client,provider'],
            'country_code' => ['required_if:channel,phone', 'nullable', 'string', 'max:4'],
            'phone_local' => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'name' => ['required_if:intent,register', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = (string) $data['channel'];
        $intent = (string) $data['intent'];
        $destination = $this->resolveDestination($channel, $data);
        if ($destination === null) {
            return $this->fail(
                $channel === 'phone' ? 'Weka namba sahihi ya simu.' : 'Weka email sahihi.',
                422
            );
        }

        $cacheKey = $this->otpCacheKey($channel, $destination);
        $cached = Cache::get($cacheKey);
        if (!is_array($cached)) {
            return $this->fail('OTP imeisha muda au haipo. Omba tena OTP.', 422);
        }

        if ((string) ($cached['intent'] ?? '') !== $intent) {
            return $this->fail('OTP intent haifanani. Omba OTP mpya.', 422);
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

        $role = (string) ($cached['role'] ?? $data['role'] ?? 'client');
        if (!in_array($role, ['client', 'provider'], true)) {
            $role = 'client';
        }

        $shouldSendWelcome = false;

        try {
            $user = \DB::transaction(function () use ($channel, $destination, $intent, $role, $data, &$shouldSendWelcome) {
                if ($intent === 'register') {
                    $user = $channel === 'phone'
                        ? User::firstOrNew(['phone' => $destination])
                        : User::firstOrNew(['email' => $destination]);

                    $shouldSendWelcome = !$user->exists || empty($user->password);

                    if ($user->exists && $user->password) {
                        throw ValidationException::withMessages([
                            'account' => 'Account tayari ipo. Tafadhali ingia.',
                        ]);
                    }

                    $name = trim((string) ($data['name'] ?? ''));
                    if ($name === '') {
                        $name = $channel === 'email'
                            ? explode('@', $destination)[0]
                            : 'Mteja wa Glamo';
                    }

                    $user->name = $name;
                    if ($channel === 'email') {
                        $user->email = strtolower($destination);
                        $user->email_verified_at = now();
                    } else {
                        $user->phone = $destination;
                    }
                    $user->role = $role;
                } else {
                    $user = $channel === 'phone'
                        ? User::where('phone', $destination)->first()
                        : User::where('email', strtolower($destination))->first();

                    if (!$user) {
                        throw ValidationException::withMessages([
                            'account' => 'Account haipo. Tafadhali jisajili.',
                        ]);
                    }
                }

                $user->otp_verified_at = now();
                $user->password = Hash::make((string) $data['password']);
                $user->save();

                if ((string) ($user->role ?? '') === 'provider') {
                    Provider::query()->firstOrCreate(
                        ['user_id' => (int) $user->id],
                        [
                            'approval_status' => 'pending',
                            'phone_public' => (string) ($user->phone ?? ''),
                            'online_status' => 'offline',
                            'is_active' => true,
                        ]
                    );
                }

                return $user->fresh(['provider']);
            });
        } catch (ValidationException $e) {
            return $this->fail('Imeshindikana kukamilisha uthibitisho.', 422, $e->errors());
        }

        Cache::forget($cacheKey);

        if ($intent === 'register' && $shouldSendWelcome) {
            app(WelcomeNotifier::class)->sendForNewUser($user);
        }

        $deviceName = trim((string) ($data['device_name'] ?? 'flutterflow'));
        if ($deviceName === '') {
            $deviceName = 'flutterflow';
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Uthibitisho umekamilika.');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:phone,email'],
            'country_code' => ['required_if:channel,phone', 'nullable', 'string', 'max:4'],
            'phone_local' => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $channel = (string) $data['channel'];
        $destination = $this->resolveDestination($channel, $data);
        if ($destination === null) {
            return $this->fail(
                $channel === 'phone' ? 'Weka namba sahihi ya simu.' : 'Weka email sahihi.',
                422
            );
        }

        $user = $channel === 'phone'
            ? User::where('phone', $destination)->first()
            : User::where('email', strtolower($destination))->first();

        if (!$user || !$user->password || !Hash::check((string) $data['password'], (string) $user->password)) {
            return $this->fail('Credentials si sahihi.', 401);
        }

        $user->loadMissing('provider');
        if ($user->isApprovedActiveProvider() && (string) ($user->role ?? '') !== 'provider') {
            $user->forceFill(['role' => 'provider'])->save();
            $user->refresh();
            $user->loadMissing('provider');
        }

        $deviceName = trim((string) ($data['device_name'] ?? 'flutterflow'));
        if ($deviceName === '') {
            $deviceName = 'flutterflow';
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ], 'Umeingia kikamilifu.');
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $user->loadMissing('provider');

        return $this->ok([
            'user' => $this->userPayload($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $token = $user->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return $this->ok([], 'Umeondoka kikamilifu.');
    }

    private function userPayload(User $user): array
    {
        $provider = $user->provider;

        return [
            'id' => (int) $user->id,
            'name' => (string) ($user->name ?? ''),
            'email' => $user->email ?: null,
            'phone' => $user->phone ?: null,
            'role' => (string) ($user->role ?? 'client'),
            'profile_image_url' => (string) ($user->profile_image_url ?? ''),
            'otp_verified_at' => optional($user->otp_verified_at)?->toIso8601String(),
            'provider' => $provider ? [
                'id' => (int) $provider->id,
                'display_name' => (string) ($provider->display_name ?? ''),
                'approval_status' => (string) ($provider->approval_status ?? 'pending'),
                'online_status' => (string) ($provider->online_status ?? 'offline'),
                'is_active' => (bool) ($provider->is_active ?? false),
                'profile_image_url' => (string) ($provider->profile_image_url ?? ''),
            ] : null,
        ];
    }

    private function otpCacheKey(string $channel, string $destination): string
    {
        return 'apiotp:' . strtolower(trim($channel)) . ':' . strtolower(trim($destination));
    }

    private function resolveDestination(string $channel, array $data): ?string
    {
        if ($channel === 'phone') {
            return $this->normalizePhone(
                (string) ($data['country_code'] ?? '255'),
                (string) ($data['phone_local'] ?? ''),
                (string) ($data['phone'] ?? '')
            );
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        return $email !== '' ? $email : null;
    }

    private function normalizePhone(string $countryCode, string $localNumber, string $rawPhone = ''): ?string
    {
        $cc = preg_replace('/\D+/', '', (string) $countryCode);
        $cc = $cc !== '' ? $cc : '255';

        $rawPhone = trim((string) $rawPhone);
        $input = $rawPhone !== '' ? $rawPhone : (string) $localNumber;

        $digits = preg_replace('/\D+/', '', $input);
        if ($digits === '') {
            return null;
        }

        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($cc !== '' && str_starts_with($digits, $cc)) {
            // already international for selected country
        } elseif (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                return null;
            }
            $digits = $cc . $digits;
        } elseif (strlen($digits) <= 11) {
            $digits = $cc . $digits;
        }

        if ($cc !== '' && str_starts_with($digits, $cc . $cc)) {
            $digits = $cc . substr($digits, strlen($cc) * 2);
        }

        return $this->normalizePhoneDigits($digits);
    }

    private function normalizePhoneDigits(string $digits): ?string
    {
        $digits = preg_replace('/\D+/', '', $digits);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) < 9 || strlen($digits) > 15 || str_starts_with($digits, '0')) {
            return null;
        }

        if (str_starts_with($digits, '255') && !preg_match('/^255(6|7)\d{8}$/', $digits)) {
            return null;
        }

        return $digits;
    }

    private function maskPhone(string $digits): string
    {
        $digits = preg_replace('/\D+/', '', $digits);
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

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);
        $user = $user === '' ? '*' : substr($user, 0, 1) . '***';
        return $user . '@' . $domain;
    }

    private function sendEmailOtp(string $email, string $otp): bool
    {
        try {
            Mail::to($email)->send(new WebOtpMail($otp));
            return true;
        } catch (\Throwable $e) {
            Log::warning('API email OTP send failed', [
                'email' => $this->maskEmail($email),
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
