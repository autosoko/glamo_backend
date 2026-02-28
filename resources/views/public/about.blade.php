@extends('public.layout')

@section('title', 'Kuhusu Sisi - Glamo')

@section('content')
<section class="section aboutHero" aria-label="Kuhusu Glamo">
  <div class="container aboutHero__grid">
    <div class="aboutHero__content">
      <span class="aboutBadge">Kuhusu sisi</span>
      <h1 class="aboutHero__title">Glamo ni huduma ya EsoTech (ERICK SOFTWARE TECHNOLOGY).</h1>
      <p class="aboutHero__subtitle">
        EsoTech ni kampuni ya software Tanzania yenye wataalamu zaidi ya 15.
        Glamo ilianza mwaka 2022 kwa lengo la kuleta mapinduzi ya huduma ya urembo mtandaoni.
      </p>
      <p class="aboutHero__subtitle">
        Tumeendesha utafiti na majaribio kwa miaka 4 hadi sasa, na tumekuja na suluhisho hili kwa uzoefu na uhakika wa tunachofanya.
      </p>

      <div class="aboutHero__actions">
        <a class="btn btn--primary" href="{{ route('services.index') }}">Angalia huduma</a>
        <a class="btn btn--ghost" href="{{ route('support') }}">Wasiliana nasi</a>
      </div>
    </div>

    <aside class="aboutJoinCard" aria-label="Jiunge na team">
      <h2>Jiunge na team ya Glamo</h2>
      <p>Jiunge nasi leo kuboresha mapinduzi ya urembo.</p>

      @auth
        @if(($joinStatus ?? null) === 'pending')
          <div class="aboutJoinMsg">Tunathakiki taarifa zako.</div>
        @elseif(($joinStatus ?? null) === 'approved')
          <div class="aboutJoinMsg aboutJoinMsg--ok">Tayari upo kwenye team ya Glamo.</div>
        @else
          <form method="POST" action="{{ route('about.join-team') }}">
            @csrf
            <button class="btn btn--primary" type="submit">Jiunge na team</button>
          </form>
          <div class="aboutJoinHint">Ukibonyeza, ombi lako litakuwa pending kwa admin.</div>
        @endif
      @else
        <a class="btn btn--primary" href="{{ route('register', ['redirect' => route('about')]) }}">Jisajili ujiunge na team</a>
        <div class="aboutJoinHint">Anza kwa kujisajili, kisha urudi ubonyeze kujiunga.</div>
      @endauth
    </aside>
  </div>
</section>
@endsection
