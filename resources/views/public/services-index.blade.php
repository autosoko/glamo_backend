@extends('public.layout')

@section('title', 'Huduma Zote - Glamo')

@section('content')
@php
  $hasLocation = ($hasLocation ?? false) || ($lat !== null && $lng !== null);
  $radiusKm = (int) ($radiusKm ?? 5);
  if ($radiusKm <= 0) $radiusKm = 5;

  $services = collect($services ?? [])->values();

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

  $categoryPriority = static function ($category): int {
    $slug = strtolower((string) ($category->slug ?? ''));
    $name = strtolower((string) ($category->name ?? ''));
    if (in_array($slug, ['misuko', 'kusuka'], true) || str_contains($name, 'misuko') || str_contains($name, 'kusuka')) {
      return 0;
    }
    return 1;
  };

  $categories = $services
    ->map(function ($s) use ($serviceCatSlug, $serviceCatName) {
      return (object) [
        'slug' => $serviceCatSlug($s),
        'name' => $serviceCatName($s),
      ];
    })
    ->unique('slug')
    ->sort(function ($a, $b) use ($categoryPriority) {
      $priorityCompare = $categoryPriority($a) <=> $categoryPriority($b);
      if ($priorityCompare !== 0) {
        return $priorityCompare;
      }
      return strcasecmp((string) ($a->name ?? ''), (string) ($b->name ?? ''));
    })
    ->values();

  $defaultCategory = $categories->first(function ($category) {
    $slug = strtolower((string) ($category->slug ?? ''));
    $name = strtolower((string) ($category->name ?? ''));
    return in_array($slug, ['misuko', 'kusuka'], true) || str_contains($name, 'misuko') || str_contains($name, 'kusuka');
  });
  $defaultCategorySlug = strtolower((string) data_get($defaultCategory, 'slug', data_get($categories->first(), 'slug', '')));
@endphp

