@extends('public.layout')

@section('title', 'Cookies - Glamo')

@section('content')
<section class="section policyHero" aria-label="Sera ya cookies">
  <div class="container policyHero__grid">
    <div class="policyHero__content">
      <span class="policyBadge">Cookies</span>
      <h1 class="policyHero__title">Matumizi ya cookies kwenye Glamo.</h1>
      <p class="policyHero__subtitle">Cookies hutusaidia kuboresha matumizi yako ya tovuti na akaunti.</p>
    </div>

    <aside class="policyHero__card">
      <h2>Aina za cookies</h2>
      <ul class="policyList">
        <li>Cookies za kuingia na usalama wa session.</li>
        <li>Cookies za kumbukumbu ya mipangilio ya mtumiaji.</li>
        <li>Cookies za utendaji wa ukurasa.</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container policyBody">
    <article class="policyBlock">
      <h3>Kazi ya cookies</h3>
      <p>Cookies husaidia kuhifadhi session ya login na kufanya ukurasa ufanye kazi kwa ufanisi.</p>
    </article>

    <article class="policyBlock">
      <h3>Udhibiti wa cookies</h3>
      <p>Unaweza kudhibiti au kuzima cookies kupitia browser settings zako.</p>
    </article>

    <article class="policyBlock">
      <h3>Kumbuka</h3>
      <p>Ukizima baadhi ya cookies, baadhi ya vipengele vya Glamo vinaweza kutofanya kazi ipasavyo.</p>
    </article>
  </div>
</section>
@endsection

