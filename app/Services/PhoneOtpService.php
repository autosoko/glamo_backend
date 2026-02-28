<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PhoneOtpService
{
    public function __construct(
        private readonly BeemOtp $beemOtp,
        private readonly BeemSms $beemSms,
    ) {
    }

    public function issue(string $phone, array $context = []): array
    {
        $phone = preg_replace('/\D+/', '', $phone ?? '');
        $hasBeemCreds = $this->hasBeemCredentials();

        if ($hasBeemCreds) {
            $pinId = $this->beemOtp->requestPin($phone);
            if (is_string($pinId) && $pinId !== '') {
                return [
                    'ok' => true,
                    'provider' => 'beem',
                    'value' => $pinId,
                    'ttl_seconds' => max(60, $this->beemOtp->ttlMinutes() * 60),
                    'debug_otp' => null,
                ];
            }
        }

        $localOtp = (string) random_int(100000, 999999);

        if ($hasBeemCreds && $this->beemSms->sendOtp($phone, $localOtp)) {
            Log::info('Phone OTP sent via Beem SMS fallback', $this->logContext(
                $phone,
                $context,
                ['reason' => 'beem_request_failed']
            ));

            return [
                'ok' => true,
                'provider' => 'local',
                'value' => $localOtp,
                'ttl_seconds' => 300,
                'debug_otp' => config('app.debug') ? $localOtp : null,
            ];
        }

        if ($this->canUseLocalFallback()) {
            Log::info('Phone OTP generated (local fallback)', $this->logContext(
                $phone,
                $context,
                [
                    'reason' => $hasBeemCreds ? 'beem_request_failed' : 'missing_beem_credentials',
                    'otp' => $localOtp,
                ]
            ));

            return [
                'ok' => true,
                'provider' => 'local',
                'value' => $localOtp,
                'ttl_seconds' => 300,
                'debug_otp' => config('app.debug') ? $localOtp : null,
            ];
        }

        Log::warning('Phone OTP delivery failed', $this->logContext(
            $phone,
            $context,
            ['reason' => $hasBeemCreds ? 'beem_request_and_sms_failed' : 'missing_beem_credentials']
        ));

        return [
            'ok' => false,
            'error' => $hasBeemCreds ? 'delivery_failed' : 'missing_credentials',
        ];
    }

    private function canUseLocalFallback(): bool
    {
        return (bool) config('app.debug') || app()->environment(['local', 'testing']);
    }

    private function hasBeemCredentials(): bool
    {
        $apiKey = trim((string) config('beem.api_key'), " \t\n\r\0\x0B\"'");
        $secretKey = trim((string) config('beem.secret_key'), " \t\n\r\0\x0B\"'");

        return $apiKey !== '' && $secretKey !== '';
    }

    private function logContext(string $phone, array $context, array $extra = []): array
    {
        return array_merge($context, [
            'phone' => $this->maskPhone($phone),
        ], $extra);
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
}
