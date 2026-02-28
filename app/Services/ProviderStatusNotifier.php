<?php

namespace App\Services;

use App\Mail\ProviderStatusUpdatedMail;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProviderStatusNotifier
{
    public function __construct(private readonly BeemSms $beemSms)
    {
    }

    public function notify(Provider $provider, array $context = []): void
    {
        $provider->loadMissing('user');
        $user = $provider->user;

        if (! $user instanceof User) {
            return;
        }

        if (! $this->shouldNotify($provider, $context)) {
            return;
        }

        $payload = $this->buildPayload($provider, $user, $context);

        $this->notifyProviderEmail($provider, $user, $payload);
        $this->notifyProviderSms($provider, $user, $payload);
    }

    private function notifyProviderEmail(Provider $provider, User $user, array $payload): void
    {
        $email = strtolower(trim((string) $user->email));
        if ($email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new ProviderStatusUpdatedMail($provider, $user, $payload));
        } catch (\Throwable $e) {
            Log::warning('Provider status email failed', [
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'email' => $this->maskEmail($email),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyProviderSms(Provider $provider, User $user, array $payload): void
    {
        $phone = trim((string) ($provider->phone_public ?: $user->phone));
        if ($phone === '') {
            return;
        }

        $message = "Glamo: {$payload['headline']} Uhakiki: {$payload['approval_label']}. Online: {$payload['online_label']}.";

        if ((string) ($payload['approval_status'] ?? '') === 'needs_more_steps') {
            $schedule = trim((string) ($payload['interview_schedule_text'] ?? ''));
            if ($schedule !== '') {
                $message .= " {$schedule}.";
            }
        } elseif (! empty($payload['interview_time'])) {
            $message .= " Interview: {$payload['interview_time']}.";
        }

        if (! empty($payload['note'])) {
            $message .= ' Maelezo: ' . $payload['note'];
        }

        $sent = $this->beemSms->sendMessage($phone, $this->limitSms($message), (int) ($provider->id ?: 1));

        if (! $sent) {
            Log::warning('Provider status SMS failed', [
                'provider_id' => $provider->id,
                'user_id' => $user->id,
            ]);
        }
    }

    private function buildPayload(Provider $provider, User $user, array $context = []): array
    {
        $approvalStatus = (string) ($provider->approval_status ?? 'pending');
        $onlineStatus = (string) ($provider->online_status ?? 'offline');
        $interviewStatus = (string) ($provider->interview_status ?? '');
        $changedFields = (array) ($context['changed_fields'] ?? []);
        $approvalStatusChanged = in_array('approval_status', $changedFields, true);
        $onlineStatusChanged = in_array('online_status', $changedFields, true);

        $approvalLabels = [
            'pending' => 'Inasubiri uhakiki',
            'approved' => 'Umeidhinishwa',
            'needs_more_steps' => 'Umeidhinishwa kwa hatua (Partial approved)',
            'rejected' => 'Imerejeshwa',
        ];

        $onlineLabels = [
            'online' => 'Online',
            'offline' => 'Offline',
            'busy' => 'Busy',
            'blocked_debt' => 'Imefungwa kwa deni',
        ];

        $interviewLabels = [
            'pending_schedule' => 'Inasubiri kupangiwa ratiba',
            'scheduled' => 'Imepangwa',
            'completed' => 'Imekamilika',
            'passed' => 'Umefaulu',
            'failed' => 'Haujafaulu',
        ];

        $headline = match (true) {
            $approvalStatus === 'approved' && $approvalStatusChanged => 'Hongera, akaunti yako imeidhinishwa.',
            $approvalStatus === 'approved' && $onlineStatusChanged && $onlineStatus === 'online' => 'Status yako imeboreshwa. Sasa uko online.',
            $approvalStatus === 'approved' && $onlineStatusChanged && $onlineStatus !== 'online' => 'Status yako imeboreshwa. Umewekwa offline kwa sasa.',
            $approvalStatus === 'needs_more_steps' => 'Umeidhinishwa kwa hatua (partial approved). Tafadhali kamilisha interview iliyopangwa.',
            $approvalStatus === 'rejected' => 'Taarifa zako zimehitaji marekebisho kabla ya kuendelea.',
            default => 'Status ya akaunti yako imeboreshwa na admin.',
        };

        $interviewTime = $provider->interview_scheduled_at?->format('d/m/Y H:i');
        $interviewLocation = trim((string) ($provider->interview_location ?? ''));
        $interviewType = trim((string) ($provider->interview_type ?? ''));

        $interviewScheduleText = '';
        if ($approvalStatus === 'needs_more_steps') {
            $parts = [];
            if ($interviewType !== '') {
                $parts[] = $interviewType;
            }
            if ($interviewTime) {
                $parts[] = 'tarehe ' . $interviewTime;
            }
            if ($interviewLocation !== '') {
                $parts[] = 'sehemu ' . $interviewLocation;
            }

            if (! empty($parts)) {
                $interviewScheduleText = 'Umepangiwa interview ' . implode(', ', $parts);
            }
        }

        $note = trim((string) ($provider->approval_note ?: $provider->rejection_reason ?: $provider->offline_reason ?: ''));
        if ($approvalStatus === 'needs_more_steps' && $interviewScheduleText !== '') {
            $note = $note !== '' ? ($note . ' ' . $interviewScheduleText) : $interviewScheduleText;
        }

        return [
            'name' => $this->providerName($provider, $user),
            'headline' => $headline,
            'approval_status' => $approvalStatus,
            'approval_label' => $approvalLabels[$approvalStatus] ?? ucfirst(str_replace('_', ' ', $approvalStatus)),
            'online_status' => $onlineStatus,
            'online_label' => $onlineLabels[$onlineStatus] ?? ucfirst(str_replace('_', ' ', $onlineStatus)),
            'interview_status' => $interviewStatus,
            'interview_label' => $interviewLabels[$interviewStatus] ?? ($interviewStatus !== '' ? ucfirst(str_replace('_', ' ', $interviewStatus)) : null),
            'interview_time' => $interviewTime,
            'interview_location' => $interviewLocation !== '' ? $interviewLocation : null,
            'interview_type' => $interviewType !== '' ? $interviewType : null,
            'interview_schedule_text' => $interviewScheduleText !== '' ? $interviewScheduleText : null,
            'note' => $note,
            'changed_fields' => $changedFields,
            'source' => (string) ($context['source'] ?? 'admin'),
            'action' => (string) ($context['action'] ?? ''),
        ];
    }

    private function shouldNotify(Provider $provider, array $context = []): bool
    {
        $changedFields = (array) ($context['changed_fields'] ?? []);
        if (empty($changedFields)) {
            return true;
        }

        $approvalStatus = (string) ($provider->approval_status ?? 'pending');
        $approvalChanged = in_array('approval_status', $changedFields, true);
        $onlineChanged = in_array('online_status', $changedFields, true);

        // Provider akiwa tayari approved, edits za kawaida zisitume "approved" tena.
        if ($approvalStatus === 'approved' && ! $approvalChanged && ! $onlineChanged) {
            return false;
        }

        return true;
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

    private function maskEmail(string $email): string
    {
        $email = trim(strtolower($email));
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$user, $domain] = explode('@', $email, 2);
        $user = $user === '' ? '*' : substr($user, 0, 1) . '***';

        return $user . '@' . $domain;
    }

    private function limitSms(string $message, int $maxLength = 300): string
    {
        $message = trim((string) preg_replace('/\s+/', ' ', $message));

        if (strlen($message) <= $maxLength) {
            return $message;
        }

        return substr($message, 0, max(0, $maxLength - 3)) . '...';
    }
}
