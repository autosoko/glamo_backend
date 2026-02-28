@extends('public.layout')

@section('title', 'Safari - Glamo')

@section('content')
<section class="section safariHero" aria-label="Safari ya huduma">
  <div class="container safariHero__grid">
    <div class="safariHero__content">
      <span class="safariBadge">Safari ya haraka ya Glamo</span>
      <h1 class="safariHero__title">Watoa huduma wa Glamo wanakufikia haraka kwa pikipiki.</h1>
      <p class="safariHero__subtitle">
        Ukiweka booking, tunamtuma mtoa huduma wa karibu ili akufikie kwa muda mfupi na huduma ianze kwa wakati.
      </p>

      <div class="safariHero__actions">
        <a class="btn btn--primary" href="{{ route('services.index') }}">Weka booking sasa</a>
        <a class="btn btn--ghost" href="{{ route('support') }}">Pata msaada</a>
      </div>

      <div class="safariHero__stats" aria-label="Faida za safari">
        <article class="safariStat">
          <strong>Mwitikio wa haraka</strong>
          <span>Ombi lako linashughulikiwa mapema.</span>
        </article>
        <article class="safariStat">
          <strong>Mtoa huduma wa karibu</strong>
          <span>Tunakupangia aliye karibu na wewe.</span>
        </article>
        <article class="safariStat">
          <strong>Kufika kwa wakati</strong>
          <span>Huduma inaanza kwa muda uliopangwa.</span>
        </article>
      </div>
    </div>

    <figure class="safariHero__media">
      <img class="safariHero__img--contain" src="{{ asset('images/glamo-usafiri.png') }}" alt="Usafiri wa Glamo kwa pikipiki" loading="lazy">
      <figcaption>Usafiri wa haraka kukuletea huduma ulipo.</figcaption>
    </figure>
  </div>
</section>

<section class="section safariFlow" aria-label="Jinsi safari inavyofanya kazi">
  <div class="container">
    <div class="section__head section__head--center">
      <h2 class="section__title">Jinsi safari inavyofanya kazi</h2>
      <p class="section__subtitle">Hatua 3 fupi na rahisi.</p>
    </div>

    <div class="safariFlow__grid">
      <article class="safariStep">
        <span class="safariStep__num">01</span>
        <h3>Mteja anachagua huduma</h3>
        <p>Mteja anachagua huduma anayohitaji na kuweka booking.</p>
      </article>

      <article class="safariStep">
        <span class="safariStep__num">02</span>
        <h3>Mtoa huduma anapata order</h3>
        <p>Order inatumwa kwa mtoa huduma wa karibu mwenye upatikanaji.</p>
      </article>

      <article class="safariStep">
        <span class="safariStep__num">03</span>
        <h3>Anapanga safari</h3>
        <p>Mtoa huduma anajiandaa na kuanza safari ya pikipiki.</p>
      </article>

      <article class="safariStep">
        <span class="safariStep__num">04</span>
        <h3>Anafika kukupatia huduma</h3>
        <p>Anakufikia kwa wakati na huduma inaanza mara moja.</p>
      </article>
    </div>
  </div>
</section>

<section class="section section--soft safariBenefits" aria-label="Faida za mfumo wa usafiri">
  <div class="container safariBenefits__grid">
    <div class="safariBenefits__content">
      <h2>Safari fupi, huduma kwa wakati</h2>
      <p>Pikipiki inasaidia kufika haraka na kupunguza muda wa kusubiri.</p>

      <ul class="safariChecklist">
        <li>Kufika mapema hata kwenye foleni.</li>
        <li>Mtoa huduma wa karibu anapangiwa kwanza.</li>
        <li>Muda wa kusubiri unapungua.</li>
      </ul>
    </div>

    <aside class="safariInfoCard" aria-label="Ujumbe muhimu">
      <div class="safariInfoCard__kicker">Muhimu kujua</div>
      <h3>Huduma inakufuata ulipo.</h3>
      <p>Book sasa, nasi tutahakikisha mtoa huduma anakufikia haraka.</p>
      <a class="btn btn--primary" href="{{ route('services.index') }}">Angalia huduma zote</a>
    </aside>
  </div>
</section>
@endsection
