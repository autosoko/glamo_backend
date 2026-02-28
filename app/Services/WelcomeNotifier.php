<?php

namespace App\Services;

use App\Mail\GlamoAnnouncementMail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WelcomeNotifier
{
    public function __construct(private readonly BeemSms $beemSms)
    {
    }

    public function sendForNewUser(User $user): void
    {
        $websiteUrl = $this->websiteUrl();

        $subject = 'Karibu kwenye Glamo App';
        $title = 'Karibu Glamo App';
        $message = "Karibu kwenye Glamo App.\nUnaweza kuingia kwenye website {$websiteUrl} na uka-install kwenye simu au kupakua app ya Glamo, kisha ufurahie huduma.";

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            try {
                Mail::to($email)->send(new GlamoAnnouncementMail(
                    subjectLine: $subject,
                    title: $title,
                    messageText: $message,
                    buttonText: 'Tembelea Website',
                    buttonUrl: $websiteUrl,
                ));
            } catch (\Throwable $e) {
                Log::warning('Welcome email send failed', [
                    'user_id' => (int) $user->id,
                    'email' => $this->maskEmail($email),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $phone = trim((string) ($user->phone ?? ''));
        if ($phone !== '') {
            $sms = "Karibu Glamo App. Ingia {$websiteUrl}, install kwenye simu au pakua app ya Glamo. Furahia huduma.";
            $sent = $this->beemSms->sendMessage($phone, $sms, (int) $user->id);

            if (!$sent) {
                Log::warning('Welcome SMS send failed', [
                    'user_id' => (int) $user->id,
                    'phone' => $this->maskPhone($phone),
                ]);
            }
        }
    }

    private function websiteUrl(): string
    {
        $url = trim((string) config('app.url'));
        if ($url === '') {
            $url = 'https://getglamo.com';
        }

        return rtrim($url, '/');
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 4) {
            return '+'.$digits;
        }

        $start = substr($digits, 0, min(3, strlen($digits)));
        $end = substr($digits, -2);

        return '+'.$start.'******'.$end;
    }

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if (!str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);
        $prefix = $user !== '' ? substr($user, 0, 1) : '*';

        return $prefix.'***@'.$domain;
    }
}

