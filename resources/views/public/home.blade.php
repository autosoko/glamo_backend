@extends('public.layout')

@section('title', 'Glamo - Urembo Ukiwa Nyumbani')

@section('content')

{{-- TOP BAR (optional) --}}
<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__brand">
      <div class="logoDot"></div>
      <strong>Glamo</strong>
      <span class="muted">Urembo Ukiwa Nyumbani</span>
    </div>

    <div class="topbar__actions">
      @auth
        <span class="chip">👋 {{ auth()->user()->name ?? 'Karibu' }}</span>
      @else
        <a class="btn btn--ghost btn--sm" href="{{ route('login', ['redirect' => url()->full()]) }}">Ingia</a>
        <a class="btn btn--primary btn--sm" href="{{ route('login', ['redirect' => url()->full(), 'mode' => 'register']) }}">Jisajili</a>
      @endauth
    </div>
  </div>
</div>

{{-- HERO --}}
<section class="heroV2">
  <div class="container heroV2__grid">
    <div class="heroV2__content">
      <div class="badge">✨ Urembo Ukiwa Nyumbani</div>

      <h1 class="heroV2__title">
        Chagua misuko, nails, kubana nywele au massage —
        <span class="script">mtaalamu anakujia</span>
      </h1>

      <p class="heroV2__subtitle">
        Angalia huduma na watoa huduma waliopo. Ukiruhusu location, tutaonyesha walio karibu zaidi ndani ya 0–{{ (int)$radiusKm }}km.
      </p>

      <div class="heroV2__cta">
        <a class="btn btn--primary" href="#services">Angalia Huduma</a>
        <a class="btn btn--ghost" href="#how">Jinsi Inavyofanya Kazi</a>
      </div>

      <div class="heroV2__mini">
        <div class="miniCard">
          <div class="miniCard__t">Rekebisha kwa location</div>
          <div class="miniCard__b muted">GPS au chagua mkoa/jiji</div>
        </div>
        <div class="miniCard">
          <div class="miniCard__t">Uhakika wa kazi</div>
          <div class="miniCard__b muted">Portfolio + ratings</div>
        </div>
        <div class="miniCard">
          <div class="miniCard__t">Urahisi wa booking</div>
          <div class="miniCard__b muted">Dakika 1–2 tu</div>
        </div>
      </div>
    </div>

    <div class="heroV2__panel">
      {{-- LOCATION GATE (not blocking) --}}
      <div id="locGate" class="locGateCard">
        <div class="locGateCard__row">
          <div>
            <div class="locGateCard__title">Ruhusu Location</div>
            <div class="muted">Ili uone walio karibu zaidi.</div>
          </div>
          <span class="pill">{{ $hasLocation ? 'GPS: ON' : 'GPS: OFF' }}</span>
        </div>

        <div class="locGateCard__actions">
          <button class="btn btn--primary btn--sm" type="button" id="btnGetLocation">Tumia GPS</button>
          <button class="btn btn--ghost btn--sm" type="button" id="btnClearLocation">Zima GPS</button>
        </div>

        <div class="locGateCard__note muted">
          * Hata bila GPS, bado utaona huduma na idadi ya watoa huduma (jumla).
        </div>
      </div>

      {{-- QUICK HIGHLIGHTS --}}
      <div class="panelCard">
        <div class="panelCard__title">Leo Inapendwa</div>
        <div class="panelList">
          <div class="panelItem">
            <div>
              <div class="panelItem__name">Twist / Knotless</div>
              <div class="muted">Ya haraka na stylish</div>
            </div>
            <div class="panelItem__meta">From <b>TZS 35,000</b></div>
          </div>
          <div class="panelItem">
            <div>
              <div class="panelItem__name">Gel Nails</div>
              <div class="muted">Kaa muda mrefu</div>
            </div>
            <div class="panelItem__meta">From <b>TZS 20,000</b></div>
          </div>
          <div class="panelItem">
            <div>
              <div class="panelItem__name">Massage Therapy</div>
              <div class="muted">Relax + stress relief</div>
            </div>
            <div class="panelItem__meta">From <b>TZS 30,000</b></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

{{-- FILTER BAR --}}
<section class="filterBarWrap">
  <div class="container">
    <div class="filterBar">
      <form method="GET" action="{{ route('public.home') }}" class="filterBar__form" id="filterForm">
        {{-- keep gps params if exist --}}
        <input type="hidden" name="lat" id="lat" value="{{ request('lat') }}">
        <input type="hidden" name="lng" id="lng" value="{{ request('lng') }}">

        <div class="filterBar__item">
          <label class="filterLabel">Mkoa</label>
          <select name="region" class="filterSelect">
            <option value="">Mikoa yote</option>
            @foreach($regions as $r)
              <option value="{{ $r }}" @selected($selectedRegion === $r)>{{ $r }}</option>
            @endforeach
          </select>
        </div>

        <div class="filterBar__item">
          <label class="filterLabel">Radius</label>
          <select name="r" class="filterSelect">
            @foreach([3,5,10,15,20,30] as $km)
              <option value="{{ $km }}" @selected((int)$radiusKm === $km)>{{ $km }}km</option>
            @endforeach
          </select>
        </div>

        <div class="filterBar__item filterBar__btns">
          <button class="btn btn--primary" type="submit">Filter</button>
          <a class="btn btn--ghost" href="{{ route('public.home') }}">Reset</a>
        </div>
      </form>

      <div class="filterBar__hint muted">
        @if($hasLocation)
          Inaonyesha walio karibu ndani ya {{ (int)$radiusKm }}km (GPS).
        @elseif($selectedRegion)
          Inaonyesha waliopo {{ $selectedRegion }} (kwa uchaguzi wako).
        @else
          Inaonyesha huduma randomly + idadi ya watoa huduma (jumla).
        @endif
      </div>
    </div>
  </div>
