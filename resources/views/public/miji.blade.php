@extends('public.layout')

@section('title', 'Miji - Glamo')

@section('content')
<section class="section citiesHero" aria-label="Miji ya huduma">
  <div class="container citiesHero__grid">
    <div class="citiesHero__content">
      <span class="citiesBadge">Miji ya huduma</span>
      <h1 class="citiesHero__title">Kwa sasa huduma inapatikana Tanzania, mji wa Arusha.</h1>
      <p class="citiesHero__subtitle">Masoko yanaandaliwa kwa nchi zaidi ya 5 za Afrika zitakazopata huduma hii.</p>

      <div class="citiesHero__actions">
        <a class="btn btn--primary" href="{{ route('services.index') }}">Angalia huduma</a>
        <a class="btn btn--ghost" href="{{ route('support') }}">Wasiliana nasi</a>
      </div>
    </div>

    <aside class="citiesHero__card" aria-label="Upatikanaji wa huduma">
      <h2>Upatikanaji</h2>
      <ul class="citiesList">
        <li>Nchi: Tanzania</li>
        <li>Mji wa sasa: Arusha</li>
        <li>Upanuzi: Nchi 5+ Afrika</li>
      </ul>
    </aside>
  </div>
</section>
@endsection
