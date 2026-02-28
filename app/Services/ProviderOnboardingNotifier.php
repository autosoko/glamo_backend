<?php

namespace App\Services;

use App\Mail\ProviderOnboardingSubmittedAdminMail;
use App\Mail\ProviderOnboardingSubmittedProviderMail;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProviderOnboardingNotifier
{
    public function __construct(private readonly BeemSms $beemSms)
    {
    }

    public function notifySubmitted(User $user, Provider $provider): void
    {
        $this->notifyAdmin($user, $provider);
        $this->notifyProvider($user, $provider);
    }

    private function notifyAdmin(User $user, Provider $provider): void
    {
        $adminEmail = trim((string) config('services.provider_onboarding.admin_email', ''));
        if ($adminEmail !== '') {
            try {
                Mail::to($adminEmail)->send(new ProviderOnboardingSubmittedAdminMail($provider, $user));
            } catch (\Throwable $e) {
                Log::warning('Provider onboarding admin email failed', [
                    'email' => $this->maskEmail($adminEmail),
                    'error' => $e->getMessage(),
                    'provider_id' => $provider->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        $adminPhone = trim((string) config('services.provider_onboarding.admin_phone', ''));
        if ($adminPhone === '') {
            return;
        }

        $name = $this->providerName($provider, $user);
        $phone = trim((string) ($provider->phone_public ?: $user->phone));
        $skills = $this->skillsLabel($provider);
        $message = $this->limitSms(
            "Glamo: Maombi mapya ya mtoa huduma yamewasilishwa. Jina: {$name}. Simu: {$phone}. Ujuzi: {$skills}."
        );

        $sent = $this->beemSms->sendMessage($adminPhone, $message, (int) ($provider->id ?: 1));
        if (!$sent) {
            Log::warning('Provider onboarding admin SMS failed', [
                'provider_id' => $provider->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function notifyProvider(User $user, Provider $provider): void
    {
        $providerEmail = strtolower(trim((string) $user->email));
        if ($providerEmail !== '') {
            try {
                Mail::to($providerEmail)->send(new ProviderOnboardingSubmittedProviderMail($provider, $user));
            } catch (\Throwable $e) {
                Log::warning('Provider onboarding provider email failed', [
                    'email' => $this->maskEmail($providerEmail),
                    'provider_id' => $provider->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $providerPhone = trim((string) ($provider->phone_public ?: $user->phone));
        if ($providerPhone === '') {
            return;
        }

        $message = $this->limitSms(
            'Glamo: Tumepokea taarifa zako za usajili wa mtoa huduma. Zipo kwenye uhakiki, utapokea mrejesho hivi karibuni.'
        );
        $sent = $this->beemSms->sendMessage($providerPhone, $message, (int) ($provider->id ?: 1));

        if (!$sent) {
            Log::warning('Provider onboarding provider SMS failed', [
                'provider_id' => $provider->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function providerName(Provider $provider, User $user): string
    {
        $nickname = trim((string) ($provider->business_nickname ?? ''));
        if ($nickname !== '') {
            return $nickname;
        }

        $name = trim(implode(' ', array_filter([
            trim((string) $provider->first_name),
            trim((string) $provider->middle_name),
            trim((string) $provider->last_name),
        ])));

        if ($name !== '') {
            return $name;
        }

        $fallback = trim((string) $user->name);

        return $fallback !== '' ? $fallback : 'Mtoa huduma';
    }

    private function skillsLabel(Provider $provider): string
    {
        $skills = collect((array) ($provider->selected_skills ?? []))
            ->map(fn ($skill) => trim((string) $skill))
            ->filter()
            ->map(function (string $skill) {
                $skill = str_replace(['-', '_'], ' ', strtolower($skill));
                return ucwords($skill);
            })
            ->unique()
            ->values();

        return $skills->isNotEmpty()
            ? $skills->implode(', ')
            : 'Haijatajwa';
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

    private function limitSms(string $message, int $maxLength = 300): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, max(0, $maxLength - 3)).'...';
    }
}
