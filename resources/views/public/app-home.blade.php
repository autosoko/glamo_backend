@extends('public.layout')

@section('title', 'Home - Glamo')

@section('content')
<section class="gh">
  <div class="container">

    {{-- TOP HEADER --}}
    <div class="gh__top">
      <div class="gh__brand">
        <div class="gh__logo">G</div>
        <div>
          <div class="gh__hello">Karibu Glamo ✨</div>
          <div class="muted">Chagua huduma, kisha utaona watoa huduma walio karibu (0–10km).</div>
        </div>
      </div>

      <div class="gh__actions">
        <button class="btn btn--ghost" type="button" id="btnAskLocTop">📍 Location</button>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button class="btn btn--ghost" type="submit">Ondoka</button>
        </form>
      </div>
    </div>

    {{-- HERO --}}
    <div class="heroCard">
      <div class="heroCard__bg"></div>

      <div class="heroCard__grid">
        <div class="heroCard__content">
          <div class="badge">✨ Urembo Ukiwa Nyumbani</div>

          <h1 class="heroCard__title">
            Suka, Makeup, Kubana Nywele & Massage
            <span class="script">kwa ustaarabu</span>
          </h1>

          <p class="heroCard__subtitle">
            Bonyeza huduma unayotaka. Ukiruhusu location, Glamo itaonyesha watoa huduma walio karibu zaidi.
          </p>

          <div class="heroCard__stats">
            <div class="stat">
              <div class="stat__num">0–10km</div>
              <div class="stat__label">Near you</div>
            </div>
            <div class="stat">
              <div class="stat__num">{{ is_countable($services) ? count($services) : 0 }}</div>
              <div class="stat__label">Huduma</div>
            </div>
            <div class="stat">
              <div class="stat__num">Fast</div>
              <div class="stat__label">Book ndani ya dakika</div>
            </div>
          </div>

          <div class="heroCard__cta">
            <a class="btn btn--primary" href="#services">Angalia Huduma</a>
            <button class="btn btn--ghost" type="button" id="btnAskLoc">Washa Location</button>
          </div>
        </div>

        {{-- RIGHT: LOCATION STATUS CARD --}}
        <div class="locCard">
          <div class="locCard__head">
            <div>
              <div class="locCard__title">📍 Location Status</div>
              @if(!empty($lat) && !empty($lng))
                <div class="muted">Imewekwa ✅</div>
              @else
                <div class="muted">Haijaruhusiwa</div>
              @endif
            </div>

            <span class="pill {{ (!empty($lat) && !empty($lng)) ? 'pill--ok' : 'pill--off' }}">
              {{ (!empty($lat) && !empty($lng)) ? 'ON' : 'OFF' }}
            </span>
          </div>

          <div class="locCard__body">
            @if(!empty($lat) && !empty($lng))
              <div class="locCoord">
                <span class="muted">Lat:</span> <b>{{ number_format($lat,4) }}</b>
                <span class="muted" style="margin-left:10px;">Lng:</span> <b>{{ number_format($lng,4) }}</b>
              </div>
              <div class="muted small" style="margin-top:8px;">
                Mfumo utaonyesha watoa huduma walio karibu ndani ya 0–10km.
              </div>
            @else
              <div class="muted">
                Ruhusu location ili uone watoa huduma walio karibu (nearby providers).
              </div>
            @endif

            <div class="locCard__actions">
              <button class="btn btn--primary btn--sm" type="button" id="btnOpenLoc">Ruhusu Location</button>
              <button class="btn btn--ghost btn--sm" type="button" id="btnHideLocTip">Sio sasa</button>
            </div>

            <div class="muted small" style="margin-top:10px;">
              * Tunatumia location kuonyesha walio karibu tu. Hatuonyeshi location yako kwa watu wengine.
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- FILTER BAR --}}
    <div class="filterBar">
      <div class="filterBar__left">
        <div class="filterItem">
          <label class="filterLabel">Tafuta huduma</label>
          <input class="filterInput" type="search" id="serviceSearch" placeholder="Mfano: Knotless, Gel, Massage..." autocomplete="off">
        </div>

        <div class="filterItem">
          <label class="filterLabel">Aina</label>
          <select class="filterSelect" id="categoryFilter">
            <option value="all">Zote</option>
            <option value="misuko">Misuko</option>
            <option value="makeup">Makeup</option>
            <option value="kubana">Kubana</option>
            <option value="massage">Massage</option>
          </select>
        </div>

        <div class="filterItem">
          <label class="filterLabel">Panga</label>
          <select class="filterSelect" id="sortFilter">
            <option value="default">Default</option>
            <option value="price_asc">Bei: Ndogo → Kubwa</option>
            <option value="price_desc">Bei: Kubwa → Ndogo</option>
            <option value="name_asc">A-Z</option>
          </select>
        </div>
      </div>

      <div class="filterBar__right">
        <div class="hint muted">
          @if(!empty($lat) && !empty($lng))
            Inaonyesha “nearby” counts ✅
          @else
            Washa location kuona “nearby” counts
          @endif
        </div>
      </div>
    </div>

    {{-- SERVICES GRID --}}
    <div id="services" class="svcGrid">
      @foreach($services as $service)
        @php
          // Defensive null-safe
          $categoryName = data_get($service, 'category.name') ?? 'Huduma';
          $categorySlug = data_get($service, 'category.slug') ?? data_get($service, 'category') ?? 'other';

          $short = $service->short_desc ?? null;
          $short = $short ?: 'Bonyeza kuona details na watoa huduma.';
          $price = $service->base_price ?? 0;

          $img = data_get($service, 'primary_image_url');

          $nearCount = $countsByService[$service->id] ?? null;
        @endphp

        <a href="#" class="svcCard"
           data-name="{{ strtolower($service->name ?? '') }}"
           data-category="{{ strtolower($categorySlug) }}"
           data-price="{{ (float)$price }}">
          <div class="svcCard__img" style="{{ $img ? "background-image:url('$img')" : "" }}">
            @if(!$img)
              <div class="svcCard__ph">
                <div class="svcCard__phInner">Glamo</div>
              </div>
            @endif
            <div class="svcCard__tag">{{ $categoryName }}</div>
          </div>

          <div class="svcCard__body">
            <div class="svcCard__title">{{ $service->name }}</div>

            <div class="svcCard__meta">
              <span class="price">TZS {{ number_format((float)$price, 0) }}</span>
              <span class="dot">•</span>

              @if(!empty($lat) && !empty($lng))
                <span class="muted">
                  <b>{{ (int)($nearCount ?? 0) }}</b> karibu
                </span>
              @else
                <span class="muted">Washa location</span>
              @endif
            </div>

            <div class="muted small svcCard__desc">{{ $short }}</div>
          </div>
        </a>
      @endforeach
    </div>

    {{-- Empty state --}}
    <div class="empty" id="emptyState" style="display:none;">
      <div class="empty__card">
        <div class="empty__title">Hakuna huduma iliyopatikana</div>
        <div class="muted">Badilisha filter au andika jina tofauti.</div>
        <button class="btn btn--primary" type="button" id="btnResetFilters">Reset Filters</button>
      </div>
    </div>

  </div>
