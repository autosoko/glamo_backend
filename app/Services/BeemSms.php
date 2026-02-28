<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BeemSms
{
    public function sendOtp(string $destAddr, string $otp): bool
    {
        $message = "OTP yako ya Glamo ni {$otp}. Inaisha ndani ya dakika 5.";

        return $this->sendMessage($destAddr, $message, 1);
    }

    public function sendMessage(string $destAddr, string $message, int $recipientId = 1): bool
    {
        $apiKey = $this->cleanConfigString(config('beem.api_key'));
        $secretKey = $this->cleanConfigString(config('beem.secret_key'));
        $smsUrl = trim((string) config('beem.sms_url'));

        if (!$apiKey || !$secretKey || trim($smsUrl) === '') {
            return false;
        }

        $normalizedNumber = $this->normalizeDestination($destAddr);
        if (!$normalizedNumber) {
            return false;
        }

        $message = trim($message);
        if ($message === '') {
            return false;
        }

        $senderId = $this->cleanConfigString(config('beem.sender_id', 'Glamo'));

        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->withBasicAuth($apiKey, $secretKey)
                ->post($smsUrl, [
                    'source_addr' => $senderId,
                    'encoding' => 0,
                    'schedule_time' => '',
                    'message' => $message,
                    'recipients' => [
                        [
                            'recipient_id' => $recipientId,
                            'dest_addr' => $normalizedNumber,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('Beem SMS send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'dest' => $this->maskMsisdn($normalizedNumber),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Beem SMS send exception', [
                'message' => $e->getMessage(),
                'dest' => $this->maskMsisdn($normalizedNumber),
            ]);
        }

        return false;
    }

    private function normalizeDestination(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', trim($input));
        while (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', (string) config('beem.default_country_code', '255'));
        $countryCode = $countryCode !== '' ? $countryCode : '255';

        if (str_starts_with($digits, '0')) {
            $digits = ltrim($digits, '0');
            if ($digits === '') {
                return null;
            }
            $digits = $countryCode.$digits;
        } elseif (
            $countryCode !== ''
            && !str_starts_with($digits, $countryCode)
            && strlen($digits) <= 11
        ) {
            $digits = $countryCode.$digits;
        }

        if (strlen($digits) < 9 || strlen($digits) > 15 || str_starts_with($digits, '0')) {
            return null;
        }

        return $digits;
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
