@extends('public.layout')

@section('title', 'Masharti - Glamo')

@section('content')
<section class="section policyHero" aria-label="Masharti ya matumizi">
  <div class="container policyHero__grid">
    <div class="policyHero__content">
      <span class="policyBadge">Masharti</span>
      <h1 class="policyHero__title">Masharti ya matumizi ya Glamo.</h1>
      <p class="policyHero__subtitle">Kwa kutumia Glamo, unakubali kufuata masharti haya ya msingi.</p>
    </div>

    <aside class="policyHero__card">
      <h2>Muhimu</h2>
      <ul class="policyList">
        <li>Tumia taarifa sahihi wakati wa kujisajili na booking.</li>
        <li>Bei zilizooneshwa kwenye huduma ndizo zinatumika.</li>
        <li>Kila mtumiaji anatakiwa kuheshimu mtoa huduma na mteja.</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container policyBody">
    <article class="policyBlock">
      <h3>Akaunti na matumizi</h3>
      <p>Usitumie akaunti ya mtu mwingine. Tunaruhusu kufunga akaunti inayokiuka taratibu za mfumo.</p>
    </article>

    <article class="policyBlock">
      <h3>Huduma na booking</h3>
      <p>Booking inategemea upatikanaji wa mtoa huduma. Ratiba inaweza kubadilika kwa taarifa ya mapema.</p>
    </article>

    <article class="policyBlock">
      <h3>Wajibu wa mtumiaji</h3>
      <p>Hairuhusiwi kutumia lugha ya matusi, udanganyifu wa malipo, au taarifa za uongo ndani ya mfumo.</p>
    </article>
  </div>
</section>
@endsection

