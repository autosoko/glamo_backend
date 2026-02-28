@extends('public.layout')

@section('title', 'Glamo - Urembo Nyumbani Tanzania')

@section('content')
@php
  $hasLocation = ($hasLocation ?? false) || ($lat !== null && $lng !== null);
  $radiusKm = (int) ($radiusKm ?? 5);
  if ($radiusKm <= 0) $radiusKm = 5;

  $serviceCatSlug = function ($service) {
    $rel = method_exists($service, 'getRelationValue') ? $service->getRelationValue('category') : null;
    $slug = strtolower((string) (data_get($rel, 'slug') ?? data_get($service, 'category') ?? 'other'));
    return $slug !== '' ? $slug : 'other';
  };

  $serviceCatName = function ($service) use ($serviceCatSlug) {
    $rel = method_exists($service, 'getRelationValue') ? $service->getRelationValue('category') : null;
    return (string) (data_get($rel, 'name') ?? ucfirst($serviceCatSlug($service)));
  };

  $formatTzs = static fn ($amount): string => number_format((float) $amount, 0, '.', ',');

  $topServicesCollection = collect($topServices ?? [])->values();
  $topCategories = $topServicesCollection
    ->map(function ($service) use ($serviceCatSlug, $serviceCatName) {
      return (object) [
        'slug' => $serviceCatSlug($service),
        'name' => $serviceCatName($service),
      ];
    })
    ->unique('slug')
    ->values();
@endphp

