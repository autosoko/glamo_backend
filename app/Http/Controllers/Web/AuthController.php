<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\WebOtpMail;
use App\Models\User;
use App\Models\Provider;
use App\Models\Order;
use App\Services\BeemOtp;
use App\Services\PhoneOtpService;
use App\Services\WelcomeNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        $this->rememberIntendedUrl($request);

        return view('auth.login');
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
        ]);

        $channel = $data['channel'];

        $destination = null;
        if ($channel === 'phone') {
            $destination = $this->normalizePhone(
                (string) ($data['country_code'] ?? '255'),
                (string) ($data['phone_local'] ?? ''),
                (string) ($data['phone'] ?? '')
            );

            if ($destination === null) {
                return back()->withErrors(['phone_local' => 'Weka namba sahihi ya simu.'])->withInput();
            }
        } else {
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email === '') {
                return back()->withErrors(['email' => 'Weka email sahihi.'])->withInput();
            }
            $destination = $email;
        }

        $user = $channel === 'phone'
            ? User::where('phone', $destination)->first()
            : User::where('email', $destination)->first();

        if (!$user || !$user->password) {
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            return back()->withErrors([$field => 'Account haipo au bado hujaweka password. Tumia "Umesahau password" kuweka upya.'])->withInput();
        }

        if (!Hash::check((string) $data['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'Password si sahihi.'])->withInput();
        }

        Auth::login($user);
        $user->loadMissing('provider');

        if ($user->isApprovedActiveProvider()) {
            $this->ensureProviderRole($user);
            return redirect()->route('provider.dashboard');
        }

        if ((string) ($user->role ?? '') === 'client') {
            $active = Order::query()
                ->where('client_id', (int) $user->id)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->orderByDesc('id')
                ->first();
            if ($active) {
                return redirect()->route('orders.show', ['order' => $active->id]);
            }
        }

        $defaultRoute = ((string) ($user->role ?? '') === 'provider' || $user->hasProviderProfile())
            ? route('provider.dashboard')
            : route('landing');

        return redirect()->intended($defaultRoute);
    }

    public function showRegister(Request $request)
    {
        $this->rememberIntendedUrl($request);

        $as = (string) $request->query('as', '');
        $defaultRole = in_array($as, ['client', 'provider'], true) ? $as : 'client';

        if ($defaultRole === 'provider') {
            if ($request->user()) {
                return redirect()->route('provider.onboarding');
            }

            if (!(string) $request->query('redirect', '')) {
                session(['url.intended' => route('provider.onboarding')]);
            }
        }

        return view('auth.register', compact('defaultRole'));
    }

    public function sendRegisterOtp(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:phone,email'],
            'country_code' => ['required_if:channel,phone', 'nullable', 'string', 'max:4'],
            'phone_local' => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
        ]);

        $channel = $data['channel'];
        // Public signup always starts as a client account.
        $role = 'client';

        $destination = $this->resolveDestination($channel, $data);
        if ($destination === null) {
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            $msg = $channel === 'phone' ? 'Weka namba sahihi ya simu.' : 'Weka email sahihi.';
            return back()->withErrors([$field => $msg])->withInput();
        }

        $existing = $channel === 'phone'
            ? User::where('phone', $destination)->first()
            : User::where('email', $destination)->first();

        if ($existing && $existing->password) {
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            return back()->withErrors([$field => 'Account tayari ipo. Tafadhali ingia.'])->withInput();
        }

        return $this->startOtpFlow(
            intent: 'register',
            channel: $channel,
            destination: $destination,
            role: $role,
        );
    }

    public function showForgot(Request $request)
    {
        $this->rememberIntendedUrl($request);

        return view('auth.forgot');
    }

    public function sendResetOtp(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:phone,email'],
            'country_code' => ['required_if:channel,phone', 'nullable', 'string', 'max:4'],
            'phone_local' => ['required_if:channel,phone', 'nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:255'],
        ]);

        $channel = $data['channel'];

        $destination = $this->resolveDestination($channel, $data);
        if ($destination === null) {
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            $msg = $channel === 'phone' ? 'Weka namba sahihi ya simu.' : 'Weka email sahihi.';
            return back()->withErrors([$field => $msg])->withInput();
        }

        $existing = $channel === 'phone'
            ? User::where('phone', $destination)->first()
            : User::where('email', $destination)->first();

        if (!$existing) {
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            return back()->withErrors([$field => 'Account haipo. Tafadhali jisajili.'])->withInput();
        }

        return $this->startOtpFlow(
            intent: 'reset',
            channel: $channel,
            destination: $destination,
        );
    }

    public function showVerify()
    {
        $channel = session('otp_channel');
        $dest = session('otp_dest');
        $intent = session('otp_intent');
        abort_unless($channel && $dest && $intent, 403);

        $destination = $channel === 'email'
            ? $this->maskEmail((string) $dest)
            : $this->maskPhone((string) $dest);

        return view('auth.verify', compact('destination', 'channel', 'intent'));
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $channel = session('otp_channel');
        $dest = session('otp_dest');
        $intent = session('otp_intent');
        $role = session('otp_role');
        abort_unless($channel && $dest && $intent, 403);

        $cacheKey = $this->otpCacheKey((string) $channel, (string) $dest);
        $cached = Cache::get($cacheKey);

        $otp = (string) $data['otp'];
        $isValid = false;

        if ($channel === 'phone') {
            $cachedStr = trim((string) ($cached ?? ''));
            $isDevOtp = ctype_digit($cachedStr) && strlen($cachedStr) === 6;

            $isValid = $cachedStr !== '' && (
                $isDevOtp
                    ? hash_equals($cachedStr, $otp)
                    : app(BeemOtp::class)->verifyPin($cachedStr, $otp)
            );
        } else {
            $isValid = $cached && hash_equals((string) $cached, $otp);
        }

        if (!$isValid) {
            return back()->withErrors(['otp' => 'OTP si sahihi au muda umeisha.'])->withInput();
        }

        Cache::forget($cacheKey);
        session()->forget(['otp_channel', 'otp_dest', 'otp_intent', 'otp_role']);

        session([
            'pw_intent' => $intent,
            'pw_channel' => $channel,
            'pw_dest' => $dest,
            'pw_role' => $role,
        ]);

        return redirect()->route('password.set');
    }

    public function showSetPassword()
    {
        $intent = session('pw_intent');
        $channel = session('pw_channel');
        $dest = session('pw_dest');
        $role = session('pw_role');
        abort_unless($intent && $channel && $dest, 403);

        $destination = $channel === 'email'
            ? $this->maskEmail((string) $dest)
            : $this->maskPhone((string) $dest);

        $phoneOtpRequired = $this->requiresClientEmailPhoneVerification(
            (string) $intent,
            (string) $channel,
            is_string($role) ? $role : null,
        );

        $pendingPhone = trim((string) session('pw_phone_pending', ''));
        $verifiedPhone = trim((string) session('pw_phone_verified', ''));
        $phoneVerified = $verifiedPhone !== '' && $this->normalizePhoneDigits($verifiedPhone) !== null;
        $phoneDestination = $phoneVerified ? $verifiedPhone : $pendingPhone;
        $phoneDestinationMasked = $phoneDestination !== '' ? $this->maskPhone($phoneDestination) : null;

        return view('auth.password', compact(
            'destination',
            'channel',
            'intent',
            'phoneOtpRequired',
            'phoneVerified',
            'phoneDestinationMasked',
        ));
    }

    public function sendSetPasswordPhoneOtp(Request $request)
    {
        $intent = (string) session('pw_intent');
        $channel = (string) session('pw_channel');
        $dest = (string) session('pw_dest');
        $role = session('pw_role');
        abort_unless($intent && $channel && $dest, 403);

        if (!$this->requiresClientEmailPhoneVerification($intent, $channel, is_string($role) ? $role : null)) {
            return redirect()->route('password.set');
        }

        $data = $request->validate([
            'country_code' => ['required', 'string', 'max:4'],
            'phone_local' => ['required', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $phone = $this->normalizePhone(
            (string) ($data['country_code'] ?? '255'),
            (string) ($data['phone_local'] ?? ''),
            (string) ($data['phone'] ?? '')
        );

        if ($phone === null) {
            return back()->withErrors(['phone_local' => 'Weka namba sahihi ya simu.'])->withInput();
        }

        $emailUser = User::where('email', $dest)->first();
        $usedByOther = User::query()
            ->where('phone', $phone)
            ->when($emailUser, fn ($q) => $q->where('id', '!=', (int) $emailUser->id))
            ->exists();

        if ($usedByOther) {
            return back()->withErrors(['phone_local' => 'Namba hii tayari inatumika kwenye akaunti nyingine.'])->withInput();
        }

        $cacheKey = $this->setPasswordPhoneOtpCacheKey($phone);
        Cache::forget($cacheKey);

        $sent = false;
        $issued = app(PhoneOtpService::class)->issue($phone, [
            'flow' => 'web-set-password-phone-send',
            'intent' => $intent,
            'channel' => $channel,
        ]);

        if ($issued['ok'] ?? false) {
            Cache::put($cacheKey, (string) ($issued['value'] ?? ''), now()->addSeconds((int) ($issued['ttl_seconds'] ?? 300)));
            $sent = true;
        }

        if (!$sent) {
            Cache::forget($cacheKey);
            return back()->withErrors(['phone_local' => 'Imeshindikana kutuma OTP ya simu. Jaribu tena.'])->withInput();
        }

        session([
            'pw_phone_pending' => $phone,
            'pw_phone_verified' => null,
        ]);

        return redirect()->route('password.set')
            ->with('success', 'OTP ya simu imetumwa kwenye ' . $this->maskPhone($phone) . '.');
    }

    public function verifySetPasswordPhoneOtp(Request $request)
    {
        $intent = (string) session('pw_intent');
        $channel = (string) session('pw_channel');
        $role = session('pw_role');
        abort_unless($intent && $channel && session()->has('pw_dest'), 403);

        if (!$this->requiresClientEmailPhoneVerification($intent, $channel, is_string($role) ? $role : null)) {
            return redirect()->route('password.set');
        }

        $data = $request->validate([
            'phone_otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $pendingPhone = trim((string) session('pw_phone_pending', ''));
        $phone = $this->normalizePhoneDigits($pendingPhone);
        if ($phone === null) {
            return redirect()->route('password.set')
                ->withErrors(['phone_local' => 'Weka namba ya simu na utume OTP kwanza.']);
        }

        $cacheKey = $this->setPasswordPhoneOtpCacheKey($phone);
        $cached = Cache::get($cacheKey);
        $otp = (string) ($data['phone_otp'] ?? '');

        $isValid = false;
        if ($cached) {
            $cachedStr = (string) $cached;
            $isDevOtp = ctype_digit($cachedStr) && strlen($cachedStr) === 6;

            $isValid = $isDevOtp
                ? hash_equals($cachedStr, $otp)
                : app(BeemOtp::class)->verifyPin($cachedStr, $otp);
        }

        if (!$isValid) {
            return back()->withErrors(['phone_otp' => 'OTP ya simu si sahihi au muda umeisha.'])->withInput();
        }

        Cache::forget($cacheKey);
        session([
            'pw_phone_verified' => $phone,
            'pw_phone_pending' => $phone,
        ]);

        return redirect()->route('password.set')
            ->with('success', 'Namba ya simu imethibitishwa: ' . $this->maskPhone($phone) . '.');
    }

    public function storePassword(Request $request)
    {
        $intent = session('pw_intent');
        $channel = session('pw_channel');
        $dest = session('pw_dest');
        $role = session('pw_role');
        abort_unless($intent && $channel && $dest, 403);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:6', 'max:255', 'confirmed'],
        ]);

        $dest = (string) $dest;
        $channel = (string) $channel;
        $intent = (string) $intent;

        if (!in_array($intent, ['register', 'reset'], true)) {
            abort(403);
        }

        $requiresPhoneVerification = $this->requiresClientEmailPhoneVerification($intent, $channel, is_string($role) ? $role : null);
        $verifiedPhone = null;
        if ($requiresPhoneVerification) {
            $verifiedPhone = $this->normalizePhoneDigits((string) session('pw_phone_verified', ''));
            if ($verifiedPhone === null) {
                return back()->withErrors([
                    'phone_local' => 'Thibitisha namba ya simu kwa OTP kabla ya kuhifadhi nenosiri.',
                ])->withInput();
            }
        }

        $user = null;
        $shouldSendWelcome = false;

        if ($intent === 'register') {
            $user = $channel === 'phone'
                ? User::firstOrNew(['phone' => $dest])
                : User::firstOrNew(['email' => $dest]);

            $shouldSendWelcome = !$user->exists || empty($user->password);

            if ($user->exists && $user->password) {
                return redirect()->route('login')->withErrors([
                    'email' => 'Account tayari ipo. Tafadhali ingia.',
                ]);
            }

            if ($channel === 'email' && !$user->email_verified_at) {
                $user->email_verified_at = now();
            }

            if (in_array((string) $role, ['client', 'provider'], true)) {
                $user->role = (string) $role;
            }

            if ($verifiedPhone !== null) {
                $usedByOther = User::query()
                    ->where('phone', $verifiedPhone)
                    ->when($user->exists, fn ($q) => $q->where('id', '!=', (int) $user->id))
                    ->exists();

                if ($usedByOther) {
                    return back()->withErrors([
                        'phone_local' => 'Namba hii tayari inatumika kwenye akaunti nyingine.',
                    ])->withInput();
                }

                $user->phone = $verifiedPhone;
            }
        } else {
            $user = $channel === 'phone'
                ? User::where('phone', $dest)->first()
                : User::where('email', $dest)->first();

            if (!$user) {
                return redirect()->route('password.request')->withErrors([
                    'email' => 'Account haipo. Tafadhali jisajili.',
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

        session()->forget([
            'pw_intent',
            'pw_channel',
            'pw_dest',
            'pw_role',
            'pw_phone_pending',
            'pw_phone_verified',
        ]);

        Auth::login($user);
        $user->loadMissing('provider');

        if ($intent === 'register' && $shouldSendWelcome) {
            app(WelcomeNotifier::class)->sendForNewUser($user);
        }

        if ($user->isApprovedActiveProvider()) {
            $this->ensureProviderRole($user);
            return redirect()->route('provider.dashboard');
        }

        $defaultRoute = ((string) ($user->role ?? '') === 'provider' || $user->hasProviderProfile())
            ? route('provider.dashboard')
            : route('landing');

        return redirect()->intended($defaultRoute);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }

    private function otpCacheKey(string $channel, string $destination): string
    {
        $destination = strtolower(trim($destination));
        return "webotp:{$channel}:{$destination}";
    }

    private function setPasswordPhoneOtpCacheKey(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone));
        return "webotp:setpw:phone:{$phone}";
    }

    private function requiresClientEmailPhoneVerification(string $intent, string $channel, ?string $role = null): bool
    {
        return $intent === 'register'
            && $channel === 'email'
            && (string) ($role ?? 'client') === 'client';
    }

    private function ensureProviderRole(User $user): void
    {
        if ((string) ($user->role ?? '') !== 'provider') {
            $user->forceFill(['role' => 'provider'])->save();
        }
    }

    private function rememberIntendedUrl(Request $request): void
    {
        $redirect = (string) $request->query('redirect', '');
        if ($redirect === '') {
            return;
        }

        // Allow relative URLs only (avoid open redirects).
        if (str_starts_with($redirect, '/')) {
            session(['url.intended' => $redirect]);
            return;
        }

        // Allow absolute URLs only when they match our own host.
        $host = parse_url($redirect, PHP_URL_HOST);
        if (!$host) {
            return;
        }

        $allowedHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $request->getHost(),
        ]);

        foreach ($allowedHosts as $allowedHost) {
            if (strcasecmp((string) $allowedHost, (string) $host) !== 0) {
                continue;
            }

            $path = (string) (parse_url($redirect, PHP_URL_PATH) ?: '/');
            $query = (string) (parse_url($redirect, PHP_URL_QUERY) ?: '');
            $intended = $query !== '' ? "{$path}?{$query}" : $path;

            session(['url.intended' => $intended]);
            return;
        }
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

    private function startOtpFlow(string $intent, string $channel, string $destination, ?string $role = null)
    {
        $cacheKey = $this->otpCacheKey($channel, $destination);

        $sent = false;
        if ($channel === 'phone') {
            $issued = app(PhoneOtpService::class)->issue($destination, [
                'intent' => $intent,
                'role' => $role,
                'flow' => 'web-auth-start',
            ]);

            if ($issued['ok'] ?? false) {
                Cache::put($cacheKey, (string) ($issued['value'] ?? ''), now()->addSeconds((int) ($issued['ttl_seconds'] ?? 300)));
                $sent = true;
            }
        } else {
            $otp = (string) random_int(100000, 999999);
            Cache::put($cacheKey, $otp, now()->addMinutes(5));
            $sent = $this->sendEmailOtp($destination, $otp);
        }

        if (!$sent) {
            Cache::forget($cacheKey);
            $field = $channel === 'phone' ? 'phone_local' : 'email';
            return back()->withErrors([$field => 'Imeshindikana kutuma OTP. Jaribu tena.'])->withInput();
        }

        session([
            'otp_channel' => $channel,
            'otp_dest' => $destination,
            'otp_intent' => $intent,
            'otp_role' => $role,
        ]);

        return redirect()->route('otp.verify');
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

        // Strip the common international call prefix "00".
        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        // If the user pasted a full number that already includes the selected country code, keep it.
        if ($cc !== '' && str_starts_with($digits, $cc)) {
            // already international format for the selected country code
        } elseif (str_starts_with($digits, '0')) {
            // National format (e.g. 07XXXXXXXX): remove leading 0(s) then add country code.
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                return null;
            }
            $digits = $cc.$digits;
        } elseif (strlen($digits) <= 11) {
            // Likely a national number without leading 0 (e.g. 7XXXXXXXX): add country code.
            $digits = $cc.$digits;
        } else {
            // Assume it's already an international number (contains a country code).
        }

        // Avoid double prefix (e.g. 255 + 2557... => 2557...)
        if ($cc !== '' && str_starts_with($digits, $cc.$cc)) {
            $digits = $cc.substr($digits, strlen($cc) * 2);
        }

        return $this->normalizePhoneDigits($digits);
    }

    private function normalizePhoneDigits(string $digits): ?string
    {
        $digits = preg_replace('/\D+/', '', $digits);
        if ($digits === '') {
            return null;
        }

        // E.164 max is 15 digits; keep a reasonable range.
        if (strlen($digits) < 9 || strlen($digits) > 15) {
            return null;
        }

        // E.164 should not start with 0.
        if (str_starts_with($digits, '0')) {
            return null;
        }

        // Tanzania mobile numbers: 255 + (6|7) + 8 digits (example: 255784825785)
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
            return '+'.$digits;
        }

        $last2 = substr($digits, -2);
        $start = substr($digits, 0, min(3, strlen($digits)));
        return '+'.$start.'••••••'.$last2;
    }

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);
        $user = $user === '' ? '*' : substr($user, 0, 1).'***';
        return $user.'@'.$domain;
    }

    private function sendEmailOtp(string $email, string $otp): bool
    {
        try {
            Mail::to($email)->send(new WebOtpMail($otp));
            return true;
        } catch (\Throwable $e) {
            $error = (string) $e->getMessage();
            $hint = null;

            if (str_contains($error, 'Peer certificate CN')) {
                $hint = 'SMTP certificate mismatch. Hakikisha MAIL_HOST inaendana na certificate CN au tumia MAIL_VERIFY_PEER=false kwa muda.';
            }

            Log::warning('Email OTP send failed', [
                'message' => $error,
                'email' => $this->maskEmail($email),
                'mailer' => (string) config('mail.default'),
                'smtp_host' => (string) config('mail.mailers.smtp.host'),
                'smtp_port' => (int) config('mail.mailers.smtp.port'),
                'verify_peer' => (bool) config('mail.mailers.smtp.verify_peer', true),
                'hint' => $hint,
            ]);
            return false;
        }
    }
}