<div class="allServicesSw">
  <style>
    .allServicesSw {
      --ink: #1f1a1c;
      --muted: #6f5f66;
      --line: rgba(90, 14, 36, .17);
      --bg: #ffffff;
      --surface: #ffffff;
      --cta: #5A0E24;
      --cta-2: #76153C;
      color: var(--ink);
      background: var(--bg);
      padding: 18px 0 30px;
    }

    .allWrap {
      width: min(1160px, calc(100% - 28px));
      margin: 0 auto;
    }

    .allHead {
      border: 1px solid var(--line);
      border-radius: 22px;
      background:
        radial-gradient(820px 300px at 14% 0%, rgba(118, 21, 60, .10), transparent 62%),
        radial-gradient(700px 300px at 88% 18%, rgba(90, 14, 36, .09), transparent 58%),
        #fff;
      padding: clamp(18px, 3vw, 32px);
      margin-bottom: 14px;
      box-shadow: 0 10px 24px rgba(90, 14, 36, .10);
    }

    .allHead h1 {
      margin: 0;
      font-size: clamp(26px, 4vw, 40px);
      line-height: 1.1;
      letter-spacing: -0.03em;
      color: #2d111c;
    }

    .allHead p {
      margin: 8px 0 0;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.6;
      max-width: 70ch;
    }

    .allHead__actions {
      margin-top: 14px;
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .allBtn {
      border: 0;
      border-radius: 12px;
      min-height: 46px;
      padding: 11px 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 700;
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .allBtn:hover { transform: translateY(-1px); }

    .allBtn--green {
      color: #fff;
      background: linear-gradient(135deg, var(--cta) 0%, var(--cta-2) 100%);
      box-shadow: 0 9px 20px rgba(90, 14, 36, .27);
    }

    .allBtn--light {
      color: var(--cta);
      border: 1px solid var(--line);
      background: #fff;
    }

    .allFilter {
      border: 1px solid var(--line);
      border-radius: 16px;
      background: #fff;
      padding: 12px;
      margin-bottom: 12px;
      display: grid;
      grid-template-columns: 1.5fr .95fr auto;
      gap: 8px;
      box-shadow: 0 8px 18px rgba(90, 14, 36, .08);
    }

    .allField {
      display: grid;
      gap: 5px;
    }

    .allField--full {
      grid-column: 1 / -1;
    }

    .allField label {
      margin: 0;
      color: #6f5f66;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-weight: 800;
    }

    .allCategoryTabs {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .allCategoryChip {
      border: 1px solid rgba(90, 14, 36, .2);
      border-radius: 999px;
      background: #fff;
      color: #4a2231;
      min-height: 38px;
      padding: 8px 14px;
      font: inherit;
      font-size: 13px;
      font-weight: 700;
      line-height: 1;
      cursor: pointer;
      transition: border-color .2s ease, background .2s ease, color .2s ease;
    }

    .allCategoryChip.is-active {
      background: #5A0E24;
      border-color: #5A0E24;
      color: #fff;
    }

    .allCategoryChip:focus-visible {
      outline: none;
      box-shadow: 0 0 0 4px rgba(90, 14, 36, .16);
    }

    .allInput,
    .allSelect {
      width: 100%;
      min-height: 42px;
      border-radius: 11px;
      border: 1px solid var(--line);
      background: #fff;
      color: #3f2832;
      font: inherit;
      font-size: 14px;
      padding: 10px 12px;
      outline: none;
    }

    .allInput:focus,
    .allSelect:focus {
      border-color: rgba(90, 14, 36, .45);
      box-shadow: 0 0 0 4px rgba(90, 14, 36, .12);
    }

    .allFilter__actions {
      display: flex;
      align-items: end;
      gap: 8px;
    }

    .allGrid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .allCard {
      border: 1px solid var(--line);
      border-radius: 22px;
      background: #fff;
      overflow: hidden;
      display: grid;
      grid-template-rows: 285px auto;
      min-height: 302px;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 10px 24px rgba(90, 14, 36, .09);
      transition: transform .2s ease, box-shadow .2s ease;
    }

    .allCard:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 28px rgba(90, 14, 36, .16);
    }

    .allCard__img {
      position: relative;
      background:
        radial-gradient(90px 70px at 22% 18%, rgba(255,255,255,.36), transparent 70%),
        radial-gradient(140px 100px at 72% 60%, rgba(255,255,255,.2), transparent 75%),
        linear-gradient(160deg, #8f1a49 0%, #5A0E24 62%, #460b1c 100%);
      background-position: center;
      background-repeat: no-repeat;
      background-size: cover;
      overflow: hidden;
    }

    .allCard__img::after {
      content: '';
      position: absolute;
      inset: auto 0 0;
      height: 58%;
      background: linear-gradient(180deg, rgba(40,8,17,0) 0%, rgba(40,8,17,.78) 100%);
    }

    .allCard__brand {
      position: absolute;
      inset: 0;
      display: grid;
      place-content: center;
      text-align: center;
      color: #fff;
      z-index: 1;
    }

    .allCard__logoText {
      font-size: 28px;
      font-weight: 600;
      letter-spacing: -.02em;
      line-height: 1;
    }

    .allCard__logoSub {
      font-size: 10px;
      font-weight: 500;
      opacity: .88;
      margin-top: 6px;
      letter-spacing: .01em;
    }

    .allCard__chips {
      position: absolute;
      left: 12px;
      bottom: 12px;
      display: flex;
      gap: 8px;
      z-index: 2;
    }

    .allChip {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      background: #fff;
      color: #221a1d;
      border: 1px solid rgba(90, 14, 36, .12);
      min-height: 28px;
      padding: 4px 10px;
      font-size: 12px;
      font-weight: 500;
    }

    .allChip--time {
      gap: 5px;
    }

    .allCard__body {
      padding: 12px;
      display: grid;
      gap: 10px;
      align-content: start;
    }

    .allCard__row {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 8px;
      align-items: start;
    }

    .allCard__title {
      margin: 0;
      font-size: 17px;
      color: #2f1722;
      line-height: 1.15;
      font-weight: 600;
    }

    .allPricePill {
      border-radius: 999px;
      min-height: 40px;
      background: #efedef;
      color: #11141f;
      font-size: 12px;
      font-weight: 600;
      line-height: 1;
      padding: 8px 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
    }

    .allPeopleRow {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      min-height: 32px;
    }

    .allPeople {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 30px;
    }

    .allPeople__dots {
      display: flex;
      align-items: center;
      padding-left: 2px;
    }

    .allPeople__dots span {
      width: 22px;
      height: 22px;
      border-radius: 999px;
      border: 2px solid #fff;
      margin-left: -6px;
      box-shadow: 0 3px 9px rgba(0,0,0,.12);
      display: inline-block;
    }

    .allPeople__dots span:nth-child(1) { background: linear-gradient(135deg, #ff8ab6, #f05f96); margin-left: 0; }
    .allPeople__dots span:nth-child(2) { background: linear-gradient(135deg, #a1e6ff, #63c6f5); }
    .allPeople__dots span:nth-child(3) { background: linear-gradient(135deg, #c6b5ff, #8c7fff); }
    .allPeople__dots span:nth-child(4) { background: linear-gradient(135deg, #ffe0a6, #f4bf58); }

    .allPeople__count {
      font-size: 16px;
      font-weight: 600;
      color: #5A0E24;
      line-height: 1;
    }

    .allPeople__meta {
      margin-left: 2px;
      font-size: 11px;
      color: #6f5f66;
      font-weight: 500;
    }

    .allReviews {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      border: 1px solid rgba(90,14,36,.18);
      border-radius: 999px;
      background: rgba(90,14,36,.06);
      padding: 4px 9px;
      line-height: 1;
      white-space: nowrap;
    }

    .allReviews__star { color: #f59e0b; font-size: 12px; }
    .allReviews__count { color: #4e0d22; font-size: 13px; font-weight: 800; }
    .allReviews__label { color: #6f5f66; font-size: 10px; font-weight: 700; letter-spacing: .01em; }

    .allEmpty {
      margin-top: 12px;
      border: 1px dashed rgba(90, 14, 36, .28);
      border-radius: 14px;
      background: rgba(90, 14, 36, .05);
      padding: 16px;
      color: #6a4e59;
      text-align: center;
      display: none;
    }

    .allModal {
      position: fixed;
      inset: 0;
      z-index: 300;
      background: rgba(24, 9, 16, .55);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }

    .allModal.is-open { display: flex; }

    .allModal__card {
      width: min(560px, 100%);
      border-radius: 16px;
      border: 1px solid var(--line);
      background: #fff;
      box-shadow: 0 20px 46px rgba(90, 14, 36, .28);
      padding: 16px;
      display: grid;
      gap: 10px;
    }

    .allNotice {
      display: none;
      border: 1px solid #f2c9c9;
      background: #fff2f2;
      color: #a23a3a;
      border-radius: 10px;
      padding: 9px 10px;
      font-size: 13px;
    }

    .allModal__manual {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    @media (max-width: 1100px) {
      .allFilter {
        grid-template-columns: 1fr 1fr;
      }

      .allFilter__actions {
        grid-column: span 2;
      }

      .allGrid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 680px) {
      .allFilter {
        grid-template-columns: 1fr;
      }

      .allFilter__actions {
        grid-column: auto;
      }

      .allGrid {
        grid-template-columns: 1fr;
      }
    }
  </style>

  <div class="allWrap">
    <section class="allHead">
      <h1>Huduma zote</h1>
      <p>
        Angalia huduma zote zilizopo kwenye Glamo. Kwa kila huduma utaona idadi ya watoa huduma,
        na ukiwasha location utaona walio karibu ndani ya {{ $radiusKm }}km.
      </p>

      <div class="allHead__actions">
        <a class="allBtn allBtn--light" href="{{ route('landing') }}">Rudi nyumbani</a>
        <button class="allBtn allBtn--green" type="button" id="btnAskLoc">Washa location</button>
      </div>
    </section>

    <section>
      <div class="allFilter">
        <div class="allField allField--full">
          <label>Aina</label>
          <div class="allCategoryTabs" id="categoryTabs" aria-label="Aina za huduma">
            @foreach($categories as $category)
              @php
                $categorySlug = strtolower((string) $category->slug);
                $isDefaultCategory = $categorySlug === $defaultCategorySlug;
              @endphp
              <button
                class="allCategoryChip {{ $isDefaultCategory ? 'is-active' : '' }}"
                type="button"
                data-category-tab="{{ $categorySlug }}"
                aria-pressed="{{ $isDefaultCategory ? 'true' : 'false' }}"
              >
                {{ $category->name }}
              </button>
            @endforeach
          </div>
        </div>

        <div class="allField">
          <label for="serviceSearch">Tafuta huduma</label>
          <input class="allInput" id="serviceSearch" type="search" placeholder="Mfano: makeup, knotless, nails..." autocomplete="off">
        </div>

        <div class="allField">
          <label for="sortFilter">Panga</label>
          <select class="allSelect" id="sortFilter">
            <option value="default">Kawaida</option>
            <option value="price_asc">Bei ndogo kwanza</option>
            <option value="price_desc">Bei kubwa kwanza</option>
            <option value="name_asc">A-Z</option>
          </select>
        </div>

        <div class="allFilter__actions">
          <button class="allBtn allBtn--light" type="button" id="btnResetFilters">Reset</button>
        </div>
      </div>

      <div class="allGrid" id="servicesGrid">
        @foreach($services as $service)
          @php
            $categorySlug = $serviceCatSlug($service);
            $categoryName = $serviceCatName($service);

            $price = data_get($service, 'display_price');
            $price = is_numeric($price) ? (float) $price : (float) ($service->base_price ?? 0);

            $totalCount = (int) ($totalProvidersByService[$service->id] ?? 0);
            $nearCount = (int) ($countsByService[$service->id] ?? 0);
            $displayCount = $hasLocation ? $nearCount : $totalCount;
            $doneCount = (int) ($completedOrdersByService[$service->id] ?? 0);

            $durationMinutes = (int) (data_get($service, 'duration_minutes')
              ?? data_get($service, 'duration_mins')
              ?? data_get($service, 'duration')
              ?? 60);
            if ($durationMinutes <= 0) {
              $durationMinutes = 60;
            }
            $durationHours = intdiv($durationMinutes, 60);
            $durationRemain = $durationMinutes % 60;
            $durationLabel = $durationHours > 0
              ? ($durationRemain > 0 ? $durationHours.'h '.$durationRemain.'dk' : $durationHours.'h')
              : $durationMinutes.'dk';
            $cardImage = (string) (data_get($service, 'primary_image_url') ?: asset('images/placeholder.svg'));
          @endphp

          <a class="allCard svcCard"
             href="{{ route('services.show', ['category' => $categorySlug, 'service' => $service->slug]) }}"
             data-name="{{ strtolower((string) ($service->name.' '.$categoryName)) }}"
             data-category="{{ strtolower((string) $categorySlug) }}"
             data-price="{{ $price }}"
             data-providers="{{ $displayCount }}">
            <div class="allCard__img" style="background-image:url('{{ $cardImage }}');">
              <div class="allCard__chips">
                <span class="allChip allChip--time">{{ $durationLabel }}</span>
                <span class="allChip">{{ $categoryName }}</span>
              </div>
            </div>

            <div class="allCard__body">
              <div class="allCard__row">
                <h3 class="allCard__title">{{ $service->name }}</h3>
                <span class="allPricePill">TZS {{ $formatTzs($price) }}</span>
              </div>

              <div class="allPeopleRow">
                <div class="allPeople">
                  <span class="allPeople__dots" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                  </span>
                  <span class="allPeople__count">{{ $displayCount }}</span>
                  @if($hasLocation)
                    <span class="allPeople__meta">karibu {{ $radiusKm }}km</span>
                  @endif
                </div>
                <div class="allReviews" aria-label="Idadi ya waliopenda huduma">
                  <span class="allReviews__star">★</span>
                  <span class="allReviews__count">{{ number_format($doneCount) }}</span>
                  <span class="allReviews__label">{{ $doneCount === 1 ? 'Amependa' : 'Wamependa' }}</span>
                </div>
              </div>
            </div>
          </a>
        @endforeach
      </div>

      <div class="allEmpty" id="emptyState">Hakuna huduma iliyopatikana kulingana na filter ulizochagua.</div>
    </section>
  </div>

  <script>
    (function () {
      const search = document.getElementById('serviceSearch');
      const categoryTabs = Array.from(document.querySelectorAll('[data-category-tab]'));
      const sort = document.getElementById('sortFilter');
      const grid = document.getElementById('servicesGrid');
      const empty = document.getElementById('emptyState');
      const reset = document.getElementById('btnResetFilters');
      const defaultCategory = String(@json($defaultCategorySlug)).toLowerCase();

      let activeCategory = '';

      function setActiveCategory(slug) {
        const selected = String(slug || '').toLowerCase();
        activeCategory = selected;

        categoryTabs.forEach((tab) => {
          const tabSlug = String(tab.getAttribute('data-category-tab') || '').toLowerCase();
          const isActive = selected !== '' && tabSlug === selected;
          tab.classList.toggle('is-active', isActive);
          tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
      }

      const initialCategory = defaultCategory || String(categoryTabs[0]?.getAttribute('data-category-tab') || '').toLowerCase();
      setActiveCategory(initialCategory);

      function applyFilters() {
        if (!grid) return;

        const q = (search ? search.value : '').trim().toLowerCase();
        const c = (activeCategory || '').toLowerCase();
        const s = sort ? sort.value : 'default';

        const cards = Array.from(grid.querySelectorAll('.svcCard'));
        let visible = [];

        cards.forEach((card) => {
          const name = (card.getAttribute('data-name') || '').toLowerCase();
          const catv = (card.getAttribute('data-category') || 'other').toLowerCase();

          const okName = !q || name.includes(q);
          const okCat = !c || catv === c;
          const show = okName && okCat;

          card.style.display = show ? '' : 'none';
          if (show) visible.push(card);
        });

        if (s !== 'default') {
          const sorted = visible.slice().sort((a, b) => {
            const ap = parseFloat(a.getAttribute('data-price') || '0');
            const bp = parseFloat(b.getAttribute('data-price') || '0');
            const an = (a.getAttribute('data-name') || '');
            const bn = (b.getAttribute('data-name') || '');

            if (s === 'price_asc') return ap - bp;
            if (s === 'price_desc') return bp - ap;
            if (s === 'name_asc') return an.localeCompare(bn);
            return 0;
          });

          sorted.forEach((el) => grid.appendChild(el));
        }

        if (empty) {
          empty.style.display = visible.length ? 'none' : 'block';
        }
      }

      function resetFilters() {
        if (search) search.value = '';
        if (sort) sort.value = 'default';
        const fallbackCategory = defaultCategory || String(categoryTabs[0]?.getAttribute('data-category-tab') || '').toLowerCase();
        setActiveCategory(fallbackCategory);
        applyFilters();
      }

      search && search.addEventListener('input', applyFilters);
      categoryTabs.forEach((tab) => {
        tab.addEventListener('click', () => {
          setActiveCategory(tab.getAttribute('data-category-tab') || '');
          applyFilters();
        });
      });
      sort && sort.addEventListener('change', applyFilters);
      reset && reset.addEventListener('click', resetFilters);

      applyFilters();

      const btnAskLoc = document.getElementById('btnAskLoc');
      btnAskLoc && btnAskLoc.addEventListener('click', () => {
        if (window.GlamoGeoPrompt && typeof window.GlamoGeoPrompt.openPrompt === 'function') {
          window.GlamoGeoPrompt.openPrompt();
        }
      });
    })();
  </script>
</div>
@endsection
