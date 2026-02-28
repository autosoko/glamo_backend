<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemOtp
{
    public function requestPin(string $msisdn): ?string
    {
        $apiKey = $this->cleanConfigString(config('beem.api_key'));
        $secretKey = $this->cleanConfigString(config('beem.secret_key'));
        $appId = $this->cleanConfigString(config('beem.otp.app_id'));
        $requestUrl = trim((string) config('beem.otp.request_url'));

        if (!$apiKey || !$secretKey || !$appId || $requestUrl === '') {
            return null;
        }

        $msisdn = preg_replace('/\D+/', '', $msisdn ?? '');
        while (str_starts_with($msisdn, '00')) {
            $msisdn = substr($msisdn, 2);
        }
        if ($msisdn === '') {
            return null;
        }

        if (strlen($msisdn) < 9 || strlen($msisdn) > 15 || str_starts_with($msisdn, '0')) {
            Log::warning('Beem OTP request skipped (invalid msisdn)', [
                'msisdn' => $this->maskMsisdn($msisdn),
            ]);
            return null;
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withBasicAuth($apiKey, $secretKey)
                ->post($requestUrl, [
                    'appId' => $appId,
                    'msisdn' => $msisdn,
                ]);

            $pinId = data_get($response->json(), 'data.pinId');
            if ($response->successful() && is_string($pinId) && $pinId !== '') {
                return $pinId;
            }

            $json = $response->json();

            Log::warning('Beem OTP request failed', [
                'status' => $response->status(),
                'code' => data_get($json, 'data.message.code'),
                'message' => data_get($json, 'data.message.message'),
                'msisdn' => $this->maskMsisdn($msisdn),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Beem OTP request exception', [
                'message' => $e->getMessage(),
                'msisdn' => $this->maskMsisdn($msisdn ?? ''),
            ]);
        }

        return null;
    }

    public function verifyPin(string $pinId, string $pin): bool
    {
        $apiKey = $this->cleanConfigString(config('beem.api_key'));
        $secretKey = $this->cleanConfigString(config('beem.secret_key'));
        $verifyUrl = trim((string) config('beem.otp.verify_url'));

        $pinId = trim($pinId);
        $pin = trim($pin);

        if (!$apiKey || !$secretKey || $verifyUrl === '' || $pinId === '' || $pin === '') {
            return false;
        }

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withBasicAuth($apiKey, $secretKey)
                ->post($verifyUrl, [
                    'pinId' => $pinId,
                    'pin' => $pin,
                ]);

            if (!$response->successful()) {
                $json = $response->json();
                Log::warning('Beem OTP verify failed', [
                    'status' => $response->status(),
                    'code' => data_get($json, 'data.message.code'),
                    'message' => data_get($json, 'data.message.message'),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $code = data_get($response->json(), 'data.message.code');
            $message = (string) data_get($response->json(), 'data.message.message', '');

            if ((int) $code === 117) {
                return true;
            }

            if ($code === null && $message !== '' && str_contains(strtolower($message), 'valid')) {
                return true;
            }
        } catch (\Throwable $e) {
            Log::warning('Beem OTP verify exception', [
                'message' => $e->getMessage(),
            ]);
        }

        return false;
    }

    public function ttlMinutes(): int
    {
        $ttl = (int) config('beem.otp.ttl_minutes', 5);

        return $ttl > 0 ? $ttl : 5;
    }

    private function maskMsisdn(string $digits): string
    {
        $digits = preg_replace('/\D+/', '', $digits);
        if ($digits === '') {
            return '';
        }
        if (strlen($digits) <= 4) {
            return $digits;
        }

        $start = substr($digits, 0, 3);
        $end = substr($digits, -2);
        $stars = str_repeat('*', max(0, strlen($digits) - 5));

        return $start.$stars.$end;
    }

    private function cleanConfigString(mixed $value): string
    {
        return trim((string) $value, " \t\n\r\0\x0B\"'");
    }
}