<div class="boltSwHome">
  <style>
    .boltSwHome{
      --line: rgba(90,14,36,.17);
      --cta:#5A0E24;
      --cta2:#76153C;
      --ink:#1f1a1c;
      --muted:#6f5f66;
      color:var(--ink);
      padding:18px 0 30px;
    }
    .boltContainer{ width:min(1160px, calc(100% - 28px)); margin:0 auto; }

    .boltHero{
      border:1px solid var(--line);
      border-radius:26px;
      background:
        radial-gradient(820px 340px at 14% 0%, rgba(118, 21, 60, .10), transparent 62%),
        radial-gradient(680px 300px at 88% 16%, rgba(90, 14, 36, .09), transparent 58%),
        #fff;
      box-shadow:0 12px 28px rgba(90,14,36,.1);
      overflow:hidden;
      margin-bottom:16px;
    }
    .boltHero__inner{ padding: clamp(30px,5vw,64px) clamp(18px,4vw,44px); text-align:center; max-width:980px; margin:0 auto; }
    .boltHero__kicker{ display:inline-flex; border:1px solid var(--line); border-radius:999px; background:rgba(90,14,36,.07); color:var(--cta); padding:7px 12px; font-size:11px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; }
    .boltHero h1{ margin:14px 0 10px; font-size:clamp(30px,5.1vw,56px); line-height:1.07; letter-spacing:-.03em; color:#2d111c; }
    .boltHero p{ margin:0 auto; max-width:52ch; color:var(--muted); font-size:clamp(14px,1.8vw,18px); line-height:1.6; }
    .boltHero__actions{ margin-top:14px; display:flex; justify-content:center; }

    .boltBtn{ border:0; border-radius:12px; min-height:44px; padding:10px 16px; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; cursor:pointer; font-size:14px; font-weight:700; }
    .boltBtn--green{ color:#fff; background:linear-gradient(135deg,var(--cta),var(--cta2)); box-shadow:0 10px 22px rgba(90,14,36,.28); }
    .boltBtn--light{ color:var(--cta); border:1px solid var(--line); background:#fff; }

    .boltStats{ margin-top:18px; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:10px; text-align:left; }
    .boltStats__item{ border:1px solid var(--line); background:#fff; border-radius:12px; padding:10px 12px; }
    .boltStats__item b{ display:block; color:var(--cta); font-size:18px; }
    .boltStats__item span{ color:#7d6a72; font-size:11px; letter-spacing:.06em; font-weight:700; text-transform:uppercase; }

    .homeServiceShowcase{ margin-bottom:16px; }
    .homeFilterBar{ border:1px solid var(--line); border-radius:24px; background:#f6f4f5; padding:14px; margin-bottom:12px; display:grid; grid-template-columns:1.45fr .9fr .9fr auto auto; gap:10px; align-items:end; box-shadow:0 10px 22px rgba(90,14,36,.08); }
    .homeField{ display:grid; gap:6px; }
    .homeField label{ margin:0; color:#2f1722; font-size:13px; font-weight:800; }
    .homeInput,.homeSelect{ width:100%; min-height:42px; border-radius:14px; border:1px solid rgba(90,14,36,.2); background:#fff; color:#2f1722; font:inherit; font-size:14px; padding:10px 14px; outline:none; }
    .homeInput:focus,.homeSelect:focus{ border-color:rgba(90,14,36,.45); box-shadow:0 0 0 4px rgba(90,14,36,.11); }

    .homeSvcGrid{ display:grid; gap:12px; grid-template-columns:repeat(4,minmax(0,1fr)); }
    .homeSvcCard{ border:1px solid var(--line); border-radius:22px; overflow:hidden; background:#fff; text-decoration:none; color:inherit; box-shadow:0 10px 24px rgba(90,14,36,.09); transition:.2s ease; display:grid; grid-template-rows:285px auto; }
    .homeSvcCard:hover{ transform:translateY(-2px); box-shadow:0 14px 28px rgba(90,14,36,.16); }
    .homeSvcCard__hero{ position:relative; background-position:center; background-repeat:no-repeat; background-size:cover; overflow:hidden; }
    .homeSvcCard__hero::after{ content:''; position:absolute; inset:auto 0 0; height:58%; background:linear-gradient(180deg, rgba(40,8,17,0) 0%, rgba(40,8,17,.78) 100%); }
    .homeSvcCard__chips{ position:absolute; left:12px; bottom:12px; display:flex; gap:8px; z-index:2; }
    .homeChip{ display:inline-flex; align-items:center; min-height:28px; padding:4px 10px; border-radius:999px; background:#fff; color:#221a1d; border:1px solid rgba(90,14,36,.12); font-size:12px; font-weight:600; }

    .homeSvcCard__body{ padding:12px; display:grid; gap:10px; }
    .homeSvcCard__row{ display:grid; grid-template-columns:1fr auto; gap:8px; align-items:start; }
    .homeSvcCard__title{ margin:0; color:#1f1720; font-size:17px; font-weight:700; line-height:1.15; }
    .homePricePill{ border-radius:999px; min-height:40px; background:#efedef; color:#11141f; font-size:12px; font-weight:700; padding:8px 12px; display:inline-flex; align-items:center; }
    .homePeopleRow{ display:flex; align-items:center; justify-content:space-between; gap:10px; min-height:32px; }
    .homePeople{ display:inline-flex; align-items:center; gap:8px; min-height:30px; }
    .homePeople__dots{ display:flex; align-items:center; padding-left:2px; }
    .homePeople__dots span{ width:22px; height:22px; border-radius:999px; border:2px solid #fff; margin-left:-6px; box-shadow:0 3px 9px rgba(0,0,0,.12); display:inline-block; }
    .homePeople__dots span:nth-child(1){ background:linear-gradient(135deg,#ff8ab6,#f05f96); margin-left:0; }
    .homePeople__dots span:nth-child(2){ background:linear-gradient(135deg,#a1e6ff,#63c6f5); }
    .homePeople__dots span:nth-child(3){ background:linear-gradient(135deg,#c6b5ff,#8c7fff); }
    .homePeople__dots span:nth-child(4){ background:linear-gradient(135deg,#ffe0a6,#f4bf58); }
    .homePeople__count{ font-size:16px; font-weight:700; color:#5A0E24; }
    .homePeople__meta{ font-size:11px; color:#6f5f66; font-weight:600; }
    .homeReviews{
      display:inline-flex;
      align-items:center;
      gap:6px;
      border:1px solid rgba(90,14,36,.18);
      border-radius:999px;
      background:rgba(90,14,36,.06);
      padding:4px 9px;
      line-height:1;
      white-space:nowrap;
    }
    .homeReviews__star{ color:#f59e0b; font-size:12px; }
    .homeReviews__count{ color:#4e0d22; font-size:13px; font-weight:800; }
    .homeReviews__label{ color:#6f5f66; font-size:10px; font-weight:700; letter-spacing:.01em; }

    .homeEmpty{ margin-top:10px; border:1px dashed rgba(90,14,36,.3); border-radius:16px; padding:15px; color:#6f5f66; background:rgba(90,14,36,.04); text-align:center; display:none; }

    .boltSection{ border:1px solid var(--line); border-radius:22px; background:#fff; padding:clamp(18px,4vw,30px); box-shadow:0 8px 20px rgba(16,24,40,.07); margin-bottom:16px; }
    .boltSection__head h2{ margin:0; font-size:clamp(22px,3.2vw,36px); line-height:1.15; }
    .boltSection__head p{ margin:6px 0 0; color:var(--muted); }

    .boltHow{ display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-top:8px; }
    .boltHow__step{ border:1px solid var(--line); border-radius:14px; background:#fff; padding:14px; }
    .boltHow__step b{ width:34px; height:34px; border-radius:10px; display:grid; place-items:center; background:rgba(90,14,36,.1); color:var(--cta); font-size:15px; margin-bottom:8px; }
    .boltHow__step h3{ margin:0 0 7px; font-size:16px; color:#2f1722; }
    .boltHow__step p{ margin:0; color:#6f5f66; line-height:1.6; font-size:13px; }

    .boltCtaSplit{ display:grid; grid-template-columns:1.03fr .97fr; border-radius:18px; overflow:hidden; border:1px solid var(--line); background:#fff; min-height:280px; }
    .boltCtaSplit__content{ padding:clamp(18px,3vw,32px); display:grid; align-content:center; gap:10px; }
    .boltCtaSplit__content h3{ margin:0; font-size:clamp(22px,3.2vw,34px); line-height:1.15; }
    .boltCtaSplit__content p{ margin:0; color:var(--muted); line-height:1.6; }
    .boltCtaSplit__img{ min-height:280px; background-size:cover; background-position:center; }

    .boltModal{ position:fixed; inset:0; z-index:300; background:rgba(9,18,33,.55); display:none; align-items:center; justify-content:center; padding:16px; }
    .boltModal.is-open{ display:flex; }
    .boltModal__card{ width:min(560px,100%); border-radius:16px; border:1px solid var(--line); background:#fff; box-shadow:0 20px 46px rgba(90,14,36,.28); padding:16px; display:grid; gap:10px; }
    .boltModal__title{ margin:0; font-size:22px; color:#2f1722; }
    .boltNotice{ display:none; border:1px solid #f2c9c9; background:#fff2f2; color:#a23a3a; border-radius:10px; padding:9px 10px; font-size:13px; }
    .boltModal__manual{ display:flex; gap:8px; flex-wrap:wrap; }
    .boltInput{ flex:1; min-width:220px; min-height:44px; border:1px solid var(--line); border-radius:12px; padding:10px 12px; font:inherit; font-size:14px; color:#3f2832; outline:none; background:#fff; }
    .boltInput:focus{ border-color:rgba(90,14,36,.45); box-shadow:0 0 0 4px rgba(90,14,36,.12); }
    .boltModal__actions{ display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }

    @media (max-width:1100px){
      .homeFilterBar{ grid-template-columns:1fr 1fr; }
      .homeFilterBar .homeField:first-child{ grid-column:1 / -1; }
      .homeFilterAction{ width:100%; }
      .homeSvcGrid{ grid-template-columns:repeat(2,minmax(0,1fr)); }
      .boltHow{ grid-template-columns:1fr; }
      .boltCtaSplit{ grid-template-columns:1fr; }
    }
    @media (max-width:680px){
      .boltHero__inner{ text-align:left; }
      .boltHero__actions{ justify-content:flex-start; }
      .boltStats{ grid-template-columns:1fr 1fr; }
      .homeFilterBar{ grid-template-columns:1fr; }
      .homeSvcGrid{ grid-template-columns:1fr; }
    }
  </style>

  <div class="boltContainer">
    <section class="boltHero" aria-label="Utambulisho">
      <div class="boltHero__inner">
        <span class="boltHero__kicker">Glamo Tanzania</span>
        <h1>Pendeza bila kutoka nyumbani.</h1>
        <p>Chagua huduma na mtaalamu wa karibu atakufikia haraka.</p>
        <div class="boltHero__actions">
          <a class="boltBtn boltBtn--green" href="{{ route('services.index') }}">Huduma zetu</a>
        </div>

        <div class="boltStats">
          <div class="boltStats__item">
            <b>{{ collect($services ?? [])->count() }}</b>
            <span>Huduma zote</span>
          </div>
          <div class="boltStats__item">
            <b>{{ collect($totalProvidersByService ?? [])->sum() }}</b>
            <span>Watoa huduma wote</span>
          </div>
          <div class="boltStats__item">
            <b>{{ $hasLocation ? collect($countsByService ?? [])->sum() : 0 }}</b>
            <span>{{ $hasLocation ? 'Karibu ndani ya '.$radiusKm.'km' : 'Washa location' }}</span>
          </div>
        </div>
      </div>
    </section>

    <section id="huduma" class="homeServiceShowcase" aria-label="Huduma kuu">
      <div class="homeFilterBar">
        <div class="homeField">
          <label for="homeSearch">Tafuta huduma</label>
          <input class="homeInput" id="homeSearch" type="search" placeholder="Mfano: Knotless, Gel, Massage..." autocomplete="off">
        </div>

        <div class="homeField">
          <label for="homeCategory">Aina</label>
          <select class="homeSelect" id="homeCategory">
            <option value="all">Zote</option>
            @foreach($topCategories as $category)
              <option value="{{ strtolower((string) $category->slug) }}">{{ $category->name }}</option>
            @endforeach
          </select>
        </div>

        <div class="homeField">
          <label for="homeSort">Panga</label>
          <select class="homeSelect" id="homeSort">
            <option value="default">Default</option>
            <option value="price_asc">Bei ndogo kwanza</option>
            <option value="price_desc">Bei kubwa kwanza</option>
            <option value="providers_desc">Watoa huduma wengi</option>
          </select>
        </div>

        <button class="boltBtn boltBtn--light homeFilterAction" type="button" id="homeReset">Reset</button>
        <button class="boltBtn boltBtn--green homeFilterAction" type="button" id="btnAskLoc">Washa Location</button>
      </div>

      @if($topServicesCollection->isNotEmpty())
        <div class="homeSvcGrid" id="homeServicesGrid">
          @foreach($topServicesCollection as $service)
            @php
              $categorySlug = $serviceCatSlug($service);
              $categoryName = $serviceCatName($service);
              $price = data_get($service, 'display_price');
              $price = is_numeric($price) ? (float) $price : (float) ($service->base_price ?? 0);
              $totalCount = (int) ($totalProvidersByService[$service->id] ?? 0);
              $nearCount = (int) ($countsByService[$service->id] ?? 0);
              $displayCount = $hasLocation ? $nearCount : $totalCount;
              $doneCount = (int) ($completedOrdersByService[$service->id] ?? 0);
              $durationMinutes = (int) (data_get($service, 'duration_minutes') ?? 60);
              if ($durationMinutes <= 0) $durationMinutes = 60;
              $durationHours = intdiv($durationMinutes, 60);
              $durationRemain = $durationMinutes % 60;
              $durationLabel = $durationHours > 0 ? ($durationRemain > 0 ? $durationHours.'h '.$durationRemain.'dk' : $durationHours.'h') : $durationMinutes.'dk';
              $cardImage = (string) (data_get($service, 'primary_image_url') ?: asset('images/placeholder.svg'));
            @endphp

            <a class="homeSvcCard homeSvcCardItem"
               href="{{ route('services.show', ['category' => $categorySlug, 'service' => $service->slug]) }}"
               data-name="{{ strtolower((string) ($service->name.' '.$categoryName)) }}"
               data-category="{{ strtolower((string) $categorySlug) }}"
               data-price="{{ $price }}"
               data-providers="{{ $displayCount }}">
              <div class="homeSvcCard__hero" style="background-image:url('{{ $cardImage }}');">
                <div class="homeSvcCard__chips">
                  <span class="homeChip">{{ $durationLabel }}</span>
                  <span class="homeChip">{{ $categoryName }}</span>
                </div>
              </div>

              <div class="homeSvcCard__body">
                <div class="homeSvcCard__row">
                  <h3 class="homeSvcCard__title">{{ $service->name }}</h3>
                  <span class="homePricePill">TZS {{ $formatTzs($price) }}</span>
                </div>

                <div class="homePeopleRow">
                  <div class="homePeople">
                    <span class="homePeople__dots" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                    <span class="homePeople__count">{{ $displayCount }}</span>
                    @if($hasLocation)
                      <span class="homePeople__meta">karibu {{ $radiusKm }}km</span>
                    @endif
                  </div>
                  <div class="homeReviews" aria-label="Idadi ya waliopenda huduma">
                    <span class="homeReviews__star">★</span>
                    <span class="homeReviews__count">{{ number_format($doneCount) }}</span>
                    <span class="homeReviews__label">{{ $doneCount === 1 ? 'Amependa' : 'Wamependa' }}</span>
                  </div>
                </div>
              </div>
            </a>
          @endforeach
        </div>

        <div class="homeEmpty" id="homeEmptyState">Hakuna huduma iliyopatikana kulingana na filter ulizochagua.</div>
      @else
        <p style="margin:0; color:var(--muted);">Hakuna huduma zilizowekwa kwa sasa.</p>
      @endif
    </section>

    <section id="jinsi" class="boltSection">
      <div class="boltSection__head">
        <h2>Jinsi inavyofanya kazi</h2>
        <p>Hatua chache tu kupata huduma nyumbani kwa haraka.</p>
      </div>

      <div class="boltHow">
        <article class="boltHow__step">
          <b>1</b>
          <h3>Washa location (hiari)</h3>
          <p>Ukiruhusu location utaona idadi ya watoa huduma waliopo karibu ndani ya {{ $radiusKm }}km.</p>
        </article>

        <article class="boltHow__step">
          <b>2</b>
          <h3>Chagua huduma</h3>
          <p>Angalia huduma 4 za mwanzo au fungua "Huduma zote" kuona kila huduma iliyo kwenye mfumo.</p>
        </article>

        <article class="boltHow__step">
          <b>3</b>
          <h3>Book na mtaalamu afike</h3>
          <p>Chagua huduma, thibitisha taarifa zako, na mtoa huduma atakufikia nyumbani.</p>
        </article>
      </div>
    </section>

    <section class="boltSection">
      <div class="boltCtaSplit">
        <div class="boltCtaSplit__content">
          <h3>Unafanya kazi ya urembo? Jiunge na Glamo.</h3>
          <p>Sajili account ya mtoa huduma, weka huduma unazoweza kutoa, na uanze kuonekana kwa wateja waliopo karibu nawe.</p>
          <div>
            <a class="boltBtn boltBtn--green" href="{{ route('register', ['as' => 'provider']) }}">Jisajili kama mtoa huduma</a>
          </div>
        </div>
        <div class="boltCtaSplit__img" style="background-image:url('{{ asset('images/slide 2.jpg') }}')"></div>
      </div>
    </section>
  </div>

  <script>
    (function () {
      const homeSearch = document.getElementById('homeSearch');
      const homeCategory = document.getElementById('homeCategory');
      const homeSort = document.getElementById('homeSort');
      const homeReset = document.getElementById('homeReset');
      const homeGrid = document.getElementById('homeServicesGrid');
      const homeEmpty = document.getElementById('homeEmptyState');

      const btnAskLoc = document.getElementById('btnAskLoc');

      function applyHomeFilters() {
        if (!homeGrid) return;

        const query = (homeSearch ? homeSearch.value : '').trim().toLowerCase();
        const category = (homeCategory ? homeCategory.value : 'all').toLowerCase();
        const sort = homeSort ? homeSort.value : 'default';

        const cards = Array.from(homeGrid.querySelectorAll('.homeSvcCardItem'));
        const visible = [];

        cards.forEach((card) => {
          const name = (card.getAttribute('data-name') || '').toLowerCase();
          const cat = (card.getAttribute('data-category') || 'other').toLowerCase();
          const okName = !query || name.includes(query);
          const okCategory = category === 'all' || cat === category;
          const show = okName && okCategory;

          card.style.display = show ? '' : 'none';
          if (show) visible.push(card);
        });

        if (sort !== 'default') {
          const sorted = visible.slice().sort((a, b) => {
            const priceA = parseFloat(a.getAttribute('data-price') || '0');
            const priceB = parseFloat(b.getAttribute('data-price') || '0');
            const provA = parseInt(a.getAttribute('data-providers') || '0', 10);
            const provB = parseInt(b.getAttribute('data-providers') || '0', 10);

            if (sort === 'price_asc') return priceA - priceB;
            if (sort === 'price_desc') return priceB - priceA;
            if (sort === 'providers_desc') return provB - provA;
            return 0;
          });

          sorted.forEach((card) => homeGrid.appendChild(card));
        }

        if (homeEmpty) {
          homeEmpty.style.display = visible.length ? 'none' : 'block';
        }
      }

      function resetHomeFilters() {
        if (homeSearch) homeSearch.value = '';
        if (homeCategory) homeCategory.value = 'all';
        if (homeSort) homeSort.value = 'default';
        applyHomeFilters();
      }

      homeSearch && homeSearch.addEventListener('input', applyHomeFilters);
      homeCategory && homeCategory.addEventListener('change', applyHomeFilters);
      homeSort && homeSort.addEventListener('change', applyHomeFilters);
      homeReset && homeReset.addEventListener('click', resetHomeFilters);
      applyHomeFilters();

      btnAskLoc && btnAskLoc.addEventListener('click', () => {
        if (window.GlamoGeoPrompt && typeof window.GlamoGeoPrompt.openPrompt === 'function') {
          window.GlamoGeoPrompt.openPrompt();
        }
      });
    })();
  </script>
</div>
@endsection
