<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CareerJob;
use App\Models\CareerJobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CareerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
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

        return $this->ok([
            'jobs' => $jobs->map(function (CareerJob $job) use ($myApplications): array {
                $application = $myApplications->get((int) $job->id);
                return [
                    'id' => (int) $job->id,
                    'title' => (string) $job->title,
                    'slug' => (string) $job->slug,
                    'employment_type' => (string) $job->employment_type,
                    'location' => $job->location ?: null,
                    'positions_count' => $job->positions_count !== null ? (int) $job->positions_count : null,
                    'status' => (string) $job->status,
                    'application_deadline' => optional($job->application_deadline)->toDateString(),
                    'summary' => $job->summary ?: null,
                    'requirements' => $job->requirements ?: null,
                    'published_at' => optional($job->published_at)->toIso8601String(),
                    'applications_count' => (int) ($job->applications_count ?? 0),
                    'my_application' => $application ? [
                        'id' => (int) $application->id,
                        'status' => (string) $application->status,
                        'reviewed_at' => optional($application->reviewed_at)->toIso8601String(),
                    ] : null,
                ];
            })->values()->all(),
        ]);
    }

    public function show(CareerJob $careerJob)
    {
        if (! $careerJob->is_active || (string) $careerJob->status !== CareerJob::STATUS_PUBLISHED) {
            return $this->fail('Tangazo la kazi halipo.', 404);
        }

        return $this->ok([
            'job' => [
                'id' => (int) $careerJob->id,
                'title' => (string) $careerJob->title,
                'slug' => (string) $careerJob->slug,
                'employment_type' => (string) $careerJob->employment_type,
                'location' => $careerJob->location ?: null,
                'positions_count' => $careerJob->positions_count !== null ? (int) $careerJob->positions_count : null,
                'status' => (string) $careerJob->status,
                'application_deadline' => optional($careerJob->application_deadline)->toDateString(),
                'summary' => $careerJob->summary ?: null,
                'description' => (string) $careerJob->description,
                'requirements' => $careerJob->requirements ?: null,
                'published_at' => optional($careerJob->published_at)->toIso8601String(),
                'open_for_applications' => $careerJob->isOpenForApplications(),
            ],
        ]);
    }

    public function apply(Request $request, CareerJob $careerJob)
    {
        $user = $request->user();
        if (!$user) {
            return $this->fail('Unauthorized.', 401);
        }

        if (! $careerJob->isOpenForApplications()) {
            return $this->fail('Samahani, kazi hii imefungwa au muda wa ku-apply umeisha.', 422);
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
            return $this->ok([
                'application' => [
                    'id' => (int) $application->id,
                    'status' => (string) $application->status,
                ],
            ], 'Tayari umekubaliwa kwenye kazi hii.');
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
        $application->cover_letter = $coverLetter !== '' ? $coverLetter : 'Ombi limetumwa kupitia API ya Kazi.';
        $application->cv_file_path = $cvPath;
        $application->application_letter_file_path = $applicationLetterPath;
        $application->admin_note = null;
        $application->save();

        return $this->ok([
            'application' => [
                'id' => (int) $application->id,
                'status' => (string) $application->status,
                'reviewed_at' => optional($application->reviewed_at)->toIso8601String(),
            ],
        ], 'Asante! Ombi lako limepokelewa.');
    }
}
