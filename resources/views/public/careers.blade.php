@extends('public.layout')

@section('title', 'Kazi - Glamo')

@section('content')
<section class="section careersHero" aria-label="Nafasi za kazi Glamo">
  <div class="container careersHero__grid">
    <div class="careersHero__content">
      <span class="careersBadge">Kazi Glamo</span>
      <h1 class="careersHero__title">Jiunge na timu ya Glamo.</h1>
      <p class="careersHero__subtitle">
        Nafasi za kazi hutangazwa hapa moja kwa moja kutoka kwa admin. Ukishajisajili unaweza ku-apply mara moja.
      </p>

      <div class="careersHero__actions">
        <a class="btn btn--primary" href="{{ route('register', ['redirect' => route('careers')]) }}">Jisajili</a>
        <a class="btn btn--ghost" href="{{ route('about') }}">Kuhusu sisi</a>
      </div>
    </div>

    <aside class="careersHero__card" aria-label="Namna ya ku-apply">
      <h2>Namna ya ku-apply</h2>
      <ul class="careersList">
        <li>Jisajili au ingia kwenye akaunti yako.</li>
        <li>Chagua kazi unayoitaka.</li>
        <li>Pakia CV na barua ya maombi.</li>
        <li>Bonyeza apply, ombi linaingia pending.</li>
        <li>Admin atahakiki na kuapprove waliochaguliwa.</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section" aria-label="Orodha ya kazi">
  <div class="container">
    <div class="section__head section__head--center">
      <h2 class="section__title">Nafasi zilizopo</h2>
      <p class="section__subtitle">Chagua nafasi inayokufaa na tuma ombi lako.</p>
    </div>

    @if($errors->any())
      <div class="careersFormErrors">
        {{ $errors->first() }}
      </div>
    @endif

    @if($jobs->isEmpty())
      <div class="careersEmpty">
        <h3>Hakuna kazi mpya kwa sasa.</h3>
        <p>Tembelea tena hivi karibuni kuona matangazo mapya.</p>
      </div>
    @else
      <div class="careersJobs">
        @foreach($jobs as $job)
          @php
            $application = $myApplications->get($job->id);
            $applyStatus = (string) data_get($application, 'status', '');
            $typeLabel = match ((string) $job->employment_type) {
              'part_time' => 'Part-time',
              'contract' => 'Contract',
              'internship' => 'Internship',
              default => 'Full-time',
            };
          @endphp

          <article class="careerJobCard">
            <div class="careerJobCard__top">
              <h3>{{ $job->title }}</h3>
              <span class="careerPill">{{ $typeLabel }}</span>
            </div>

            <p class="careerJobCard__summary">
              {{ trim((string) $job->summary) !== '' ? $job->summary : \Illuminate\Support\Str::limit(strip_tags((string) $job->description), 180) }}
            </p>

            <div class="careerMeta">
              <span>Mji: {{ trim((string) $job->location) !== '' ? $job->location : 'Tanzania' }}</span>
              <span>Waombaji: {{ number_format((int) $job->applications_count) }}</span>
              <span>
                Mwisho:
                {{ $job->application_deadline ? $job->application_deadline->format('d M Y') : 'Inaendelea' }}
              </span>
            </div>

            <div class="careerJobCard__foot">
              @auth
                @if($applyStatus === 'approved')
                  <span class="careerApplyState careerApplyState--ok">Umeidhinishwa</span>
                @elseif($applyStatus === 'pending')
                  <span class="careerApplyState">Tunahakiki taarifa zako</span>
                @else
                  <form method="POST" action="{{ route('careers.apply', ['careerJob' => $job->slug]) }}" enctype="multipart/form-data" class="careerApplyForm">
                    @csrf

                    <div class="careerApplyForm__grid">
                      <label class="careerApplyField">
                        <span>CV yako</span>
                        <input class="careerApplyInput" type="file" name="cv_file" accept=".pdf,.doc,.docx" required>
                      </label>

                      <label class="careerApplyField">
                        <span>Barua ya maombi</span>
                        <input class="careerApplyInput" type="file" name="application_letter_file" accept=".pdf,.doc,.docx" required>
                      </label>
                    </div>

                    <label class="careerApplyField careerApplyField--full">
                      <span>Ujumbe mfupi (hiari)</span>
                      <textarea class="careerApplyTextarea" name="cover_letter" rows="2" placeholder="Andika maelezo mafupi kuhusu ombi lako...">{{ old('cover_letter') }}</textarea>
                    </label>

                    <button class="btn btn--primary btn--sm" type="submit">
                      {{ $applyStatus === 'rejected' ? 'Apply tena' : 'Apply kazi hii' }}
                    </button>
                  </form>
                  @if($applyStatus === 'rejected')
                    <span class="careerApplyState careerApplyState--warn">Ombi la awali halikukubaliwa.</span>
                  @endif
                @endif
              @else
                <a class="btn btn--primary btn--sm" href="{{ route('register', ['redirect' => route('careers')]) }}">
                  Jisajili ku-apply
                </a>
              @endauth
            </div>
          </article>
        @endforeach
      </div>
    @endif
  </div>
</section>
@endsection