</section>

{{-- LOCATION MODAL --}}
<div class="gmodal" id="locModal" aria-hidden="true">
  <div class="gmodal__backdrop" data-close="1"></div>
  <div class="gmodal__card" role="dialog" aria-modal="true">
    <div class="gmodal__head">
      <div>
        <div class="gmodal__title">Ruhusu Location</div>
        <div class="muted">Ili Glamo ikuonyeshe watoa huduma walio karibu.</div>
      </div>
      <button class="gmodal__x" type="button" data-close="1">✕</button>
    </div>

    <div class="gmodal__body">
      <div class="gmodal__info">
        <div class="gmodal__bullet">✅ Unaona “nearby providers” kwa kila huduma</div>
        <div class="gmodal__bullet">✅ Una-book haraka (waliopo karibu)</div>
        <div class="gmodal__bullet">🔒 Location yako haionyeshwi public</div>
      </div>

      <div class="gmodal__actions">
        <button class="btn btn--ghost" id="btnCloseLoc" type="button">Sio sasa</button>
        <button class="btn btn--primary" id="btnAllowLoc" type="button">Ruhusu Location</button>
      </div>

      <div class="muted small" id="locErr" style="margin-top:10px; display:none;"></div>
    </div>
  </div>
</div>

{{-- PAGE STYLES (keep here for copy-paste simplicity) --}}
<style>
  .gh{ padding:18px 0 34px; }
  .gh__top{ display:flex; align-items:flex-start; justify-content:space-between; gap:14px; margin-bottom:14px; }
  .gh__brand{ display:flex; align-items:center; gap:12px; }
  .gh__logo{ width:42px; height:42px; border-radius:14px; display:grid; place-items:center; font-weight:900; background:rgba(0,0,0,.06); }
  .gh__hello{ font-weight:950; font-size:1.25rem; }
  .gh__actions{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }

  .heroCard{ position:relative; border-radius:26px; overflow:hidden; border:1px solid rgba(0,0,0,.06); background:#fff; box-shadow:0 18px 45px rgba(0,0,0,.06); }
  .heroCard__bg{ position:absolute; inset:0; background: radial-gradient(circle at 20% 10%, rgba(0,0,0,.06), transparent 55%),
                                      radial-gradient(circle at 90% 20%, rgba(0,0,0,.05), transparent 50%),
                                      linear-gradient(180deg, rgba(0,0,0,.03), transparent); }
  .heroCard__grid{ position:relative; z-index:1; display:grid; grid-template-columns: 1.25fr .75fr; gap:14px; padding:18px; }
  @media (max-width: 980px){ .heroCard__grid{ grid-template-columns:1fr; } }

  .heroCard__title{ font-size: clamp(26px, 3.2vw, 40px); line-height:1.08; margin:10px 0; font-weight:950; }
  .heroCard__subtitle{ max-width: 65ch; }
  .heroCard__stats{ display:grid; grid-template-columns:repeat(3, 1fr); gap:10px; margin-top:12px; }
  @media (max-width: 720px){ .heroCard__stats{ grid-template-columns:1fr; } }
  .stat{ background:rgba(255,255,255,.75); border:1px solid rgba(0,0,0,.06); border-radius:18px; padding:12px; }
  .stat__num{ font-weight:950; font-size:1.1rem; }
  .stat__label{ font-size:.92rem; color: rgba(0,0,0,.65); }
  .heroCard__cta{ display:flex; gap:10px; flex-wrap:wrap; margin-top:14px; }

  .locCard{ background:rgba(255,255,255,.9); border:1px solid rgba(0,0,0,.06); border-radius:22px; padding:14px; }
  .locCard__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
  .locCard__title{ font-weight:950; }
  .pill{ padding:7px 12px; border-radius:999px; font-weight:900; font-size:.86rem; background:rgba(0,0,0,.06); }
  .pill--ok{ background: rgba(0,0,0,.08); }
  .pill--off{ background: rgba(0,0,0,.04); }
  .locCard__actions{ display:flex; gap:10px; flex-wrap:wrap; margin-top:12px; }
  .locCoord{ background:rgba(0,0,0,.04); border:1px solid rgba(0,0,0,.06); border-radius:14px; padding:10px; margin-top:10px; }

  .filterBar{ margin-top:14px; display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;
              background:rgba(0,0,0,.03); border:1px solid rgba(0,0,0,.06); border-radius:22px; padding:12px; }
  .filterBar__left{ display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; }
  .filterItem{ display:flex; flex-direction:column; gap:6px; min-width: 190px; }
  .filterLabel{ font-weight:900; font-size:.9rem; }
  .filterInput, .filterSelect{ height:44px; border-radius:14px; border:1px solid rgba(0,0,0,.12); padding:0 12px; background:#fff; }
  .filterInput{ width: 260px; max-width: 100%; }

  .svcGrid{ margin-top:14px; display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; }
  @media (max-width: 1100px){ .svcGrid{ grid-template-columns:repeat(3, 1fr); } }
  @media (max-width: 820px){ .svcGrid{ grid-template-columns:repeat(2, 1fr); } }
  @media (max-width: 520px){ .svcGrid{ grid-template-columns:1fr; } }

  .svcCard{ display:block; text-decoration:none; color:inherit; border-radius:22px; overflow:hidden;
            background:#fff; border:1px solid rgba(0,0,0,.06); box-shadow:0 16px 35px rgba(0,0,0,.05);
            transition: transform .15s ease, box-shadow .15s ease; }
  .svcCard:hover{ transform: translateY(-2px); box-shadow:0 22px 45px rgba(0,0,0,.08); }
  .svcCard__img{ position:relative; height:170px; background-size:cover; background-position:center; background-color:rgba(0,0,0,.05); }
  .svcCard__ph{ position:absolute; inset:0; display:grid; place-items:center; }
  .svcCard__phInner{ width:78px; height:78px; border-radius:22px; display:grid; place-items:center; font-weight:950; background:rgba(255,255,255,.85); border:1px solid rgba(0,0,0,.08); }
  .svcCard__tag{ position:absolute; left:10px; top:10px; padding:7px 10px; border-radius:999px;
                background: rgba(255,255,255,.9); border:1px solid rgba(0,0,0,.08); font-weight:900; font-size:.86rem; }
  .svcCard__body{ padding:14px; }
  .svcCard__title{ font-weight:950; line-height:1.15; }
  .svcCard__meta{ display:flex; align-items:center; gap:10px; margin-top:8px; flex-wrap:wrap; }
  .dot{ opacity:.35; }
  .svcCard__desc{ margin-top:10px; }

  .empty{ margin-top:18px; }
  .empty__card{ border:1px dashed rgba(0,0,0,.2); border-radius:22px; padding:18px; background:rgba(0,0,0,.02); display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
  .empty__title{ font-weight:950; font-size:1.1rem; }

  /* Modal */
  .gmodal{ position:fixed; inset:0; display:none; z-index:200; }
  .gmodal.is-open{ display:block; }
  .gmodal__backdrop{ position:absolute; inset:0; background:rgba(0,0,0,.45); }
  .gmodal__card{ position:relative; max-width:520px; margin:8vh auto; background:#fff; border-radius:22px; border:1px solid rgba(0,0,0,.08);
                box-shadow:0 30px 80px rgba(0,0,0,.25); overflow:hidden; }
  .gmodal__head{ display:flex; align-items:flex-start; justify-content:space-between; gap:10px; padding:16px; border-bottom:1px solid rgba(0,0,0,.06); }
  .gmodal__title{ font-weight:950; font-size:1.15rem; }
  .gmodal__x{ width:38px; height:38px; border-radius:14px; border:1px solid rgba(0,0,0,.12); background:#fff; font-weight:900; cursor:pointer; }
  .gmodal__body{ padding:16px; }
  .gmodal__info{ display:flex; flex-direction:column; gap:8px; background:rgba(0,0,0,.03); border:1px solid rgba(0,0,0,.06); border-radius:18px; padding:12px; }
  .gmodal__bullet{ font-weight:700; }
  .gmodal__actions{ display:flex; gap:10px; justify-content:flex-end; margin-top:14px; flex-wrap:wrap; }

  .btn--sm{ padding:10px 12px; font-size:.92rem; }
</style>

<script>
(function(){
  const modal = document.getElementById('locModal');
  const btnAsk = document.getElementById('btnAskLoc');
  const btnAskTop = document.getElementById('btnAskLocTop');
  const btnOpenLoc = document.getElementById('btnOpenLoc');
  const btnHideTip = document.getElementById('btnHideLocTip');
  const btnAllow = document.getElementById('btnAllowLoc');
  const btnClose = document.getElementById('btnCloseLoc');
  const locErr = document.getElementById('locErr');

  const search = document.getElementById('serviceSearch');
  const cat = document.getElementById('categoryFilter');
  const sort = document.getElementById('sortFilter');
  const grid = document.getElementById('services');
  const emptyState = document.getElementById('emptyState');
  const resetBtn = document.getElementById('btnResetFilters');

  function openModal(){ modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }

  async function saveLocation(lat, lng){
    const res = await fetch("{{ route('location.set') }}", {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': "{{ csrf_token() }}"
      },
      body: JSON.stringify({lat, lng})
    });
    if(!res.ok) throw new Error('Imeshindikana kuhifadhi location.');
  }

  function askLocation(){
    locErr.style.display = 'none';

    if(!navigator.geolocation){
      locErr.textContent = "Browser yako hai-support location.";
      locErr.style.display = 'block';
      return;
    }

    btnAllow.disabled = true;
    btnAllow.textContent = 'Inaomba ruhusa...';

    navigator.geolocation.getCurrentPosition(async (pos) => {
      try{
        await saveLocation(pos.coords.latitude, pos.coords.longitude);
        window.location.reload();
      }catch(e){
        locErr.textContent = e.message || 'Kuna hitilafu.';
        locErr.style.display = 'block';
      }finally{
        btnAllow.disabled = false;
        btnAllow.textContent = 'Ruhusu Location';
      }
    }, () => {
      btnAllow.disabled = false;
      btnAllow.textContent = 'Ruhusu Location';
      locErr.textContent = "Imeshindikana kupata location. Washa Location kwenye settings kisha jaribu tena.";
      locErr.style.display = 'block';
    }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
  }

  // open modal triggers
  btnAsk?.addEventListener('click', openModal);
  btnAskTop?.addEventListener('click', openModal);
  btnOpenLoc?.addEventListener('click', openModal);
  btnHideTip?.addEventListener('click', () => {});
  btnAllow?.addEventListener('click', askLocation);
  btnClose?.addEventListener('click', closeModal);

  // close by backdrop or X
  modal?.addEventListener('click', (e) => {
    const close = e.target?.getAttribute('data-close');
    if(close) closeModal();
  });

  // Auto open once if no loc
  const hasLoc = {{ (!empty($lat) && !empty($lng)) ? 'true' : 'false' }};
  if(!hasLoc){
    // Only auto-open if not dismissed before
    const dismissed = localStorage.getItem('glamo_loc_dismissed');
    if(!dismissed) openModal();
  }
  btnHideTip?.addEventListener('click', () => {
    localStorage.setItem('glamo_loc_dismissed', '1');
  });

  // Filtering client-side
  function applyFilters(){
    const q = (search?.value || '').trim().toLowerCase();
    const c = (cat?.value || 'all').toLowerCase();
    const s = (sort?.value || 'default');

    const cards = Array.from(grid.querySelectorAll('.svcCard'));

    // filter
    let visible = [];
    cards.forEach(card => {
      const name = card.getAttribute('data-name') || '';
      const catv = (card.getAttribute('data-category') || 'other').toLowerCase();
      const okName = !q || name.includes(q);
      const okCat = (c === 'all') || (catv === c);
      const show = okName && okCat;

      card.style.display = show ? '' : 'none';
      if(show) visible.push(card);
    });

    // sort visible only
    if(s !== 'default'){
      const sorted = visible.slice().sort((a,b) => {
        if(s === 'price_asc') return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
        if(s === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
        if(s === 'name_asc') return (a.dataset.name || '').localeCompare(b.dataset.name || '');
        return 0;
      });

      // append in sorted order
      sorted.forEach(el => grid.appendChild(el));
    }

    emptyState.style.display = visible.length ? 'none' : 'block';
  }

  search?.addEventListener('input', applyFilters);
  cat?.addEventListener('change', applyFilters);
  sort?.addEventListener('change', applyFilters);
  resetBtn?.addEventListener('click', () => {
    search.value = '';
    cat.value = 'all';
    sort.value = 'default';
    applyFilters();
  });

})();
</script>
@endsection
