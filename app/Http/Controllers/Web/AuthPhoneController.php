<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Mail\WebOtpMail;
use App\Models\User;
use App\Services\BeemOtp;
use App\Services\WelcomeNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthPhoneController extends Controller
{
    public function showPhone()
    {
        return view('auth.phone');
    }

    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'channel' => ['required', 'in:phone,email'],
            'intent' => ['required', 'in:login,register'],
            'role' => ['nullable', 'in:client,provider'],
            'country_code' => ['nullable', 'string', 'max:4'],
            'phone_local' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $channel = $data['channel'];
        $intent = $data['intent'];

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

        $cacheKey = $this->otpCacheKey($channel, $destination);

        $sent = false;
        if ($channel === 'phone') {
            $hasBeemCreds = (bool) (config('beem.api_key') && config('beem.secret_key'));

            if (!$hasBeemCreds) {
                $sent = false;
            } else {
                $pinId = app(BeemOtp::class)->requestPin($destination);
                if ($pinId) {
                    $ttl = app(BeemOtp::class)->ttlMinutes();
                    Cache::put($cacheKey, $pinId, now()->addMinutes($ttl));
                    $sent = true;
                }
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

        $role = $intent === 'register' ? (string) ($data['role'] ?? 'client') : null;

        // Keep destination in session for verify page
        session([
            'otp_channel' => $channel,
            'otp_dest' => $destination,
            'otp_intent' => $intent,
            'otp_role' => $role,
        ]);

        return redirect()->route('auth.verify');
    }

    public function showVerify()
    {
        $channel = session('otp_channel');
        $dest = session('otp_dest');
        abort_unless($channel && $dest, 403);

        $destination = $channel === 'email'
            ? $this->maskEmail((string) $dest)
            : $this->maskPhone((string) $dest);

        return view('auth.verify', compact('destination', 'channel'));
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $channel = session('otp_channel');
        $dest = session('otp_dest');
        $intent = session('otp_intent', 'login');
        $role = session('otp_role', 'client');
        abort_unless($channel && $dest, 403);

        $cacheKey = $this->otpCacheKey((string) $channel, (string) $dest);
        $cached = Cache::get($cacheKey);

        $otp = (string) $data['otp'];
        $isValid = false;

        if ($channel === 'phone') {
            if ($cached) {
                $cachedStr = (string) $cached;
                $isDevOtp = ctype_digit($cachedStr) && strlen($cachedStr) === 6;

                $isValid = $isDevOtp
                    ? hash_equals($cachedStr, $otp)
                    : app(BeemOtp::class)->verifyPin($cachedStr, $otp);
            }
        } else {
            $isValid = $cached && hash_equals((string) $cached, $otp);
        }

        if (!$isValid) {
            return back()->withErrors(['otp' => 'OTP si sahihi au muda umeisha.'])->withInput();
        }

        Cache::forget($cacheKey);
        session()->forget(['otp_channel', 'otp_dest', 'otp_intent', 'otp_role']);

        // create or fetch user (OTP-based)
        $user = null;
        $shouldSendWelcome = false;
        if ($channel === 'phone') {
            $user = User::firstOrCreate(['phone' => (string) $dest]);
        } else {
            $user = User::firstOrCreate(['email' => (string) $dest]);
            if (!$user->email_verified_at) {
                $user->email_verified_at = now();
            }
        }

        if ($intent === 'register' && $user->wasRecentlyCreated) {
            $shouldSendWelcome = true;
        }

        if ($intent === 'register' && in_array($role, ['client', 'provider'], true) && !$user->role) {
            $user->role = $role;
        }

        $user->otp_verified_at = now();
        $user->save();

        Auth::login($user);
        $user->loadMissing('provider');

        if ($shouldSendWelcome) {
            app(WelcomeNotifier::class)->sendForNewUser($user);
        }

        if ($user->isApprovedActiveProvider()) {
            if ((string) ($user->role ?? '') !== 'provider') {
                $user->forceFill(['role' => 'provider'])->save();
            }

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

    private function normalizePhone(string $countryCode, string $localNumber, string $rawPhone = ''): ?string
    {
        $rawPhone = trim((string) $rawPhone);
        if ($rawPhone !== '') {
            $digits = preg_replace('/\D+/', '', $rawPhone);
            if ($digits === '') {
                return null;
            }
            return $this->normalizePhoneDigits($digits);
        }

        $cc = preg_replace('/\D+/', '', (string) $countryCode);
        $local = preg_replace('/\D+/', '', (string) $localNumber);

        if ($cc === '' || $local === '') {
            return null;
        }

        // common UX: user types 07XXXXXXXX -> remove leading 0
        $local = ltrim($local, '0');
        if ($local === '') {
            return null;
        }

        $digits = $cc.$local;
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
