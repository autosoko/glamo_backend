@extends('public.layout')

@section('title', 'Faragha - Glamo')

@section('content')
<section class="section policyHero" aria-label="Sera ya faragha">
  <div class="container policyHero__grid">
    <div class="policyHero__content">
      <span class="policyBadge">Faragha</span>
      <h1 class="policyHero__title">Sera ya faragha ya Glamo.</h1>
      <p class="policyHero__subtitle">Tunahifadhi taarifa zako kwa uangalifu na matumizi halali ya huduma.</p>
    </div>

    <aside class="policyHero__card">
      <h2>Tunachokusanya</h2>
      <ul class="policyList">
        <li>Taarifa za akaunti (jina, simu, email).</li>
        <li>Taarifa za booking na huduma ulizotumia.</li>
        <li>Taarifa za location pale unapokubali kutumia GPS.</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container policyBody">
    <article class="policyBlock">
      <h3>Matumizi ya taarifa</h3>
      <p>Tunatumia taarifa zako kuboresha booking, kuwasaidia watoa huduma wa karibu, na kusaidia support.</p>
    </article>

    <article class="policyBlock">
      <h3>Kushiriki taarifa</h3>
      <p>Hatushiriki taarifa zako binafsi bila sababu ya huduma au matakwa ya sheria.</p>
    </article>

    <article class="policyBlock">
      <h3>Haki zako</h3>
      <p>Unaweza kuomba marekebisho ya taarifa zako kupitia support ya Glamo.</p>
    </article>
  </div>
</section>
@endsection

