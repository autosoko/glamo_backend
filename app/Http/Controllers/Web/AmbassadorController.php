<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AmbassadorController extends Controller
{
    public function create(): View
    {
        return view('public.ambassador');
    }

    public function store(Request $request): RedirectResponse
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
            return back()
                ->withInput()
                ->with('error', 'Imeshindikana kutuma ombi kwa sasa. Tafadhali jaribu tena.');
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
            Log::warning('Ambassador application email failed', [
                'email' => $adminEmail,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Ombi halijatumika kwa sasa. Tafadhali jaribu tena.');
        }

        return redirect()
            ->route('ambassador.create')
            ->with('success', 'Asante! Taarifa zako zimepokelewa. Utapigiwa simu.');
    }
}
