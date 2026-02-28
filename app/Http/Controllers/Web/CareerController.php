<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CareerJob;
use App\Models\CareerJobApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(Request $request): View
    {
        $jobs = CareerJob::query()
            ->where('is_active', true)
            ->where('status', CareerJob::STATUS_PUBLISHED)
            ->where(function ($query): void {
                $query->whereNull('application_deadline')
                    ->orWhereDate('application_deadline', '>=', now()->toDateString());
            })
            ->withCount('applications')
            ->orderByRaw('application_deadline IS NULL')
            ->orderBy('application_deadline')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $myApplications = collect();

        if ($request->user() && $jobs->isNotEmpty()) {
            $myApplications = CareerJobApplication::query()
                ->where('user_id', (int) $request->user()->id)
                ->whereIn('career_job_id', $jobs->pluck('id')->all())
                ->get()
                ->keyBy('career_job_id');
        }

        return view('public.careers', [
            'jobs' => $jobs,
            'myApplications' => $myApplications,
        ]);
    }

    public function apply(Request $request, CareerJob $careerJob): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('register', ['redirect' => route('careers')]);
        }

        if (! $careerJob->isOpenForApplications()) {
            return redirect()
                ->route('careers')
                ->with('error', 'Samahani, kazi hii imefungwa au muda wa ku-apply umeisha.');
        }

        $data = $request->validate([
            'cv_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'application_letter_file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'cover_letter' => ['nullable', 'string', 'max:2000'],
        ]);

        $application = CareerJobApplication::query()->firstOrNew([
            'career_job_id' => (int) $careerJob->id,
            'user_id' => (int) $user->id,
        ]);

        if ($application->exists && (string) $application->status === CareerJobApplication::STATUS_APPROVED) {
            return redirect()
                ->route('careers')
                ->with('success', 'Tayari umekubaliwa kwenye kazi hii.');
        }

        $cvPath = $request->file('cv_file')->store('careers/cv', 'public');
        $applicationLetterPath = $request->file('application_letter_file')->store('careers/application-letters', 'public');

        $oldCvPath = trim((string) ($application->cv_file_path ?? ''));
        if ($oldCvPath !== '' && $oldCvPath !== $cvPath) {
            Storage::disk('public')->delete($oldCvPath);
        }

        $oldApplicationLetterPath = trim((string) ($application->application_letter_file_path ?? ''));
        if ($oldApplicationLetterPath !== '' && $oldApplicationLetterPath !== $applicationLetterPath) {
            Storage::disk('public')->delete($oldApplicationLetterPath);
        }

        $coverLetter = trim((string) ($data['cover_letter'] ?? ''));

        $application->status = CareerJobApplication::STATUS_PENDING;
        $application->cover_letter = $coverLetter !== '' ? $coverLetter : 'Ombi limetumwa kupitia page ya Kazi.';
        $application->cv_file_path = $cvPath;
        $application->application_letter_file_path = $applicationLetterPath;
        $application->admin_note = null;
        $application->save();

        return redirect()
            ->route('careers')
            ->with('success', 'Asante! Ombi lako limepokelewa. Tunahakiki taarifa zako.');
    }
}
