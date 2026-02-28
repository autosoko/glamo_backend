@extends('public.layout')

@section('title', 'Malipo - Glamo')

@section('content')
<section class="section paymentHero" aria-label="Maelezo ya malipo">
  <div class="container paymentHero__grid">
    <div class="paymentHero__content">
      <span class="paymentBadge">Malipo</span>
      <h1 class="paymentHero__title">Lipa cash baada ya kazi kukamilika.</h1>
      <p class="paymentHero__subtitle">Bei unayoiona kwenye huduma ni ya mwisho, imejumuisha kila kitu.</p>
      <p class="paymentHero__note">Hakuna gharama za ziada.</p>

      <div class="paymentHero__actions">
        <a class="btn btn--primary" href="{{ route('services.index') }}">Angalia huduma</a>
        <a class="btn btn--ghost" href="{{ route('support') }}">Pata msaada</a>
      </div>
    </div>

    <aside class="paymentHero__card" aria-label="Muhtasari wa malipo">
      <h2>Muhtasari</h2>
      <ul class="paymentList">
        <li>Malipo: Cash</li>
        <li>Muda: Baada ya huduma kuisha</li>
        <li>Bei: Imejumuisha kila kitu</li>
        <li>Ziada: Hakuna</li>
      </ul>
    </aside>
  </div>
</section>
@endsection
