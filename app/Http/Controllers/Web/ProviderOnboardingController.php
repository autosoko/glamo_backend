<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Provider;
use App\Services\ProfileImageProcessor;
use App\Services\ProviderOnboardingNotifier;
use App\Support\BusinessNickname;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderOnboardingController extends Controller
{
    public function show(Request $request)
    {
        [$user, $provider] = $this->resolveProviderUser($request);

        if ((string) ($provider->approval_status ?? '') === 'approved') {
            return redirect()->route('provider.dashboard');
        }

        $skills = $this->skillCategories();

        return view('public.provider-onboarding', [
            'user' => $user,
            'provider' => $provider,
            'skills' => $skills,
        ]);
    }

    public function submit(Request $request, ProviderOnboardingNotifier $notifier)
    {
        [$user, $provider] = $this->resolveProviderUser($request);
        $skills = $this->skillCategories();

        $skillSlugs = $skills->pluck('slug')
            ->map(fn ($slug) => strtolower((string) $slug))
            ->values()
            ->all();

        $idType = strtolower(trim((string) $request->input('id_type')));
        $dualSideIdTypes = ['nida', 'voter', 'driver_license'];
        $requiresDualSideUpload = in_array($idType, $dualSideIdTypes, true);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'middle_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'business_nickname' => ['required', 'string', 'max:120'],
            'profile_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'profile_image_mode' => ['nullable', 'in:auto_remove,original'],
            'phone_public' => ['required', 'string', 'max:20'],
            'gender' => ['required', 'in:male,female,other'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'years_experience' => ['required', 'integer', 'min:0', 'max:60'],
            'alt_phone' => ['nullable', 'string', 'max:20'],

            'id_type' => ['required', 'in:nida,voter,passport,driver_license'],
            'id_number' => ['required', 'string', 'max:120'],
            'id_document_front' => [
                Rule::requiredIf($requiresDualSideUpload && (string) ($provider->id_document_front_path ?? '') === ''),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'id_document_back' => [
                Rule::requiredIf($requiresDualSideUpload && (string) ($provider->id_document_back_path ?? '') === ''),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
            'id_document' => [
                Rule::requiredIf(! $requiresDualSideUpload && (string) ($provider->id_document_path ?? '') === ''),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],

            'selected_skills' => ['required', 'array', 'min:1'],
            'selected_skills.*' => ['required', Rule::in($skillSlugs)],

            'education_status' => ['required', 'in:trained,not_trained'],
            'training_institution' => ['nullable', 'string', 'max:180'],
            'certificate_file' => [
                Rule::requiredIf(
                    $request->input('education_status') === 'trained'
                    && (string) ($provider->certificate_path ?? '') === ''
                ),
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:8192',
            ],
            'qualification_files' => ['nullable', 'array'],
            'qualification_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],

            'references_text' => [
                Rule::requiredIf($request->input('education_status') === 'not_trained'),
                'nullable',
                'string',
                'min:20',
                'max:2000',
            ],
            'demo_interview_acknowledged' => [
                Rule::requiredIf($request->input('education_status') === 'not_trained'),
                'nullable',
                'accepted',
            ],

            'region' => ['required', 'string', 'max:80'],
            'district' => ['required', 'string', 'max:80'],
            'ward' => ['required', 'string', 'max:80'],
            'village' => ['required', 'string', 'max:120'],
            'house_number' => ['required', 'string', 'max:80'],

            'emergency_contact_name' => ['required', 'string', 'max:120'],
            'emergency_contact_phone' => ['required', 'string', 'max:20'],
        ], [
            'demo_interview_acknowledged.accepted' => 'Lazima ukubali kuwa utaitwa interview ya demo.',
            'selected_skills.required' => 'Chagua angalau ujuzi mmoja.',
            'id_document_front.required' => 'Pakia picha/PDF ya mbele ya kitambulisho.',
            'id_document_back.required' => 'Pakia picha/PDF ya nyuma ya kitambulisho.',
            'id_document.required' => 'Pakia picha/PDF ya kitambulisho.',
            'certificate_file.required' => 'Weka cheti chako kama umejaza kuwa umesomea.',
            'references_text.required' => 'Weka rejea zako kama hujasomea.',
        ]);

        $selectedSkills = collect($validated['selected_skills'] ?? [])
            ->map(fn ($v) => strtolower(trim((string) $v)))
            ->filter()
            ->unique()
            ->values();

        $firstName = trim((string) $validated['first_name']);
        $middleName = trim((string) $validated['middle_name']);
        $lastName = trim((string) $validated['last_name']);
        $businessNickname = BusinessNickname::normalize((string) $validated['business_nickname']);
        $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);

        if (BusinessNickname::isTaken($businessNickname, (int) $provider->id)) {
            $suggestions = BusinessNickname::suggestions($businessNickname, (int) $provider->id, 3);
            $message = 'Nickname hii ya biashara tayari inatumika.';
            if (! empty($suggestions)) {
                $message .= ' Jaribu: ' . implode(', ', $suggestions) . '.';
            }

            throw ValidationException::withMessages([
                'business_nickname' => $message,
            ]);
        }

        $normalizedPublicPhone = Phone::normalizeTzMsisdn((string) $validated['phone_public']);
        if ($normalizedPublicPhone === null) {
            throw ValidationException::withMessages([
                'phone_public' => 'Weka namba sahihi ya simu (mfano 07XXXXXXXX au 2557XXXXXXXX).',
            ]);
        }

        $educationStatus = (string) $validated['education_status'];
        $isTrained = $educationStatus === 'trained';
        $now = now();

        $basePath = 'provider-onboarding/' . (int) $provider->id;
        $profileImagePath = (string) ($provider->profile_image_path ?? '');
        $profileImageMode = strtolower(trim((string) ($validated['profile_image_mode'] ?? 'auto_remove')));
        if ($request->hasFile('profile_image')) {
            $stored = (string) $request->file('profile_image')->store($basePath . '/profile', 'public');
            $profileImagePath = (string) app(ProfileImageProcessor::class)->processStoredProfileImage($stored, $profileImageMode, 'public');
        }

        $idDocumentPath = (string) ($provider->id_document_path ?? '');
        $idDocumentFrontPath = (string) ($provider->id_document_front_path ?? '');
        $idDocumentBackPath = (string) ($provider->id_document_back_path ?? '');

        if ($requiresDualSideUpload) {
            if ($request->hasFile('id_document_front')) {
                $idDocumentFrontPath = (string) $request->file('id_document_front')->store($basePath . '/identity/front', 'public');
            }

            if ($request->hasFile('id_document_back')) {
                $idDocumentBackPath = (string) $request->file('id_document_back')->store($basePath . '/identity/back', 'public');
            }

            $idDocumentPath = null;
        } else {
            if ($request->hasFile('id_document')) {
                $idDocumentPath = (string) $request->file('id_document')->store($basePath . '/identity/single', 'public');
            }

            $idDocumentFrontPath = null;
            $idDocumentBackPath = null;
        }

        $certificatePath = (string) ($provider->certificate_path ?? '');
        if ($isTrained && $request->hasFile('certificate_file')) {
            $certificatePath = (string) $request->file('certificate_file')->store($basePath . '/certificate', 'public');
        }
        if (!$isTrained) {
            $certificatePath = null;
        }

        $qualificationDocs = [];
        if ($isTrained) {
            $qualificationDocs = is_array($provider->qualification_docs) ? $provider->qualification_docs : [];
            if ($request->hasFile('qualification_files')) {
                foreach ((array) $request->file('qualification_files') as $file) {
                    if (!$file) {
                        continue;
                    }
                    $qualificationDocs[] = (string) $file->store($basePath . '/qualification-docs', 'public');
                }
                $qualificationDocs = array_values(array_unique(array_filter($qualificationDocs)));
            }
        }

        $referencesText = $isTrained ? null : trim((string) ($validated['references_text'] ?? ''));
        $demoAcknowledged = $isTrained ? false : (bool) $request->boolean('demo_interview_acknowledged');

        DB::transaction(function () use (
            $user,
            $provider,
            $now,
            $validated,
            $firstName,
            $middleName,
            $lastName,
            $businessNickname,
            $fullName,
            $normalizedPublicPhone,
            $selectedSkills,
            $profileImagePath,
            $idDocumentPath,
            $idDocumentFrontPath,
            $idDocumentBackPath,
            $educationStatus,
            $certificatePath,
            $qualificationDocs,
            $referencesText,
            $demoAcknowledged,
            $isTrained
        ) {
            $phoneOwnerExists = \App\Models\User::query()
                ->where('phone', $normalizedPublicPhone)
                ->where('id', '!=', (int) $user->id)
                ->exists();

            if ($phoneOwnerExists) {
                throw ValidationException::withMessages([
                    'phone_public' => 'Namba hii tayari inatumika na akaunti nyingine.',
                ]);
            }

            $user->forceFill([
                'name' => $businessNickname !== '' ? $businessNickname : $fullName,
                'phone' => $normalizedPublicPhone,
                'role' => 'provider',
            ])->save();

            $provider->fill([
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'business_nickname' => $businessNickname,
                'profile_image_path' => $profileImagePath !== '' ? $profileImagePath : null,
                'phone_public' => $normalizedPublicPhone,
                'gender' => (string) $validated['gender'],
                'date_of_birth' => (string) $validated['date_of_birth'],
                'years_experience' => (int) $validated['years_experience'],
                'alt_phone' => trim((string) ($validated['alt_phone'] ?? '')),

                'id_type' => (string) $validated['id_type'],
                'id_number' => trim((string) $validated['id_number']),
                'id_document_path' => $idDocumentPath,
                'id_document_front_path' => $idDocumentFrontPath,
                'id_document_back_path' => $idDocumentBackPath,

                'selected_skills' => $selectedSkills->values()->all(),
                'education_status' => $educationStatus,
                'bio' => trim((string) ($validated['training_institution'] ?? '')),
                'certificate_path' => $certificatePath,
                'qualification_docs' => $isTrained ? $qualificationDocs : [],
                'references_text' => $referencesText,
                'demo_interview_acknowledged' => $demoAcknowledged,
                'interview_required' => !$isTrained,
                'interview_status' => !$isTrained ? ((string) ($provider->interview_status ?? '') ?: 'pending_schedule') : null,

                'region' => trim((string) $validated['region']),
                'district' => trim((string) $validated['district']),
                'ward' => trim((string) $validated['ward']),
                'village' => trim((string) $validated['village']),
                'house_number' => trim((string) $validated['house_number']),

                'emergency_contact_name' => trim((string) $validated['emergency_contact_name']),
                'emergency_contact_phone' => trim((string) $validated['emergency_contact_phone']),

                'approval_status' => 'pending',
                'approved_at' => null,
                'rejection_reason' => null,
                'approval_note' => null,
                'online_status' => 'offline',
                'offline_reason' => 'Taarifa zako zinahakikiwa.',
                'onboarding_submitted_at' => $now,
                'onboarding_completed_at' => $now,
            ])->save();
        });

        $user->refresh();
        $provider->refresh();

        try {
            $notifier->notifySubmitted($user, $provider);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('provider.dashboard')
            ->with('success', 'Tumepokea taarifa zako. Ziko kwenye uhakiki, utapokea ujumbe pindi ukaguzi ukikamilika.');
    }

    public function checkBusinessNickname(Request $request)
    {
        [, $provider] = $this->resolveProviderUser($request);

        $nickname = BusinessNickname::normalize((string) $request->input('business_nickname'));
        if ($nickname === '') {
            return response()->json([
                'success' => false,
                'available' => false,
                'message' => 'Andika nickname ya biashara kwanza.',
                'suggestions' => [],
            ], 422);
        }

        $available = ! BusinessNickname::isTaken($nickname, (int) $provider->id);
        $suggestions = $available
            ? []
            : BusinessNickname::suggestions($nickname, (int) $provider->id, 3);

        return response()->json([
            'success' => true,
            'available' => $available,
            'nickname' => $nickname,
            'message' => $available
                ? 'Nickname hii inapatikana.'
                : 'Nickname hii tayari inatumika.',
            'suggestions' => $suggestions,
        ]);
    }

    private function resolveProviderUser(Request $request): array
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ((string) ($user->role ?? '') !== 'provider') {
            $user->forceFill(['role' => 'provider'])->save();
        }

        $provider = Provider::query()->firstOrCreate(
            ['user_id' => (int) $user->id],
            [
                'approval_status' => 'pending',
                'phone_public' => (string) ($user->phone ?? ''),
                'online_status' => 'offline',
                'is_active' => true,
            ]
        );

        $providerPhone = Phone::normalizeTzMsisdn((string) ($provider->phone_public ?? ''));
        if ($providerPhone !== null && trim((string) ($user->phone ?? '')) !== $providerPhone) {
            $usedByOther = \App\Models\User::query()
                ->where('phone', $providerPhone)
                ->where('id', '!=', (int) $user->id)
                ->exists();

            if (!$usedByOther) {
                $user->forceFill(['phone' => $providerPhone])->save();
            }
        }

        return [$user, $provider];
    }

    private function skillCategories()
    {
        return Category::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);
    }
}
