@extends('public.layout')

@section('title', 'Kamilisha Taarifa za Mtoa Huduma - Glamo')

@section('content')
@php
  $selectedSkills = collect(old('selected_skills', $provider->selected_skills ?? []))
    ->map(fn ($v) => strtolower(trim((string) $v)))
    ->filter()
    ->unique()
    ->values()
    ->all();

  $educationStatus = old('education_status', (string) ($provider->education_status ?? ''));
  $approvalStatus = (string) ($provider->approval_status ?? 'pending');
@endphp

<section class="section">
  <div class="container">
    <div class="auth">
      <div class="auth__card" style="max-width:1060px;">
        <div class="authHeader" style="margin-bottom:14px;">
          <h2 class="authTitle" style="margin:0 0 6px;">Kamilisha taarifa zako</h2>
          <p class="muted" style="margin:0;">Jaza taarifa zote muhimu ili timu yetu ikuhakiki kabla hujaanza kupokea oda.</p>
        </div>

        @if($approvalStatus === 'pending' && $provider->onboarding_completed_at)
          <div class="flash flash--success" style="margin-bottom:12px;">
            Taarifa zako zipo kwenye uhakiki. Utapokea ujumbe ukaguzi ukikamilika.
          </div>
        @elseif($approvalStatus === 'needs_more_steps')
          <div class="flash flash--error" style="margin-bottom:12px;">
            Umeidhinishwa kwa hatua (partial approved). Tafadhali angalia ratiba ya interview kwenye dashibodi.
          </div>
        @elseif($approvalStatus === 'rejected')
          <div class="flash flash--error" style="margin-bottom:12px;">
            Taarifa zako zinahitaji marekebisho. Jaza upya sehemu zilizo na mapungufu.
          </div>
        @endif

        <style>
          .providerForm { display: grid; gap: 16px; }
          .providerSection {
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 16px;
            padding: 14px;
            background: #fff;
          }
          .providerSection__title {
            margin: 0 0 12px;
            font-size: 20px;
            font-weight: 700;
            color: #2d111c;
          }
          .providerGrid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
          }
          .providerGrid--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
          .providerGrid--1 { grid-template-columns: 1fr; }
          .providerField {
            display: grid;
            gap: 5px;
          }
          .providerField--full {
            grid-column: 1 / -1;
          }
          .providerLabel {
            font-size: 13px;
            font-weight: 600;
            color: #331721;
          }
          .providerHint {
            margin: 0;
            font-size: 12px;
            color: #6a5f63;
            line-height: 1.5;
          }
          .providerHint--ok { color: #0f7a3a; }
          .providerHint--warn { color: #a0491b; }
          .providerInput,
          .providerSelect,
          .providerTextarea {
            width: 100%;
            border: 1px solid rgba(90,14,36,.18);
            border-radius: 12px;
            min-height: 42px;
            padding: 10px 12px;
            font: inherit;
            color: #231a1e;
            outline: none;
            background: #fff;
          }
          .providerTextarea {
            min-height: 100px;
            resize: vertical;
          }
          .providerInput:focus,
          .providerSelect:focus,
          .providerTextarea:focus {
            border-color: rgba(90,14,36,.42);
            box-shadow: 0 0 0 4px rgba(90,14,36,.1);
          }
          .providerChecks {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
          }
          .providerCheck {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(90,14,36,.16);
            border-radius: 999px;
            padding: 8px 12px;
            background: #fff;
            font-size: 13px;
          }
          .providerServices {
            display: grid;
            gap: 12px;
          }
          .providerServiceGroup {
            border: 1px solid rgba(90,14,36,.14);
            border-radius: 14px;
            padding: 10px;
          }
          .providerServiceGroup__title {
            margin: 0 0 8px;
            font-size: 14px;
            font-weight: 700;
            color: #2f1722;
          }
          .providerServiceList {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
          }
          .providerServiceItem {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(0,0,0,.08);
            border-radius: 10px;
            padding: 8px;
            font-size: 13px;
            background: #fff;
          }
          .providerAlert {
            border: 1px solid rgba(90,14,36,.18);
            border-radius: 12px;
            background: rgba(90,14,36,.05);
            color: #4e2532;
            padding: 10px 12px;
            font-size: 13px;
            line-height: 1.5;
          }
          .providerDocLink {
            font-size: 12px;
            color: #5A0E24;
            text-decoration: underline;
            text-underline-offset: 2px;
          }
          .providerActions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
          }
          .profileEditor {
            margin-top: 10px;
            border: 1px solid rgba(90,14,36,.16);
            border-radius: 14px;
            padding: 10px;
            background: #fff9fb;
          }
          .profileEditor[hidden] { display: none !important; }
          .profileEditor__stage {
            width: min(360px, 100%);
            aspect-ratio: 1 / 1;
            border: 1px solid rgba(90,14,36,.16);
            border-radius: 12px;
            overflow: hidden;
            background: #e8c8d7;
            margin-bottom: 10px;
          }
          .profileEditor__canvas {
            width: 100%;
            height: 100%;
            display: block;
            cursor: grab;
            touch-action: none;
          }
          .profileEditor__canvas.is-dragging { cursor: grabbing; }
          .profileEditor__tools {
            display: grid;
            gap: 8px;
          }
          .profileEditor__row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
          }
          .profileEditor__row input[type="range"] {
            flex: 1 1 220px;
          }
          .profileEditor__actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
          }
          .profilePreview {
            margin-top: 8px;
            width: 86px;
            height: 86px;
            border-radius: 16px;
            border: 1px solid rgba(90,14,36,.16);
            object-fit: cover;
            background: #e8c8d7;
          }
          .profileMode {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
          }
          .profileMode label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid rgba(90,14,36,.18);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 12px;
            background: #fff;
          }
          @media (max-width: 980px) {
            .providerGrid,
            .providerGrid--2,
            .providerServiceList,
            .providerChecks {
              grid-template-columns: repeat(2, minmax(0, 1fr));
            }
          }
          @media (max-width: 640px) {
            .providerGrid,
            .providerGrid--2,
            .providerServiceList,
            .providerChecks {
              grid-template-columns: 1fr;
            }
          }
        </style>

        <form method="POST" action="{{ route('provider.onboarding.submit') }}" enctype="multipart/form-data" class="providerForm" id="providerOnboardingForm">
          @csrf

          <div class="providerSection">
            <h3 class="providerSection__title">Taarifa binafsi</h3>
            <div class="providerGrid">
              <div class="providerField">
                <label class="providerLabel" for="first_name">Jina la kwanza</label>
                <input class="providerInput" id="first_name" name="first_name" value="{{ old('first_name', $provider->first_name ?? '') }}" required>
                @error('first_name') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="middle_name">Jina la kati</label>
                <input class="providerInput" id="middle_name" name="middle_name" value="{{ old('middle_name', $provider->middle_name ?? '') }}" required>
                @error('middle_name') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="last_name">Jina la mwisho</label>
                <input class="providerInput" id="last_name" name="last_name" value="{{ old('last_name', $provider->last_name ?? '') }}" required>
                @error('last_name') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="business_nickname">Nickname ya biashara (itaonekana kwa wateja)</label>
                <input class="providerInput" id="business_nickname" name="business_nickname" value="{{ old('business_nickname', $provider->business_nickname ?? (auth()->user()->name ?? '')) }}" required>
                <p class="providerHint">Mfano: Erick Beauty Hub</p>
                <p class="providerHint" id="nicknameCheckHint" hidden></p>
                @error('business_nickname') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div class="providerField providerField--full">
                <label class="providerLabel" for="profile_image">Picha ya profile (portrait)</label>
                <input class="providerInput" id="profile_image" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                <input type="hidden" id="profile_image_mode" name="profile_image_mode" value="{{ old('profile_image_mode', 'auto_remove') }}">
                <div class="profileMode" id="profileModeSelect">
                  <label>
                    <input type="radio" name="profile_image_mode_choice" value="auto_remove" {{ old('profile_image_mode', 'auto_remove') === 'auto_remove' ? 'checked' : '' }}>
                    Toa background (inapendekezwa)
                  </label>
                  <label>
                    <input type="radio" name="profile_image_mode_choice" value="original" {{ old('profile_image_mode', 'auto_remove') === 'original' ? 'checked' : '' }}>
                    Tumia original
                  </label>
                </div>
                <p class="providerHint">
                  Size inayopendekezwa: angalau 1080x1080, uso uonekane vizuri, faili hadi 5MB.
                  Ukipakia, unaweza ku-zoom na ku-crop kabla ya save.
                </p>
                @if(!empty($provider->profile_image_path))
                  <a class="providerDocLink" target="_blank" href="{{ \App\Support\PublicFileUrl::url($provider->profile_image_path) }}">Angalia picha ya profile iliyopo</a>
                @endif
                @error('profile_image') <div class="err">{{ $message }}</div> @enderror
                @error('profile_image_mode') <div class="err">{{ $message }}</div> @enderror

                <div class="profileEditor" id="profileEditor" hidden>
                  <div class="profileEditor__stage">
                    <canvas class="profileEditor__canvas" id="profileEditorCanvas" width="360" height="360"></canvas>
                  </div>

                  <div class="profileEditor__tools">
                    <div class="profileEditor__row">
                      <label class="providerLabel" for="profileZoomRange">Zoom</label>
                      <input type="range" id="profileZoomRange" min="1" max="3" step="0.01" value="1">
                      <span class="providerHint" id="profileZoomValue">1.00x</span>
                    </div>
                    <div class="profileEditor__actions">
                      <button class="btn btn--ghost btn--sm" type="button" id="profileResetBtn">Reset</button>
                      <button class="btn btn--primary btn--sm" type="button" id="profileApplyBtn">Tumia picha hii</button>
                    </div>
                  </div>
                </div>

                <img class="profilePreview" id="profilePreview" alt="Preview ya profile image" hidden>
              </div>
            </div>

            <div class="providerGrid" style="margin-top:10px;">
              <div class="providerField">
                <label class="providerLabel" for="phone_public">Namba ya simu unayotumia kazi</label>
                <input class="providerInput" id="phone_public" name="phone_public" value="{{ old('phone_public', $provider->phone_public ?? (auth()->user()->phone ?? '')) }}" required>
                @error('phone_public') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="alt_phone">Namba mbadala (hiari)</label>
                <input class="providerInput" id="alt_phone" name="alt_phone" value="{{ old('alt_phone', $provider->alt_phone ?? '') }}">
                @error('alt_phone') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="gender">Jinsia</label>
                <select class="providerSelect" id="gender" name="gender" required>
                  @php($gender = old('gender', $provider->gender ?? ''))
                  <option value="">Chagua</option>
                  <option value="female" {{ $gender === 'female' ? 'selected' : '' }}>Mwanamke</option>
                  <option value="male" {{ $gender === 'male' ? 'selected' : '' }}>Mwanaume</option>
                  <option value="other" {{ $gender === 'other' ? 'selected' : '' }}>Nyingine</option>
                </select>
                @error('gender') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="providerGrid providerGrid--2" style="margin-top:10px;">
              <div class="providerField">
                <label class="providerLabel" for="date_of_birth">Tarehe ya kuzaliwa</label>
                <input class="providerInput" id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', optional($provider->date_of_birth)->format('Y-m-d') ?? '') }}" required>
                @error('date_of_birth') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="years_experience">Uzoefu wa miaka mingapi?</label>
                <input class="providerInput" id="years_experience" type="number" min="0" max="60" name="years_experience" value="{{ old('years_experience', $provider->years_experience ?? '') }}" required>
                @error('years_experience') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          <div class="providerSection">
            <h3 class="providerSection__title">Utambulisho</h3>
            <div class="providerGrid providerGrid--2">
              <div class="providerField">
                <label class="providerLabel" for="id_type">Aina ya kitambulisho</label>
                <select class="providerSelect" id="id_type" name="id_type" required>
                  @php($idType = old('id_type', $provider->id_type ?? ''))
                  <option value="">Chagua</option>
                  <option value="nida" {{ $idType === 'nida' ? 'selected' : '' }}>NIDA</option>
                  <option value="voter" {{ $idType === 'voter' ? 'selected' : '' }}>Mpiga kura</option>
                  <option value="passport" {{ $idType === 'passport' ? 'selected' : '' }}>Passport</option>
                  <option value="driver_license" {{ $idType === 'driver_license' ? 'selected' : '' }}>Leseni ya udereva</option>
                </select>
                @error('id_type') <div class="err">{{ $message }}</div> @enderror
              </div>

              <div class="providerField">
                <label class="providerLabel" for="id_number">Namba ya kitambulisho</label>
                <input class="providerInput" id="id_number" name="id_number" value="{{ old('id_number', $provider->id_number ?? '') }}" required>
                @error('id_number') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            @php($idTypeValue = old('id_type', $provider->id_type ?? ''))
            @php($useDualIdUploads = in_array($idTypeValue, ['nida', 'voter', 'driver_license'], true))

            <div id="idDualUploadBlock" style="margin-top:10px;" @if(!$useDualIdUploads) hidden @endif>
              <div class="providerGrid providerGrid--2">
                <div class="providerField">
                  <label class="providerLabel" for="id_document_front">Mbele ya kitambulisho (Front)</label>
                  <input class="providerInput" id="id_document_front" type="file" name="id_document_front" accept=".jpg,.jpeg,.png,.pdf">
                  <p class="providerHint">Pakia picha au PDF ya upande wa mbele.</p>
                  @if(!empty($provider->id_document_front_path))
                    <a class="providerDocLink" target="_blank" href="{{ \App\Support\PublicFileUrl::url($provider->id_document_front_path) }}">Angalia front iliyopo</a>
                  @endif
                  @error('id_document_front') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="providerField">
                  <label class="providerLabel" for="id_document_back">Nyuma ya kitambulisho (Back)</label>
                  <input class="providerInput" id="id_document_back" type="file" name="id_document_back" accept=".jpg,.jpeg,.png,.pdf">
                  <p class="providerHint">Pakia picha au PDF ya upande wa nyuma.</p>
                  @if(!empty($provider->id_document_back_path))
                    <a class="providerDocLink" target="_blank" href="{{ \App\Support\PublicFileUrl::url($provider->id_document_back_path) }}">Angalia back iliyopo</a>
                  @endif
                  @error('id_document_back') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>
            </div>

            <div id="idSingleUploadBlock" class="providerField" style="margin-top:10px;" @if($useDualIdUploads) hidden @endif>
              <label class="providerLabel" for="id_document">Picha/PDF ya kitambulisho</label>
              <input class="providerInput" id="id_document" type="file" name="id_document" accept=".jpg,.jpeg,.png,.pdf">
              <p class="providerHint">Kwa passport, pakia picha au PDF inayoonekana wazi.</p>
              @if(!empty($provider->id_document_path))
                <a class="providerDocLink" target="_blank" href="{{ \App\Support\PublicFileUrl::url($provider->id_document_path) }}">Angalia document iliyopo</a>
              @endif
              @error('id_document') <div class="err">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="providerSection">
            <h3 class="providerSection__title">Ujuzi (Category) unazoweza</h3>
            <div class="providerChecks" id="skillChecks">
              @foreach($skills as $skill)
                @php($slug = strtolower((string) $skill->slug))
                <label class="providerCheck">
                  <input type="checkbox" name="selected_skills[]" value="{{ $slug }}" data-skill="{{ $slug }}" {{ in_array($slug, $selectedSkills, true) ? 'checked' : '' }}>
                  <span>{{ $skill->name }}</span>
                </label>
              @endforeach
            </div>
            @error('selected_skills') <div class="err" style="margin-top:6px;">{{ $message }}</div> @enderror
            @error('selected_skills.*') <div class="err" style="margin-top:6px;">{{ $message }}</div> @enderror
            <div class="providerAlert" style="margin-top:12px;">
              Chagua category zako hapa. Baada ya kupitishwa, utaingia dashibodi yako na kuchagua huduma mahususi unazotoa kwenye hizo category.
            </div>
          </div>

          <div class="providerSection">
            <h3 class="providerSection__title">Mafunzo na uthibitisho wa uwezo</h3>

            <div class="providerField">
              <label class="providerLabel" for="education_status">Umesomea kazi hizi?</label>
              <select class="providerSelect" id="education_status" name="education_status" required>
                <option value="">Chagua</option>
                <option value="trained" {{ $educationStatus === 'trained' ? 'selected' : '' }}>Ndio, nimesomea</option>
                <option value="not_trained" {{ $educationStatus === 'not_trained' ? 'selected' : '' }}>Hapana, sijasomea rasmi</option>
              </select>
              @error('education_status') <div class="err">{{ $message }}</div> @enderror
            </div>

            <div id="trainedBlock" style="margin-top:10px;" @if($educationStatus !== 'trained') hidden @endif>
              <div class="providerGrid providerGrid--2">
                <div class="providerField">
                  <label class="providerLabel" for="training_institution">Chuo/Kituo uliposoma (hiari)</label>
                  <input class="providerInput" id="training_institution" name="training_institution" value="{{ old('training_institution', $provider->bio ?? '') }}">
                  @error('training_institution') <div class="err">{{ $message }}</div> @enderror
                </div>

                <div class="providerField">
                  <label class="providerLabel" for="certificate_file">Cheti (image/pdf)</label>
                  <input class="providerInput" id="certificate_file" type="file" name="certificate_file" accept=".jpg,.jpeg,.png,.pdf">
                  @if(!empty($provider->certificate_path))
                    <a class="providerDocLink" target="_blank" href="{{ \App\Support\PublicFileUrl::url($provider->certificate_path) }}">Angalia cheti kilichopo</a>
                  @endif
                  @error('certificate_file') <div class="err">{{ $message }}</div> @enderror
                </div>
              </div>

              <div class="providerField" style="margin-top:10px;">
                <label class="providerLabel" for="qualification_files">Document zingine za uthibitisho (optional)</label>
                <input class="providerInput" id="qualification_files" type="file" name="qualification_files[]" accept=".jpg,.jpeg,.png,.pdf" multiple>
                @error('qualification_files') <div class="err">{{ $message }}</div> @enderror
                @error('qualification_files.*') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div id="untrainedBlock" style="margin-top:10px;" @if($educationStatus !== 'not_trained') hidden @endif>
              <div class="providerAlert" style="margin-bottom:10px;">
                Kama hujasomea rasmi, utaweka referee na utaitwa interview ya demo kuthibitisha uwezo wako.
              </div>

              <div class="providerField">
                <label class="providerLabel" for="references_text">Referee/wateja wanaokufahamu</label>
                <textarea class="providerTextarea" id="references_text" name="references_text" placeholder="Andika majina, simu na uhusiano wa watu wanaoweza kuthibitisha kazi yako.">{{ old('references_text', $provider->references_text ?? '') }}</textarea>
                @error('references_text') <div class="err">{{ $message }}</div> @enderror
              </div>

              <label class="providerCheck" style="margin-top:10px; border-radius:12px;">
                <input type="checkbox" name="demo_interview_acknowledged" value="1" {{ old('demo_interview_acknowledged', $provider->demo_interview_acknowledged ?? false) ? 'checked' : '' }}>
                <span>Ninakubali kufanyiwa interview ya demo kabla ya kuidhinishwa.</span>
              </label>
              @error('demo_interview_acknowledged') <div class="err">{{ $message }}</div> @enderror
            </div>
          </div>

          <div class="providerSection">
            <h3 class="providerSection__title">Anuani ya makazi</h3>
            <div class="providerGrid">
              <div class="providerField">
                <label class="providerLabel" for="region">Mkoa</label>
                <input class="providerInput" id="region" name="region" value="{{ old('region', $provider->region ?? '') }}" required>
                @error('region') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="district">Wilaya</label>
                <input class="providerInput" id="district" name="district" value="{{ old('district', $provider->district ?? '') }}" required>
                @error('district') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="ward">Kata</label>
                <input class="providerInput" id="ward" name="ward" value="{{ old('ward', $provider->ward ?? '') }}" required>
                @error('ward') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="providerGrid providerGrid--2" style="margin-top:10px;">
              <div class="providerField">
                <label class="providerLabel" for="village">Kijiji/Mtaa</label>
                <input class="providerInput" id="village" name="village" value="{{ old('village', $provider->village ?? '') }}" required>
                @error('village') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="house_number">Namba ya nyumba</label>
                <input class="providerInput" id="house_number" name="house_number" value="{{ old('house_number', $provider->house_number ?? '') }}" required>
                @error('house_number') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          <div class="providerSection">
            <h3 class="providerSection__title">Mtu wa dharura</h3>
            <div class="providerGrid providerGrid--2">
              <div class="providerField">
                <label class="providerLabel" for="emergency_contact_name">Jina la mtu wa dharura</label>
                <input class="providerInput" id="emergency_contact_name" name="emergency_contact_name" value="{{ old('emergency_contact_name', $provider->emergency_contact_name ?? '') }}" required>
                @error('emergency_contact_name') <div class="err">{{ $message }}</div> @enderror
              </div>
              <div class="providerField">
                <label class="providerLabel" for="emergency_contact_phone">Namba ya simu ya dharura</label>
                <input class="providerInput" id="emergency_contact_phone" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $provider->emergency_contact_phone ?? '') }}" required>
                @error('emergency_contact_phone') <div class="err">{{ $message }}</div> @enderror
              </div>
            </div>
          </div>

          <div class="providerActions">
            <a class="btn btn--ghost" href="{{ route('landing') }}">Ghairi</a>
            <button class="btn btn--primary" type="submit">Wasilisha taarifa za uhakiki</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
  (() => {
    const idTypeSelect = document.getElementById('id_type');
    const idDualUploadBlock = document.getElementById('idDualUploadBlock');
    const idSingleUploadBlock = document.getElementById('idSingleUploadBlock');
    const idFrontInput = document.getElementById('id_document_front');
    const idBackInput = document.getElementById('id_document_back');
    const idSingleInput = document.getElementById('id_document');

    const educationSelect = document.getElementById('education_status');
    const trainedBlock = document.getElementById('trainedBlock');
    const untrainedBlock = document.getElementById('untrainedBlock');

    const certificateFile = document.getElementById('certificate_file');
    const referencesText = document.getElementById('references_text');
    const demoAck = document.querySelector('input[name="demo_interview_acknowledged"]');
    const formEl = document.getElementById('providerOnboardingForm');
    const nicknameInput = document.getElementById('business_nickname');
    const nicknameCheckHint = document.getElementById('nicknameCheckHint');
    const nicknameCheckUrl = @json(route('provider.onboarding.nickname-check'));

    const profileInput = document.getElementById('profile_image');
    const profileEditor = document.getElementById('profileEditor');
    const profileCanvas = document.getElementById('profileEditorCanvas');
    const profileZoomRange = document.getElementById('profileZoomRange');
    const profileZoomValue = document.getElementById('profileZoomValue');
    const profileResetBtn = document.getElementById('profileResetBtn');
    const profileApplyBtn = document.getElementById('profileApplyBtn');
    const profilePreview = document.getElementById('profilePreview');
    const profileModeHidden = document.getElementById('profile_image_mode');
    const profileModeChoices = Array.from(document.querySelectorAll('input[name="profile_image_mode_choice"]'));

    const stageSize = 360;
    const outputSize = 1080;
    const recommendedPink = [232, 200, 214];

    let imageEl = null;
    let sourceOriginalCanvas = null;
    let sourceRemovedCanvas = null;
    let sourceMode = (profileModeHidden?.value || 'auto_remove').toLowerCase();
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let dragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let appliedFileReady = false;
    let nicknameTimer = null;
    let nicknameLastChecked = '';
    let nicknameCheckCtrl = null;

    const ctx = profileCanvas ? profileCanvas.getContext('2d') : null;

    function setNicknameHint(message, tone = '') {
      if (!nicknameCheckHint) return;

      if (!message) {
        nicknameCheckHint.hidden = true;
        nicknameCheckHint.textContent = '';
        nicknameCheckHint.classList.remove('providerHint--ok', 'providerHint--warn');
        return;
      }

      nicknameCheckHint.hidden = false;
      nicknameCheckHint.textContent = message;
      nicknameCheckHint.classList.remove('providerHint--ok', 'providerHint--warn');
      if (tone === 'ok') nicknameCheckHint.classList.add('providerHint--ok');
      if (tone === 'warn') nicknameCheckHint.classList.add('providerHint--warn');
    }

    async function checkNicknameAvailability(force = false) {
      if (!nicknameInput) return;

      const nickname = (nicknameInput.value || '').trim();
      if (nickname.length < 3) {
        setNicknameHint('');
        return;
      }

      if (!force && nicknameLastChecked === nickname) {
        return;
      }

      nicknameLastChecked = nickname;
      setNicknameHint('Inakagua nickname...', '');

      if (nicknameCheckCtrl) {
        nicknameCheckCtrl.abort();
      }
      nicknameCheckCtrl = new AbortController();

      try {
        const res = await fetch(nicknameCheckUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || '',
          },
          body: JSON.stringify({ business_nickname: nickname }),
          signal: nicknameCheckCtrl.signal,
        });

        const payload = await res.json().catch(() => ({}));
        const available = !!payload?.available;
        const suggestions = Array.isArray(payload?.suggestions) ? payload.suggestions : [];

        if (available) {
          setNicknameHint('Nickname inapatikana.', 'ok');
          return;
        }

        if (suggestions.length) {
          setNicknameHint(`Nickname imeshatumika. Jaribu: ${suggestions.join(', ')}`, 'warn');
          return;
        }

        setNicknameHint('Nickname hii tayari inatumika. Jaribu nyingine.', 'warn');
      } catch (error) {
        if (error?.name === 'AbortError') return;
        setNicknameHint('');
      }
    }

    function refreshIdentityUploadBlocks() {
      const idType = (idTypeSelect?.value || '').toLowerCase();
      const useDual = ['nida', 'voter', 'driver_license'].includes(idType);

      if (idDualUploadBlock) {
        idDualUploadBlock.hidden = !useDual;
      }

      if (idSingleUploadBlock) {
        idSingleUploadBlock.hidden = useDual;
      }

      if (idFrontInput) {
        idFrontInput.required = useDual;
      }

      if (idBackInput) {
        idBackInput.required = useDual;
      }

      if (idSingleInput) {
        idSingleInput.required = !useDual;
      }
    }

    function refreshEducationBlocks() {
      const status = (educationSelect?.value || '').toLowerCase();
      const trained = status === 'trained';
      const notTrained = status === 'not_trained';

      if (trainedBlock) {
        trainedBlock.hidden = !trained;
      }

      if (untrainedBlock) {
        untrainedBlock.hidden = !notTrained;
      }

      if (certificateFile) {
        certificateFile.required = trained;
      }

      if (referencesText) {
        referencesText.required = notTrained;
      }

      if (demoAck) {
        demoAck.required = notTrained;
      }
    }

    idTypeSelect?.addEventListener('change', refreshIdentityUploadBlocks);
    educationSelect?.addEventListener('change', refreshEducationBlocks);

    function clamp(val, min, max) {
      return Math.min(max, Math.max(min, val));
    }

    function colorDistance(a, b) {
      const dr = a[0] - b[0];
      const dg = a[1] - b[1];
      const db = a[2] - b[2];
      return Math.sqrt((dr * dr) + (dg * dg) + (db * db));
    }

    function averageCornerColor(imageData, width, height, box = 14) {
      const data = imageData.data;
      const b = Math.max(1, Math.min(box, Math.floor(Math.min(width, height) / 3)));
      const corners = [
        [0, 0],
        [width - b, 0],
        [0, height - b],
        [width - b, height - b],
      ];

      let r = 0;
      let g = 0;
      let bl = 0;
      let count = 0;

      corners.forEach(([sx, sy]) => {
        for (let y = sy; y < sy + b; y++) {
          for (let x = sx; x < sx + b; x++) {
            const i = (y * width + x) * 4;
            r += data[i];
            g += data[i + 1];
            bl += data[i + 2];
            count++;
          }
        }
      });

      if (!count) return [240, 220, 230];

      return [
        Math.round(r / count),
        Math.round(g / count),
        Math.round(bl / count),
      ];
    }

    function removeBackgroundFromCorners(canvas) {
      const c = document.createElement('canvas');
      c.width = canvas.width;
      c.height = canvas.height;

      const cctx = c.getContext('2d', { willReadFrequently: true });
      cctx.drawImage(canvas, 0, 0);

      const imageData = cctx.getImageData(0, 0, c.width, c.height);
      const data = imageData.data;
      const w = c.width;
      const h = c.height;

      const bg = averageCornerColor(imageData, w, h, 16);
      const threshold = 48;

      const visited = new Uint8Array(w * h);
      const qx = new Int32Array(w * h);
      const qy = new Int32Array(w * h);
      let head = 0;
      let tail = 0;

      function enqueueIfBackground(x, y) {
        if (x < 0 || y < 0 || x >= w || y >= h) return;
        const idx = y * w + x;
        if (visited[idx]) return;

        const i = idx * 4;
        if (data[i + 3] <= 10) return;

        const rgb = [data[i], data[i + 1], data[i + 2]];
        if (colorDistance(rgb, bg) > threshold) return;

        visited[idx] = 1;
        qx[tail] = x;
        qy[tail] = y;
        tail++;
      }

      for (let x = 0; x < w; x++) {
        enqueueIfBackground(x, 0);
        enqueueIfBackground(x, h - 1);
      }
      for (let y = 0; y < h; y++) {
        enqueueIfBackground(0, y);
        enqueueIfBackground(w - 1, y);
      }

      while (head < tail) {
        const x = qx[head];
        const y = qy[head];
        head++;
        enqueueIfBackground(x + 1, y);
        enqueueIfBackground(x - 1, y);
        enqueueIfBackground(x, y + 1);
        enqueueIfBackground(x, y - 1);
      }

      for (let i = 0; i < visited.length; i++) {
        if (!visited[i]) continue;
        data[(i * 4) + 3] = 0;
      }

      cctx.putImageData(imageData, 0, 0);
      return c;
    }

    function compositeOnPink(sourceCanvas) {
      const c = document.createElement('canvas');
      c.width = sourceCanvas.width;
      c.height = sourceCanvas.height;
      const cctx = c.getContext('2d');
      cctx.fillStyle = `rgb(${recommendedPink[0]}, ${recommendedPink[1]}, ${recommendedPink[2]})`;
      cctx.fillRect(0, 0, c.width, c.height);
      cctx.drawImage(sourceCanvas, 0, 0);
      return c;
    }

    function getActiveSourceCanvas() {
      if (sourceMode === 'auto_remove' && sourceRemovedCanvas) {
        return sourceRemovedCanvas;
      }
      return sourceOriginalCanvas;
    }

    function renderProfileEditor() {
      if (!ctx || !imageEl || !sourceOriginalCanvas) return;
      const source = getActiveSourceCanvas();
      if (!source) return;

      const sw = source.width;
      const sh = source.height;

      const baseScale = Math.max(stageSize / sw, stageSize / sh);
      const scale = baseScale * zoom;
      const dw = sw * scale;
      const dh = sh * scale;

      const minOffsetX = stageSize - dw;
      const minOffsetY = stageSize - dh;
      offsetX = clamp(offsetX, minOffsetX, 0);
      offsetY = clamp(offsetY, minOffsetY, 0);

      ctx.clearRect(0, 0, stageSize, stageSize);
      ctx.fillStyle = `rgb(${recommendedPink[0]}, ${recommendedPink[1]}, ${recommendedPink[2]})`;
      ctx.fillRect(0, 0, stageSize, stageSize);
      ctx.drawImage(source, offsetX, offsetY, dw, dh);
    }

    function resetEditorState() {
      zoom = 1;
      offsetX = 0;
      offsetY = 0;
      if (profileZoomRange) profileZoomRange.value = '1';
      if (profileZoomValue) profileZoomValue.textContent = '1.00x';
      appliedFileReady = false;
      renderProfileEditor();
    }

    async function buildSourceCanvasesFromFile(file) {
      const img = new Image();
      const objectUrl = URL.createObjectURL(file);
      await new Promise((resolve, reject) => {
        img.onload = () => resolve(true);
        img.onerror = reject;
        img.src = objectUrl;
      });
      URL.revokeObjectURL(objectUrl);

      imageEl = img;

      const maxSide = 1400;
      const scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
      const w = Math.max(1, Math.round(img.naturalWidth * scale));
      const h = Math.max(1, Math.round(img.naturalHeight * scale));

      sourceOriginalCanvas = document.createElement('canvas');
      sourceOriginalCanvas.width = w;
      sourceOriginalCanvas.height = h;
      const octx = sourceOriginalCanvas.getContext('2d');
      octx.drawImage(img, 0, 0, w, h);

      sourceRemovedCanvas = compositeOnPink(removeBackgroundFromCorners(sourceOriginalCanvas));
    }

    function updateModeFromChoice() {
      const checked = profileModeChoices.find((el) => el.checked)?.value || 'auto_remove';
      sourceMode = checked === 'original' ? 'original' : 'auto_remove';
      if (profileModeHidden) {
        profileModeHidden.value = sourceMode;
      }
      appliedFileReady = false;
      renderProfileEditor();
    }

    function applyEditedProfileImage(done) {
      if (!profileInput?.files?.length || !ctx || !sourceOriginalCanvas) {
        done?.();
        return;
      }

      const source = getActiveSourceCanvas();
      if (!source) {
        done?.();
        return;
      }

      const sw = source.width;
      const sh = source.height;
      const baseScale = Math.max(stageSize / sw, stageSize / sh);
      const scale = baseScale * zoom;
      const dw = sw * scale;
      const dh = sh * scale;

      const out = document.createElement('canvas');
      out.width = outputSize;
      out.height = outputSize;
      const outCtx = out.getContext('2d');
      outCtx.fillStyle = `rgb(${recommendedPink[0]}, ${recommendedPink[1]}, ${recommendedPink[2]})`;
      outCtx.fillRect(0, 0, outputSize, outputSize);

      const ratio = outputSize / stageSize;
      outCtx.drawImage(
        source,
        offsetX * ratio,
        offsetY * ratio,
        dw * ratio,
        dh * ratio
      );

      out.toBlob((blob) => {
        if (!blob) {
          done?.();
          return;
        }

        const fileName = `provider-profile-${Date.now()}.jpg`;
        const editedFile = new File([blob], fileName, { type: 'image/jpeg' });
        const dt = new DataTransfer();
        dt.items.add(editedFile);
        profileInput.files = dt.files;

        const previewUrl = URL.createObjectURL(blob);
        if (profilePreview) {
          profilePreview.hidden = false;
          profilePreview.src = previewUrl;
        }

        appliedFileReady = true;
        done?.();
      }, 'image/jpeg', 0.92);
    }

    profileInput?.addEventListener('change', async () => {
      const file = profileInput.files?.[0];
      if (!file) {
        profileEditor?.setAttribute('hidden', 'hidden');
        if (profilePreview) profilePreview.hidden = true;
        imageEl = null;
        sourceOriginalCanvas = null;
        sourceRemovedCanvas = null;
        return;
      }

      try {
        await buildSourceCanvasesFromFile(file);
        profileEditor?.removeAttribute('hidden');
        updateModeFromChoice();
        resetEditorState();
      } catch (e) {
        profileEditor?.setAttribute('hidden', 'hidden');
      }
    });

    profileModeChoices.forEach((input) => {
      input.addEventListener('change', updateModeFromChoice);
    });

    profileZoomRange?.addEventListener('input', () => {
      zoom = Number(profileZoomRange.value || 1);
      if (profileZoomValue) profileZoomValue.textContent = `${zoom.toFixed(2)}x`;
      appliedFileReady = false;
      renderProfileEditor();
    });

    profileResetBtn?.addEventListener('click', () => {
      resetEditorState();
    });

    profileApplyBtn?.addEventListener('click', () => {
      applyEditedProfileImage();
    });

    profileCanvas?.addEventListener('pointerdown', (event) => {
      if (!imageEl) return;
      dragging = true;
      dragStartX = event.clientX - offsetX;
      dragStartY = event.clientY - offsetY;
      profileCanvas.classList.add('is-dragging');
      profileCanvas.setPointerCapture?.(event.pointerId);
    });

    profileCanvas?.addEventListener('pointermove', (event) => {
      if (!dragging || !imageEl) return;
      offsetX = event.clientX - dragStartX;
      offsetY = event.clientY - dragStartY;
      appliedFileReady = false;
      renderProfileEditor();
    });

    function stopDrag(event) {
      dragging = false;
      profileCanvas?.classList.remove('is-dragging');
      if (profileCanvas && event?.pointerId !== undefined) {
        profileCanvas.releasePointerCapture?.(event.pointerId);
      }
    }

    profileCanvas?.addEventListener('pointerup', stopDrag);
    profileCanvas?.addEventListener('pointercancel', stopDrag);
    profileCanvas?.addEventListener('pointerleave', stopDrag);

    nicknameInput?.addEventListener('input', () => {
      if (nicknameTimer) clearTimeout(nicknameTimer);
      nicknameTimer = setTimeout(() => {
        checkNicknameAvailability(false);
      }, 500);
    });

    nicknameInput?.addEventListener('blur', () => {
      if (nicknameTimer) clearTimeout(nicknameTimer);
      checkNicknameAvailability(true);
    });

    formEl?.addEventListener('submit', (event) => {
      if (!profileInput?.files?.length) {
        return;
      }

      if (appliedFileReady) {
        return;
      }

      event.preventDefault();
      applyEditedProfileImage(() => {
        formEl.submit();
      });
    });

    refreshIdentityUploadBlocks();
    refreshEducationBlocks();
    updateModeFromChoice();
    if ((nicknameInput?.value || '').trim() !== '') {
      checkNicknameAvailability(false);
    }
  })();
</script>
@endsection