</section>

{{-- SERVICES --}}
<section id="services" class="section">
  <div class="container">
    <div class="section__head">
      <h2 class="section__title">Misuko & Huduma</h2>
      <p class="section__subtitle">Bonyeza huduma uone watoa huduma na uweke oda.</p>
    </div>

    <div class="svcGrid">
      @foreach($services as $s)
        <a class="svcCard" href="{{ url('/services/'.$s->id) }}">
          <div class="svcCard__img">
            <img src="{{ data_get($s, 'primary_image_url') ?? asset('images/placeholder.jpg') }}" alt="{{ $s->name }}">
          </div>
          <div class="svcCard__body">
            <div class="svcCard__top">
              <div class="svcCard__name">{{ $s->name }}</div>
              <span class="pill pill--soft">TZS {{ number_format((int)($s->base_price ?? 0)) }}+</span>
            </div>

            <div class="svcCard__meta">
              @if($hasLocation || $selectedRegion)
                <span class="metaLine">📍 <b>{{ number_format((int)($s->nearby_providers ?? 0)) }}</b> karibu</span>
              @else
                <span class="metaLine">👥 <b>{{ number_format((int)($s->total_providers ?? 0)) }}</b> providers</span>
              @endif
              <span class="metaLine muted">Gusa kuona details</span>
            </div>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</section>

{{-- HOW --}}
<section id="how" class="section section--soft">
  <div class="container">
    <div class="section__head">
      <h2 class="section__title">Jinsi Inavyofanya Kazi</h2>
      <p class="section__subtitle">Hatua 3 tu — haraka na wazi.</p>
    </div>

    <div class="grid3">
      <div class="step">
        <div class="step__num">1</div>
        <div class="step__title">Chagua Location</div>
        <p class="muted">Tumia GPS au chagua mkoa kisha filter.</p>
      </div>
      <div class="step">
        <div class="step__num">2</div>
        <div class="step__title">Chagua Huduma</div>
        <p class="muted">Bonyeza msuko/huduma unayotaka.</p>
      </div>
      <div class="step">
        <div class="step__num">3</div>
        <div class="step__title">Weka Oda</div>
        <p class="muted">Mtoa huduma anathibitisha na anakujia.</p>
      </div>
    </div>
  </div>
</section>

{{-- hidden form for gps reload --}}
<form id="locForm" method="GET" action="{{ route('public.home') }}" style="display:none;">
  <input type="hidden" name="lat" id="lat2">
  <input type="hidden" name="lng" id="lng2">
  <input type="hidden" name="r" id="r2" value="{{ (int)$radiusKm }}">
</form>

@endsection

@push('scripts')
<script>
(function () {
  const btnGet = document.getElementById('btnGetLocation');
  const btnClear = document.getElementById('btnClearLocation');
  const form = document.getElementById('locForm');

  function reloadWith(lat, lng) {
    const r = document.querySelector('select[name="r"]')?.value || '{{ (int)$radiusKm }}';
    document.getElementById('lat2').value = lat;
    document.getElementById('lng2').value = lng;
    document.getElementById('r2').value = r;
    localStorage.setItem('glamo_lat', lat);
    localStorage.setItem('glamo_lng', lng);
    form.submit();
  }

  function clearGps() {
    localStorage.removeItem('glamo_lat');
    localStorage.removeItem('glamo_lng');
    const url = new URL(window.location.href);
    url.searchParams.delete('lat');
    url.searchParams.delete('lng');
    window.location.href = url.pathname + '?' + url.searchParams.toString();
  }

  // auto-use cached gps if exists and not present in URL
  const url = new URL(window.location.href);
  const cachedLat = localStorage.getItem('glamo_lat');
  const cachedLng = localStorage.getItem('glamo_lng');
  if (!url.searchParams.get('lat') && cachedLat && cachedLng) {
    url.searchParams.set('lat', cachedLat);
    url.searchParams.set('lng', cachedLng);
    window.location.replace(url.toString());
    return;
  }

  if (btnGet) btnGet.addEventListener('click', function () {
    if (!navigator.geolocation) return alert('Kifaa chako hakina Geolocation.');

    btnGet.disabled = true;
    btnGet.textContent = 'Inapata location...';

    navigator.geolocation.getCurrentPosition(
      (pos) => {
        const lat = pos.coords.latitude.toFixed(6);
        const lng = pos.coords.longitude.toFixed(6);
        reloadWith(lat, lng);
      },
      () => {
        btnGet.disabled = false;
        btnGet.textContent = 'Tumia GPS';
        alert('Imeshindikana kupata location. Washa GPS/Location kisha jaribu tena.');
      },
      { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
    );
  });

  if (btnClear) btnClear.addEventListener('click', function () {
    clearGps();
  });
})();
</script>
@endpush
