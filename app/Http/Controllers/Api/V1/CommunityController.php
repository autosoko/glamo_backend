<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommunityController extends Controller
{
    use ApiResponse;

    public function teamStatus(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $staff = Staff::query()
            ->where('user_id', (int) $user->id)
            ->first();

        return $this->ok([
            'status' => $staff ? (string) $staff->status : null,
            'notes' => $staff?->notes,
            'approved_at' => optional($staff?->approved_at)->toIso8601String(),
        ]);
    }

    public function joinTeam(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        $staff = Staff::query()->firstOrNew(['user_id' => (int) $user->id]);

        if (! $staff->exists) {
            $staff->original_role = (string) ($user->role ?? 'client');
        }

        if ((string) $staff->status !== Staff::STATUS_APPROVED) {
            $staff->status = Staff::STATUS_PENDING;
            $staff->notes = $staff->notes ?: 'Imewasilishwa kupitia API ya about-us.';
            $staff->approved_at = null;
            $staff->approved_by_admin_id = null;
            $staff->save();

            return $this->ok([
                'status' => (string) $staff->status,
            ], 'Asante! Tunahakiki taarifa zako.');
        }

        return $this->ok([
            'status' => (string) $staff->status,
        ], 'Tayari upo kwenye team ya Glamo.');
    }

    public function ambassadorApply(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'city' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $adminEmail = trim((string) config('services.provider_onboarding.admin_email', ''));
        if ($adminEmail === '') {
            return $this->fail('Imeshindikana kutuma ombi kwa sasa. Tafadhali jaribu tena.', 500);
        }

        $subject = 'Ombi jipya: Ambasador wa Glamo';
        $body = implode("\n", [
            'Kuna ombi jipya la kujiunga kama ambasador wa Glamo.',
            '',
            'Jina: ' . $data['full_name'],
            'Simu: ' . $data['phone'],
            'Mji: ' . $data['city'],
            'Email: ' . trim((string) ($data['email'] ?? '')),
            'Maelezo: ' . trim((string) ($data['notes'] ?? '')),
            '',
            'Imetumwa: ' . now()->format('Y-m-d H:i:s'),
        ]);

        try {
            Mail::raw($body, function ($message) use ($adminEmail, $subject): void {
                $message->to($adminEmail)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Ambassador application email failed (API)', [
                'email' => $adminEmail,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('Ombi halijatumwa kwa sasa. Tafadhali jaribu tena.', 500);
        }

        return $this->ok([], 'Asante! Taarifa zako zimepokelewa. Utapigiwa simu.');
    }
}
