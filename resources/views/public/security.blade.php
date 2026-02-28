@extends('public.layout')

@section('title', 'Usalama - Glamo')

@section('content')
<section class="section policyHero" aria-label="Sera ya usalama">
  <div class="container policyHero__grid">
    <div class="policyHero__content">
      <span class="policyBadge">Usalama</span>
      <h1 class="policyHero__title">Usalama wa mfumo wa Glamo.</h1>
      <p class="policyHero__subtitle">Tunatumia hatua za kiusalama kulinda akaunti na taarifa za watumiaji wetu.</p>
    </div>

    <aside class="policyHero__card">
      <h2>Hatua za msingi</h2>
      <ul class="policyList">
        <li>Authentication ya akaunti kupitia OTP na password.</li>
        <li>Ufuatiliaji wa activity za mfumo kwa usalama.</li>
        <li>Uhakiki wa watoa huduma kabla ya kuanza kazi.</li>
      </ul>
    </aside>
  </div>
</section>

<section class="section">
  <div class="container policyBody">
    <article class="policyBlock">
      <h3>Linda akaunti yako</h3>
      <p>Tumia nenosiri imara na usishirikishe OTP au taarifa zako za kuingia.</p>
    </article>

    <article class="policyBlock">
      <h3>Taarifa za tukio</h3>
      <p>Ukiona shughuli isiyo ya kawaida kwenye akaunti yako, wasiliana na support mara moja.</p>
    </article>

    <article class="policyBlock">
      <h3>Maboresho ya usalama</h3>
      <p>Glamo huendelea kuboresha ulinzi wa mfumo ili kupunguza hatari za kiusalama.</p>
    </article>
  </div>
</section>
@endsection

