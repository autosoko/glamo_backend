@extends('public.layout')

@section('title', 'Salon Zetu - Glamo')

@section('content')
<section class="section salonHero" aria-label="Salon zetu coming soon">
  <div class="container salonHero__grid">
    <div class="salonHero__content">
      <span class="salonBadge">Coming Soon</span>
      <h1 class="salonHero__title">Salon zetu zinakuja hivi karibuni.</h1>
      <p class="salonHero__subtitle">Mikoa: Dar es salaam, Arusha, Mwanza, Dodoma, Morogoro, Kigoma.</p>
      <p class="salonHero__note">Kitovu cha kufundisha wataalamu staha, huduma bora kwa mteja, na sehemu ya kutulia kusubiri order kwenye account.</p>

      <div class="salonHero__actions">
        <a class="btn btn--primary" href="{{ route('services.index') }}">Angalia huduma</a>
        <a class="btn btn--ghost" href="{{ route('support') }}">Pata msaada</a>
      </div>
    </div>

    <figure class="salonHero__media">
      <img src="{{ asset('images/salon.png') }}" alt="Salon ya Glamo coming soon" loading="lazy">
    </figure>
  </div>
</section>

<section class="section salonRoadmap" aria-label="Mpango wa ufunguzi wa salon">
  <div class="container">
    <div class="section__head section__head--center">
      <h2 class="section__title">Mpango wa ufunguzi</h2>
      <p class="section__subtitle">Dar es salaam (Sinza Moli), kisha mikoa 5 zaidi.</p>
    </div>

    <div class="salonRoadmap__grid">
      <article class="salonStop salonStop--primary">
        <span>01</span>
        <h3>Dar es salaam</h3>
        <p>Sinza Moli - Coming soon</p>
      </article>

      <article class="salonStop">
        <span>02</span>
        <h3>Arusha</h3>
        <p>Coming soon</p>
      </article>

      <article class="salonStop">
        <span>03</span>
        <h3>Mwanza</h3>
        <p>Coming soon</p>
      </article>

      <article class="salonStop">
        <span>04</span>
        <h3>Dodoma</h3>
        <p>Coming soon</p>
      </article>

      <article class="salonStop">
        <span>05</span>
        <h3>Morogoro</h3>
        <p>Coming soon</p>
      </article>

      <article class="salonStop">
        <span>06</span>
        <h3>Kigoma</h3>
        <p>Coming soon</p>
      </article>
    </div>
  </div>
</section>
@endsection
