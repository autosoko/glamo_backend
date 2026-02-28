@extends('public.layout')

@section('title', ($service->name ?? 'Huduma') . ' - Glamo')

@section('content')
@php
  $categoryRel = method_exists($service, 'getRelationValue') ? $service->getRelationValue('category') : null;
  $categorySlug = strtolower((string) (data_get($categoryRel, 'slug') ?? data_get($service, 'category') ?? 'other'));
  $categorySlug = $categorySlug !== '' ? $categorySlug : 'other';

  $categoryName = data_get($categoryRel, 'name');
  if (!$categoryName) {
    $catStr = trim((string) ($service->category ?? ''));
    $categoryName = $catStr !== '' ? ucfirst($catStr) : 'Huduma';
  }
  $servicePrice = (float) ($service->base_price ?? 0);
  $materials = (float) ($service->materials_price ?? 0);
  $usagePercent = (float) config('glamo_pricing.usage_percent', 5);

  $durationMin = (int) ($service->duration_minutes ?? 60);
  if ($durationMin <= 0) $durationMin = 60;
  $dh = intdiv($durationMin, 60);
  $dm = $durationMin % 60;
  $durationText = $dh > 0 ? ($dh.'h'.($dm ? ' '.$dm.'m' : '')) : ($dm.'m');

  $imgs = method_exists($service, 'imageUrls')
    ? $service->imageUrls(10)
    : [asset('images/placeholder.svg')];

  $firstProvider = ($providers ?? collect())->first();
  $firstProviderId = $firstProvider ? (int) ($firstProvider->id ?? 0) : null;
  $firstProviderName = $firstProvider ? (data_get($firstProvider, 'display_name') ?: 'Mtoa huduma') : null;
  $checkoutBaseUrl = url('/huduma/'.$categorySlug.'/'.$service->slug.'/checkout');
  $resumeQuery = [
    'resume' => 1,
    'service_ids' => (string) ((int) $service->id),
  ];
  if ($firstProviderId) {
    $resumeQuery['provider'] = $firstProviderId;
  }
  $hairWashEnabled = (bool) data_get($hairWash ?? [], 'enabled', false);
  $hairWashPrice = (float) data_get($hairWash ?? [], 'price', 0);
  $hairWashDefaultSelected = (bool) data_get($hairWash ?? [], 'selected', false);
  if ($hairWashEnabled) {
    $resumeQuery['include_hair_wash'] = $hairWashDefaultSelected ? 1 : 0;
  }
  $checkoutResumeUrlDefault = route('services.checkout', array_merge([
    'category' => $categorySlug,
    'service' => $service->slug,
  ], $resumeQuery));
  $hairWashInitialAmount = $hairWashEnabled && $hairWashDefaultSelected ? $hairWashPrice : 0;
  $initial = $baseBreakdown ?? [
    'service' => $servicePrice,
    'materials' => $materials,
    'travel' => null,
    'usage' => round(($servicePrice * $usagePercent) / 100, 2),
    'hair_wash' => $hairWashInitialAmount,
    'total' => $servicePrice + $materials + round(($servicePrice * $usagePercent) / 100, 2) + $hairWashInitialAmount,
    'usage_percent' => $usagePercent,
  ];

  if ($firstProvider) {
    $initial = [
      'service' => (float) ($firstProvider->calc_service_price ?? $servicePrice),
      'materials' => (float) ($firstProvider->calc_materials_price ?? $materials),
      'travel' => $firstProvider->calc_travel_price !== null ? (float) $firstProvider->calc_travel_price : null,
      'usage' => (float) ($firstProvider->calc_usage_price ?? 0),
      'hair_wash' => (float) ($firstProvider->calc_hair_wash_amount ?? $hairWashInitialAmount),
      'total' => (float) ($firstProvider->calc_total_price ?? 0),
      'usage_percent' => (float) ($firstProvider->calc_usage_percent ?? $usagePercent),
    ];
  }

  $startingPrice = (float) ($initial['total'] ?? 0);
  if (($providers ?? collect())->count() > 0) {
    $startingPrice = (float) collect($providers)
      ->map(fn ($p) => (float) ($p->calc_total_price ?? 0))
      ->filter(fn ($n) => $n > 0)
      ->min();

    if ($startingPrice <= 0) {
      $startingPrice = (float) ($initial['total'] ?? 0);
    }
  }

@endphp

<section class="section">
  <div class="container svcShow">
    <a class="backLink" href="{{ route('landing') }}#services">&lt;- Rudi kwenye huduma</a>

    <div class="svcShow__head">
      <div class="svcShow__titleRow">
        <div>
          <div class="muted small">{{ $categoryName }}</div>
          <h1 class="svcShow__title">{{ $service->name }}</h1>
        </div>

        <div class="svcShow__pills">
          <span class="pill pill--soft">Muda: {{ $durationText }}</span>
          <span class="pill pill--soft" id="topTotalPill">TZS {{ number_format((float) ($initial['total'] ?? 0), 0) }}</span>
        </div>
      </div>

      @if(!empty($service->short_desc))
        <p class="svcShow__subtitle muted">{{ $service->short_desc }}</p>
      @endif

      <div class="svcShow__quick">
        <div class="svcShow__quickItem">
          <span class="muted small">Bei kuanzia</span>
          <strong>TZS {{ number_format($startingPrice, 0) }}</strong>
        </div>
        <div class="svcShow__quickItem">
          <span class="muted small">Muda wa huduma</span>
          <strong>{{ $durationText }}</strong>
        </div>
        <div class="svcShow__quickItem">
          <span class="muted small">Watoa huduma</span>
          <strong>{{ number_format((int) (($providers ?? collect())->count())) }}</strong>
        </div>
      </div>
    </div>

    <div class="svcShow__grid">

      {{-- LEFT --}}
      <div class="svcShow__gallery">
        {{-- GALLERY --}}
        <div class="card card--soft svcGallery" data-gallery>
          <div class="svcGallery__main">
            <button class="iconBtn" type="button" data-gallery-prev aria-label="Previous image">&lt;</button>
            <button class="iconBtn" type="button" data-gallery-next aria-label="Next image">&gt;</button>

            <div class="svcGallery__slides">
              @foreach($imgs as $i => $url)
                <div class="svcGallery__slide {{ $i === 0 ? 'is-active' : '' }}" data-gallery-slide="{{ $i }}">
                  <img src="{{ $url }}" alt="{{ $service->name }} ({{ $i + 1 }})" loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                </div>
              @endforeach
            </div>
          </div>

          <div class="svcGallery__thumbs" aria-label="Images">
            @foreach($imgs as $i => $url)
              <button class="svcGallery__thumb {{ $i === 0 ? 'is-active' : '' }}" type="button" data-gallery-thumb="{{ $i }}" aria-label="Image {{ $i + 1 }}">
                <img src="{{ $url }}" alt="" loading="lazy">
              </button>
            @endforeach
          </div>
        </div>
      </div>

      {{-- RIGHT --}}
      <aside class="svcShow__right">
        <div class="card card--soft priceCard priceCard--sheet is-collapsed" id="priceCard" data-price-sheet>
          <button class="priceSheet__toggle" type="button" data-price-toggle aria-expanded="false" aria-controls="priceSheetBody" aria-label="Onyesha breakdown">
            <span class="priceSheet__handle" aria-hidden="true"></span>
            <span class="priceSheet__chev" aria-hidden="true">^</span>
          </button>

          @auth
            @php
              $bookAction = $firstProviderId
                ? route('services.checkout.start', ['category' => $categorySlug, 'service' => $service->slug, 'provider' => $firstProviderId])
                : '#';
            @endphp

            <form method="POST" id="bookForm" action="{{ $bookAction }}">
              @csrf
              <input type="hidden" name="service_ids[]" value="{{ (int) $service->id }}" data-primary-service>
              <div id="extraInputs"></div>
              @if($hairWashEnabled)
                <input type="hidden" name="include_hair_wash" id="includeHairWashInput" value="{{ $hairWashDefaultSelected ? '1' : '0' }}">
              @endif

              <div class="priceSheet__body" id="priceSheetBody">
                <div class="priceCard__head">
                  <div>
                    <div class="priceCard__title">Muhtasari wa gharama</div>
                    <div class="muted small">Makadirio ya awali, yanaweza kubadilika kidogo.</div>
                  </div>
                  <span class="pill pill--soft" id="providerPill">Mtoa huduma: {{ $firstProviderName ?: 'Chagua' }}</span>
                </div>

                <div class="priceRows">
                  <div class="priceRow">
                    <div class="priceRow__label">Gharama ya huduma</div>
                    <div class="priceRow__val" id="bdService">TZS {{ number_format((float) ($initial['service'] ?? 0), 0) }}</div>
                  </div>
                  <div class="priceRow">
                    <div class="priceRow__label">Vifaa & material</div>
                    <div class="priceRow__val" id="bdMaterials">TZS {{ number_format((float) ($initial['materials'] ?? 0), 0) }}</div>
                  </div>
                  @if($hasLocation)
                    <div class="priceRow">
                      <div class="priceRow__label">Usafiri</div>
                      <div class="priceRow__val" id="bdTravel">
                        @if($initial['travel'] === null)
                          <span class="muted small">Chagua mtoa huduma</span>
                        @else
                          TZS {{ number_format((float) $initial['travel'], 0) }}
                        @endif
                      </div>
                    </div>
                  @endif
                  <div class="priceRow">
                    <div class="priceRow__label">Matumizi</div>
                    <div class="priceRow__val" id="bdUsage">TZS {{ number_format((float) ($initial['usage'] ?? 0), 0) }}</div>
                  </div>
                  @if($hairWashEnabled)
                    <div class="priceRow">
                      <div class="priceRow__label">Kuosha nywele (hiari)</div>
                      <div class="priceRow__val">
                        <label style="display:inline-flex;align-items:center;gap:8px;">
                          <input type="checkbox" id="hairWashToggle" {{ $hairWashDefaultSelected ? 'checked' : '' }}>
                          <span id="bdHairWash">+TZS {{ number_format((float) ($initial['hair_wash'] ?? 0), 0) }}</span>
                        </label>
                      </div>
                    </div>
                  @endif
                </div>

                <div class="priceExtras" id="extrasBox" data-extras>
                  <div class="extrasHead">
                    <div class="priceExtras__title">Ongeza huduma (hiari)</div>
                    <button class="btn btn--ghost btn--sm" type="button" data-extras-toggle aria-expanded="false">Onyesha</button>
                  </div>
                  <div class="muted small">Chagua huduma nyingine unazotaka afanye mtoa huduma huyu.</div>

                  <div class="extrasBody" data-extras-body style="display:none">
                    <div class="extrasRecs" id="extrasRecs"></div>
                  </div>

                  <button class="btn btn--ghost btn--sm wfull" type="button" data-open-all-services style="margin-top:10px">
                    Tazama huduma zote
                  </button>

                  <div class="muted small" id="extrasEmpty" style="display:none;margin-top:6px;">Hakuna mapendekezo kwa sasa.</div>
                </div>
              </div>

              <div class="priceSheet__footer">
                <div class="priceTotal">
                  <div class="priceTotal__label">Jumla ya kulipa</div>
                  <div class="priceTotal__val" id="bdTotal">TZS {{ number_format((float) ($initial['total'] ?? 0), 0) }}</div>
                </div>

                <button class="btn btn--primary wfull" id="btnBookNow" type="submit" {{ (!$hasLocation || !$firstProviderId) ? 'disabled' : '' }}>
                  Endelea checkout
                </button>

                @if(!$hasLocation)
                  <div class="muted small" style="margin-top:8px;">Washa location kwanza ili uendelee ku-book.</div>
                @elseif(!$firstProviderId)
                  <div class="muted small" style="margin-top:8px;">Hakuna mtoa huduma wa kuchagua kwa sasa.</div>
                @endif
              </div>
            </form>
          @else
            <div class="priceSheet__body" id="priceSheetBody">
              <div class="priceCard__head">
                <div>
                  <div class="priceCard__title">Muhtasari wa gharama</div>
                  <div class="muted small">Makadirio ya awali, yanaweza kubadilika kidogo.</div>
                </div>
                <span class="pill pill--soft" id="providerPill">Mtoa huduma: {{ $firstProviderName ?: 'Chagua' }}</span>
              </div>

              <div class="priceRows">
                <div class="priceRow">
                  <div class="priceRow__label">Gharama ya huduma</div>
                  <div class="priceRow__val" id="bdService">TZS {{ number_format((float) ($initial['service'] ?? 0), 0) }}</div>
                </div>
                <div class="priceRow">
                  <div class="priceRow__label">Vifaa & material</div>
                  <div class="priceRow__val" id="bdMaterials">TZS {{ number_format((float) ($initial['materials'] ?? 0), 0) }}</div>
                </div>
                @if($hasLocation)
                  <div class="priceRow">
                    <div class="priceRow__label">Usafiri</div>
                    <div class="priceRow__val" id="bdTravel">
                      @if($initial['travel'] === null)
                        <span class="muted small">Chagua mtoa huduma</span>
                      @else
                        TZS {{ number_format((float) $initial['travel'], 0) }}
                      @endif
                    </div>
                  </div>
                @endif
                <div class="priceRow">
                  <div class="priceRow__label">Matumizi</div>
                  <div class="priceRow__val" id="bdUsage">TZS {{ number_format((float) ($initial['usage'] ?? 0), 0) }}</div>
                </div>
                @if($hairWashEnabled)
                  <div class="priceRow">
                    <div class="priceRow__label">Kuosha nywele (hiari)</div>
                    <div class="priceRow__val">
                      <label style="display:inline-flex;align-items:center;gap:8px;">
                        <input type="checkbox" id="hairWashToggle" {{ $hairWashDefaultSelected ? 'checked' : '' }}>
                        <span id="bdHairWash">+TZS {{ number_format((float) ($initial['hair_wash'] ?? 0), 0) }}</span>
                      </label>
                    </div>
                  </div>
                @endif
              </div>

              <div class="priceExtras" id="extrasBox" data-extras>
                <div class="extrasHead">
                  <div class="priceExtras__title">Ongeza huduma (hiari)</div>
                  <button class="btn btn--ghost btn--sm" type="button" data-extras-toggle aria-expanded="false">Onyesha</button>
                </div>
                <div class="muted small">Chagua huduma nyingine unazotaka afanye mtoa huduma huyu.</div>

                <div class="extrasBody" data-extras-body style="display:none">
                  <div class="extrasRecs" id="extrasRecs"></div>
                </div>

                <button class="btn btn--ghost btn--sm wfull" type="button" data-open-all-services style="margin-top:10px">
                  Tazama huduma zote
                </button>

                <div class="muted small" id="extrasEmpty" style="display:none;margin-top:6px;">Hakuna mapendekezo kwa sasa.</div>
              </div>
            </div>

            <div class="priceSheet__footer">
              <div class="priceTotal">
                <div class="priceTotal__label">Jumla ya kulipa</div>
                <div class="priceTotal__val" id="bdTotal">TZS {{ number_format((float) ($initial['total'] ?? 0), 0) }}</div>
              </div>

              <a class="btn btn--primary wfull" id="btnBookLogin" href="{{ route('login', ['redirect' => $checkoutResumeUrlDefault]) }}">Ingia ili u-book</a>
              <div class="muted small" style="margin-top:8px;">
                Huna akaunti?
                <a id="btnBookRegister" href="{{ route('register', ['redirect' => $checkoutResumeUrlDefault]) }}">Jisajili</a>
              </div>
            </div>
          @endauth

        </div>
      </aside>

      <div class="svcShow__content">
        {{-- PROVIDERS --}}
        <div class="svcSection">
          <div class="svcSection__head">
            <h2 class="svcSection__title">Watoa huduma karibu yako</h2>
            <div class="muted small">
              @if($hasLocation)
                Tunatumia location yako kuonyesha walio karibu ({{ (int) config('glamo_pricing.radius_km', 10) }}km).
              @else
                Washa location ili tuonyeshe walio karibu zaidi.
              @endif
            </div>
          </div>

          @if($errors->has('provider'))
            <div class="err" style="margin-top:10px;">{{ $errors->first('provider') }}</div>
          @endif

          @if(!$hasLocation)
            <div class="notice">
              <div class="notice__body">
                <div class="notice__title">Washa Location</div>
                <div class="muted small">
                  Tukipata location, tutaonyesha watoa huduma waliopo karibu na wewe na tutakokotoa gharama ya usafiri.
                </div>
              </div>
              <div class="notice__actions">
                <button class="btn btn--primary btn--sm" type="button" id="btnAskLocSvc">Ruhusu Location</button>
              </div>

              <div class="muted small" id="locErrSvc" style="display:none;margin-top:8px;"></div>
            </div>
          @endif

          @if(($providers ?? collect())->count() === 0)
            <div class="emptyBlock">
              <div class="emptyBlock__title">Hakuna mtoa huduma aliye karibu kwa sasa</div>
              <div class="muted small">Jaribu tena baadae au badilisha location.</div>
            </div>
          @else
            <div class="provGrid" id="providerGrid">
              @foreach($providers as $p)
                @php
                  $pName = data_get($p, 'display_name') ?: 'Mtoa huduma';
                  $pRating = data_get($p, 'rating_avg');
                  $pRatingText = is_numeric($pRating) ? number_format((float)$pRating, 1) : null;
                  $pOrders = (int) (data_get($p, 'total_orders') ?? 0);
                  $distanceKm = $p->calc_distance_km;
                  $pImg = (string) (data_get($p, 'profile_image_url') ?: asset('images/placeholder.svg'));
                @endphp

                <div class="provCard {{ $loop->first ? 'is-selected' : '' }}"
                     data-provider-card
                     data-provider-id="{{ $p->id }}"
                     data-service="{{ (float) ($p->calc_service_price ?? 0) }}"
                     data-materials="{{ (float) ($p->calc_materials_price ?? 0) }}"
                     data-usage-percent="{{ (float) ($p->calc_usage_percent ?? 0) }}"
                     data-usage="{{ (float) ($p->calc_usage_price ?? 0) }}"
                     data-travel="{{ $p->calc_travel_price !== null ? (float) $p->calc_travel_price : '' }}"
                     data-total="{{ (float) ($p->calc_total_price ?? 0) }}"
                     data-distance="{{ $distanceKm !== null ? (float) $distanceKm : '' }}">
                  <div class="provCard__body">
                    <div class="provCard__row">
                      <img class="provCard__avatar" src="{{ $pImg }}" alt="{{ $pName }}" loading="lazy">
                      <div class="provCard__main">
                        <div class="provCard__top">
                          <div class="provCard__name">{{ $pName }}</div>
                          <div class="provCard__jobs" aria-label="Kazi alizofanya">
                            <span class="provCard__jobsLabel">Kazi alizofanya</span>
                            <span class="provCard__jobsNum">{{ number_format($pOrders) }}</span>
                          </div>
                        </div>

                        <div class="provCard__meta muted small">
                          <span class="provStars" aria-hidden="true">***</span>
                          <span>{{ $pRatingText ?: 'New' }}</span>
                          <span>|</span>
                          @if($distanceKm !== null)
                            <span>{{ number_format((float)$distanceKm, 1) }} km</span>
                          @else
                            <span>Distance haijulikani</span>
                          @endif
                        </div>
                      </div>
                    </div>

                    <div class="provCard__price">
                      <div class="muted small">Jumla (estimate)</div>
                      <div class="provCard__priceNum">TZS {{ number_format((float) ($p->calc_total_price ?? 0), 0) }}</div>
                    </div>

                    <div class="provCard__actions">
                      <button class="btn btn--ghost btn--sm wfull" type="button" data-select-provider>
                        Chagua mtoa huduma huyu
                      </button>
                    </div>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      {{-- RELATED --}}
      @if(($related ?? collect())->count() > 0)
        <div class="svcShow__related">
          <div class="svcSection">
            <div class="svcSection__head">
              <h2 class="svcSection__title">Huduma zinazofanana</h2>
              <div class="muted small">Kutoka category hii hii</div>
            </div>

            <div class="relGrid">
              @foreach($related as $s)
                @php
                  $img = null;
                  $catRel = method_exists($s, 'getRelationValue') ? $s->getRelationValue('category') : null;
                  $catSlug = strtolower((string) (data_get($catRel, 'slug') ?? data_get($s, 'category') ?? 'other'));
                  $catSlug = $catSlug !== '' ? $catSlug : 'other';

                  $img = (string) (data_get($s, 'primary_image_url') ?: asset('images/placeholder.svg'));
                  $price = data_get($s, 'display_price');
                  $price = is_numeric($price) ? (float) $price : (float) ($s->base_price ?? 0);
                @endphp

                <a class="relCard" href="{{ route('services.show', ['category' => $catSlug, 'service' => $s->slug]) }}">
                  <div class="relCard__img" style="background-image:url('{{ $img }}')"></div>
                  <div class="relCard__body">
                    <div class="relCard__name">{{ $s->name }}</div>
                    <div class="muted small">TZS {{ number_format($price, 0) }}</div>
                  </div>
                </a>
              @endforeach
            </div>
          </div>
        </div>
      @endif
      </div>

    </div>
  </div>
</section>

{{-- Modals --}}
<div class="modal" id="allServicesModal" aria-hidden="true">
  <div class="modal__card modal__card--wide" role="dialog" aria-modal="true" aria-labelledby="allServicesTitle">
    <div class="modalHead">
      <div class="modal__title" id="allServicesTitle">Chagua huduma nyingine</div>
      <button class="btn btn--ghost btn--sm" type="button" data-allsvc-close>Funga</button>
    </div>

    <input class="input" id="allSvcSearch" placeholder="Tafuta huduma..." autocomplete="off">
    <div class="allSvcList" id="allSvcList"></div>

    <div class="modal__actions">
      <button class="btn btn--primary" type="button" data-allsvc-done>Sawa</button>
    </div>
  </div>
</div>

<div class="modal" id="svcPreviewModal" aria-hidden="true">
  <div class="modal__card modal__card--wide" role="dialog" aria-modal="true" aria-labelledby="svcPreviewTitle">
    <div class="modalHead">
      <div class="modal__title" id="svcPreviewTitle">Huduma</div>
      <button class="btn btn--ghost btn--sm" type="button" data-preview-close>Funga</button>
    </div>

    <div class="svcPrev">
      <button class="svcPrev__nav svcPrev__nav--prev" type="button" data-preview-prev aria-label="Prev">&lt;</button>
      <button class="svcPrev__nav svcPrev__nav--next" type="button" data-preview-next aria-label="Next">&gt;</button>
      <img class="svcPrev__img" id="svcPreviewImg" src="" alt="">
    </div>

    <div class="svcPrevThumbs" id="svcPreviewThumbs"></div>
  </div>
</div>

<script>
(() => {
  // Gallery
  const root = document.querySelector('[data-gallery]');
  if (root) {
    const slides = Array.from(root.querySelectorAll('[data-gallery-slide]'));
    const thumbs = Array.from(root.querySelectorAll('[data-gallery-thumb]'));
    const prev = root.querySelector('[data-gallery-prev]');
    const next = root.querySelector('[data-gallery-next]');
    let idx = 0;

    function setActive(i) {
      if (!slides.length) return;
      idx = (i + slides.length) % slides.length;
      slides.forEach((s) => s.classList.toggle('is-active', String(s.dataset.gallerySlide) === String(idx)));
      thumbs.forEach((t) => t.classList.toggle('is-active', String(t.dataset.galleryThumb) === String(idx)));
    }

    thumbs.forEach((t) => t.addEventListener('click', () => setActive(parseInt(t.dataset.galleryThumb || '0', 10))));
    prev?.addEventListener('click', () => setActive(idx - 1));
    next?.addEventListener('click', () => setActive(idx + 1));
    setActive(0);
  }

  // Provider select -> update breakdown
  const bdService = document.getElementById('bdService');
  const bdMaterials = document.getElementById('bdMaterials');
  const bdTravel = document.getElementById('bdTravel');
  const bdUsage = document.getElementById('bdUsage');
  const bdHairWash = document.getElementById('bdHairWash');
  const bdTotal = document.getElementById('bdTotal');
  const topTotalPill = document.getElementById('topTotalPill');
  const providerPill = document.getElementById('providerPill');
  const bookForm = document.getElementById('bookForm');
  const hairWashToggle = document.getElementById('hairWashToggle');
  const includeHairWashInput = document.getElementById('includeHairWashInput');
  const checkoutBase = @json($checkoutBaseUrl);
  const hasLoc = {{ $hasLocation ? 'true' : 'false' }};
  const primaryServiceId = {{ (int) $service->id }};
  const primaryServiceName = @json((string) ($service->name ?? ''));
  const hairWashEnabled = {{ $hairWashEnabled ? 'true' : 'false' }};
  const hairWashPrice = {{ (float) $hairWashPrice }};
  const providerServices = @json($providerServicesMap ?? []);
  const servicePreview = @json($servicePreviewMap ?? []);
  const extrasRecs = document.getElementById('extrasRecs');
  const extrasEmpty = document.getElementById('extrasEmpty');
  const extrasToggle = document.querySelector('[data-extras-toggle]');
  const extrasBody = document.querySelector('[data-extras-body]');
  const btnOpenAll = document.querySelector('[data-open-all-services]');
  const extraInputs = document.getElementById('extraInputs');
  const btnBookLogin = document.getElementById('btnBookLogin');
  const btnBookRegister = document.getElementById('btnBookRegister');
  const loginBaseUrl = @json(route('login'));
  const registerBaseUrl = @json(route('register'));
  const defaultResumeUrl = @json($checkoutResumeUrlDefault);

  const allServicesModal = document.getElementById('allServicesModal');
  const allSvcList = document.getElementById('allSvcList');
  const allSvcSearch = document.getElementById('allSvcSearch');
  const allSvcClose = allServicesModal?.querySelector('[data-allsvc-close]');
  const allSvcDone = allServicesModal?.querySelector('[data-allsvc-done]');

  const svcPreviewModal = document.getElementById('svcPreviewModal');
  const svcPreviewTitle = document.getElementById('svcPreviewTitle');
  const svcPreviewImg = document.getElementById('svcPreviewImg');
  const svcPreviewThumbs = document.getElementById('svcPreviewThumbs');
  const svcPreviewClose = svcPreviewModal?.querySelector('[data-preview-close]');
  const svcPreviewPrev = svcPreviewModal?.querySelector('[data-preview-prev]');
  const svcPreviewNext = svcPreviewModal?.querySelector('[data-preview-next]');

  let selectedExtraIds = new Set();
  let activeCard = null;
  let allSvcOptions = [];
  let previewImages = [];
  let previewIdx = 0;

  function fmtTZS(n) {
    try {
      return 'TZS ' + Math.round(Number(n || 0)).toLocaleString('en-US');
    } catch {
      return 'TZS ' + Math.round(Number(n || 0));
    }
  }

  function escHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getProviderOptions(providerId) {
    const key = String(providerId || '');
    return (providerServices && (providerServices[key] || providerServices[providerId])) || [];
  }

  function getMeta(serviceId) {
    const key = String(serviceId || '');
    return (servicePreview && (servicePreview[key] || servicePreview[serviceId])) || null;
  }

  function serviceGroup(serviceId, fallbackName = '') {
    const meta = getMeta(serviceId) || {};
    const slug = String(meta.category_slug || '').toLowerCase();
    const name = String(meta.name || fallbackName || '').toLowerCase();
    const hay = `${slug} ${name}`;

    if (/(makeup|make-up|vipodozi)/.test(hay)) return 'makeup';
    if (/(kucha|nail|manicure|pedicure)/.test(hay)) return 'nails';
    if (/(msuko|misuko|nywele|hair|braid|twist|weave|dread|lock|wig)/.test(hay)) return 'hair';

    return 'other';
  }

  const primaryGroup = serviceGroup(primaryServiceId, primaryServiceName);
  const groupLabels = { hair: 'Misuko', nails: 'Rangi kucha', makeup: 'Makeup' };
  const groupMax = { hair: 2, nails: 1, makeup: 1 };
  function recommendedGroupsFor(g) {
    if (g === 'makeup') return ['hair', 'nails'];
    if (g === 'hair') return ['nails', 'makeup'];
    if (g === 'nails') return ['hair', 'makeup'];
    return ['hair', 'nails'];
  }

  function setExtrasExpanded(expanded) {
    if (!extrasBody || !extrasToggle) return;
    extrasBody.style.display = expanded ? 'block' : 'none';
    extrasToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    extrasToggle.textContent = expanded ? 'Ficha' : 'Onyesha';
  }

  function openModal(el) {
    if (!el) return;
    el.classList.add('is-open');
    el.setAttribute('aria-hidden', 'false');
  }

  function closeModal(el) {
    if (!el) return;
    el.classList.remove('is-open');
    el.setAttribute('aria-hidden', 'true');
  }

  function syncExtraInputs() {
    if (!extraInputs) return;
    extraInputs.innerHTML = '';
    Array.from(selectedExtraIds).forEach((id) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'service_ids[]';
      input.value = String(id);
      extraInputs.appendChild(input);
    });
  }

  function isHairWashSelected() {
    if (!hairWashEnabled) return false;
    if (!hairWashToggle) return false;
    return !!hairWashToggle.checked;
  }

  function syncHairWashInput() {
    if (!includeHairWashInput) return;
    includeHairWashInput.value = isHairWashSelected() ? '1' : '0';
  }

  function buildResumeCheckoutUrl() {
    const providerId = activeCard?.getAttribute('data-provider-id');
    if (!providerId) return defaultResumeUrl || window.location.href;

    const params = new URLSearchParams();
    params.set('resume', '1');
    params.set('provider', String(providerId));
    params.set('service_ids', [String(primaryServiceId), ...Array.from(selectedExtraIds)].join(','));
    if (hairWashEnabled) {
      params.set('include_hair_wash', isHairWashSelected() ? '1' : '0');
    }

    return `${checkoutBase}?${params.toString()}`;
  }

  function updateGuestAuthLinks() {
    if (!btnBookLogin && !btnBookRegister) return;
    const resumeUrl = buildResumeCheckoutUrl();
    const encoded = encodeURIComponent(resumeUrl);
    if (btnBookLogin) btnBookLogin.href = `${loginBaseUrl}?redirect=${encoded}`;
    if (btnBookRegister) btnBookRegister.href = `${registerBaseUrl}?redirect=${encoded}`;
  }

  function syncExtraUI() {
    document.querySelectorAll('[data-toggle-extra]').forEach((btn) => {
      const id = String(btn.getAttribute('data-toggle-extra') || '');
      const selected = id && selectedExtraIds.has(id);
      btn.textContent = selected ? 'Ondoa' : 'Ongeza';
      btn.closest('.recCard')?.classList.toggle('is-selected', selected);
    });

    document.querySelectorAll('[data-allsvc-check]').forEach((cb) => {
      const id = String(cb.value || '');
      cb.checked = id && selectedExtraIds.has(id);
    });
  }

  function toggleExtra(serviceId) {
    const id = String(serviceId || '');
    if (!id || id === String(primaryServiceId)) return;
    if (selectedExtraIds.has(id)) selectedExtraIds.delete(id);
    else selectedExtraIds.add(id);
    recalcBreakdown();
  }

  function renderExtraServices(providerId) {
    const options = getProviderOptions(providerId).filter((o) => String(o.id) !== String(primaryServiceId));

    if (btnOpenAll) btnOpenAll.style.display = options.length ? '' : 'none';

    if (!extrasRecs) return;
    extrasRecs.innerHTML = '';

    if (!options.length) {
      if (extrasEmpty) extrasEmpty.style.display = 'block';
      if (extrasBody) extrasBody.style.display = 'none';
      if (extrasToggle) extrasToggle.style.display = 'none';
      return;
    }

    const wanted = recommendedGroupsFor(primaryGroup);
    const groups = wanted.map((g) => {
      const items = options.filter((o) => serviceGroup(o.id, o.name) === g).slice(0, groupMax[g] || 1);
      return { key: g, title: groupLabels[g] || 'Huduma', items };
    }).filter((g) => g.items.length);

    const fallbackItems = options
      .filter((o) => serviceGroup(o.id, o.name) !== primaryGroup)
      .slice(0, 3);

    const finalGroups = groups.length
      ? groups
      : (fallbackItems.length ? [{ key: 'other', title: 'Mapendekezo', items: fallbackItems }] : []);

    if (!finalGroups.length) {
      if (extrasEmpty) extrasEmpty.style.display = 'block';
      if (extrasBody) extrasBody.style.display = 'none';
      if (extrasToggle) extrasToggle.style.display = 'none';
      return;
    }

    if (extrasEmpty) extrasEmpty.style.display = 'none';
    if (extrasToggle) extrasToggle.style.display = '';

    const html = finalGroups.map((g) => {
      const cards = g.items.map((o) => {
        const id = String(o.id || '');
        const meta = getMeta(id) || {};
        const thumb = escHtml(String((meta.images && meta.images[0]) || ''));
        const name = escHtml(String(o.name || meta.name || 'Huduma'));
        const extraTotal = o.total ?? (Number(o.service || 0) + Number(o.materials || 0) + Number(o.usage || 0));
        const selected = id && selectedExtraIds.has(id);

        return `
          <div class="recCard ${selected ? 'is-selected' : ''}">
            <button class="recCard__thumb" type="button" data-preview-service="${id}" aria-label="Picha ${name}">
              <img src="${thumb}" alt="">
            </button>
            <div class="recCard__main">
              <button class="recCard__name" type="button" data-preview-service="${id}">${name}</button>
              <div class="muted small">+${fmtTZS(extraTotal)}</div>
            </div>
            <button class="btn btn--ghost btn--sm recCard__add" type="button" data-toggle-extra="${id}">
              ${selected ? 'Ondoa' : 'Ongeza'}
            </button>
          </div>
        `;
      }).join('');

      return `
        <div class="recGroup">
          <div class="recGroup__title">${escHtml(g.title)}</div>
          <div class="recGrid">${cards}</div>
        </div>
      `;
    }).join('');

    extrasRecs.innerHTML = html;
    syncExtraUI();
  }

  function renderAllServicesList(query = '') {
    if (!allSvcList) return;
    const q = String(query || '').trim().toLowerCase();

    const list = allSvcOptions.filter((o) => {
      if (!q) return true;
      return String(o.name || '').toLowerCase().includes(q);
    });

    const html = list.map((o) => {
      const id = String(o.id || '');
      const meta = getMeta(id) || {};
      const thumb = escHtml(String((meta.images && meta.images[0]) || ''));
      const name = escHtml(String(o.name || meta.name || 'Huduma'));
      const extraTotal = o.total ?? (Number(o.service || 0) + Number(o.materials || 0) + Number(o.usage || 0));
      const checked = id && selectedExtraIds.has(id) ? 'checked' : '';

      return `
        <div class="allSvcItem">
          <input class="allSvcItem__check" type="checkbox" data-allsvc-check value="${id}" ${checked}>
          <button class="allSvcItem__thumb" type="button" data-preview-service="${id}" aria-label="Picha ${name}">
            <img src="${thumb}" alt="">
          </button>
          <div class="allSvcItem__main">
            <div class="allSvcItem__name">${name}</div>
            <div class="muted small">+${fmtTZS(extraTotal)}</div>
          </div>
        </div>
      `;
    }).join('');

    allSvcList.innerHTML = html || `<div class="muted small">Hakuna huduma nyingine.</div>`;
    syncExtraUI();
  }

  function openAllServices(providerId) {
    if (!allServicesModal) return;
    allSvcOptions = getProviderOptions(providerId).filter((o) => String(o.id) !== String(primaryServiceId));
    if (allSvcSearch) allSvcSearch.value = '';
    renderAllServicesList('');
    openModal(allServicesModal);
    allSvcSearch?.focus();
  }

  function setPreviewIndex(i) {
    if (!svcPreviewImg || !svcPreviewThumbs) return;
    if (!previewImages.length) return;
    previewIdx = (i + previewImages.length) % previewImages.length;
    svcPreviewImg.src = String(previewImages[previewIdx] || '');
    svcPreviewThumbs.querySelectorAll('[data-preview-thumb]').forEach((b) => {
      b.classList.toggle('is-active', String(b.getAttribute('data-preview-thumb')) === String(previewIdx));
    });
  }

  function openPreview(serviceId) {
    const meta = getMeta(serviceId);
    if (!svcPreviewModal || !meta) return;
    previewImages = Array.isArray(meta.images) ? meta.images : [];
    if (!previewImages.length) return;

    if (svcPreviewTitle) svcPreviewTitle.textContent = String(meta.name || 'Huduma');

    if (svcPreviewThumbs) {
      svcPreviewThumbs.innerHTML = previewImages.map((u, idx) => `
        <button class="svcPrevThumb ${idx === 0 ? 'is-active' : ''}" type="button" data-preview-thumb="${idx}">
          <img src="${escHtml(String(u || ''))}" alt="">
        </button>
      `).join('');
    }

    setPreviewIndex(0);
    openModal(svcPreviewModal);
  }

  // Extras UI events
  setExtrasExpanded(false);
  extrasToggle?.addEventListener('click', () => setExtrasExpanded(extrasBody?.style.display === 'none'));
  btnOpenAll?.addEventListener('click', () => {
    const providerId = activeCard?.getAttribute('data-provider-id');
    if (!providerId) return;
    openAllServices(providerId);
  });
  extrasRecs?.addEventListener('click', (e) => {
    const toggleBtn = e.target.closest('[data-toggle-extra]');
    if (toggleBtn) {
      e.preventDefault();
      toggleExtra(toggleBtn.getAttribute('data-toggle-extra'));
      return;
    }

    const previewBtn = e.target.closest('[data-preview-service]');
    if (previewBtn) {
      e.preventDefault();
      openPreview(previewBtn.getAttribute('data-preview-service'));
    }
  });

  // All services modal events
  allSvcClose?.addEventListener('click', () => closeModal(allServicesModal));
  allSvcDone?.addEventListener('click', () => closeModal(allServicesModal));
  allServicesModal?.addEventListener('click', (e) => { if (e.target === allServicesModal) closeModal(allServicesModal); });
  allSvcSearch?.addEventListener('input', () => renderAllServicesList(allSvcSearch.value));
  allSvcList?.addEventListener('change', (e) => {
    const cb = e.target?.closest?.('[data-allsvc-check]');
    if (!cb) return;
    const id = String(cb.value || '');
    if (!id || id === String(primaryServiceId)) return;
    if (cb.checked) selectedExtraIds.add(id);
    else selectedExtraIds.delete(id);
    recalcBreakdown();
  });
  allSvcList?.addEventListener('click', (e) => {
    const previewBtn = e.target.closest('[data-preview-service]');
    if (!previewBtn) return;
    e.preventDefault();
    openPreview(previewBtn.getAttribute('data-preview-service'));
  });

  // Preview modal events
  svcPreviewClose?.addEventListener('click', () => closeModal(svcPreviewModal));
  svcPreviewModal?.addEventListener('click', (e) => { if (e.target === svcPreviewModal) closeModal(svcPreviewModal); });
  svcPreviewPrev?.addEventListener('click', () => setPreviewIndex(previewIdx - 1));
  svcPreviewNext?.addEventListener('click', () => setPreviewIndex(previewIdx + 1));
  svcPreviewThumbs?.addEventListener('click', (e) => {
    const b = e.target.closest('[data-preview-thumb]');
    if (!b) return;
    const i = parseInt(String(b.getAttribute('data-preview-thumb') || '0'), 10);
    if (!Number.isFinite(i)) return;
    setPreviewIndex(i);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeModal(allServicesModal);
      closeModal(svcPreviewModal);
    }
  });

  function recalcBreakdown() {
    if (!activeCard) return;

    const providerId = activeCard.getAttribute('data-provider-id');
    const providerName = activeCard.querySelector('.provCard__name')?.textContent?.trim() || 'Selected';

    const baseService = Number(activeCard.getAttribute('data-service') || 0);
    const baseMaterials = Number(activeCard.getAttribute('data-materials') || 0);
    const baseUsage = Number(activeCard.getAttribute('data-usage') || 0);
    const baseTravelRaw = activeCard.getAttribute('data-travel');
    const baseTravel = Number(String(baseTravelRaw || '').trim() === '' ? 0 : baseTravelRaw);

    let sumService = baseService;
    let sumMaterials = baseMaterials;
    let sumUsage = baseUsage;

    const options = getProviderOptions(providerId);
    selectedExtraIds.forEach((id) => {
      const opt = options.find((o) => String(o.id) === String(id));
      if (!opt) return;
      sumService += Number(opt.service || 0);
      sumMaterials += Number(opt.materials || 0);
      sumUsage += Number(opt.usage || 0);
    });

    const travel = hasLoc ? baseTravel : 0;
    const hairWashAmount = isHairWashSelected() ? Number(hairWashPrice || 0) : 0;
    const total = sumService + sumMaterials + sumUsage + travel + hairWashAmount;

    if (bdService) bdService.textContent = fmtTZS(sumService);
    if (bdMaterials) bdMaterials.textContent = fmtTZS(sumMaterials);
    if (bdUsage) bdUsage.textContent = fmtTZS(sumUsage);
    if (bdHairWash) bdHairWash.textContent = isHairWashSelected() ? `+${fmtTZS(hairWashAmount)}` : 'Imeondolewa';
    if (bdTotal) bdTotal.textContent = fmtTZS(total);
    if (topTotalPill) topTotalPill.textContent = fmtTZS(total);

    if (bdTravel) {
      if (!hasLoc) {
        bdTravel.innerHTML = `<span class="muted small">Washa location</span>`;
      } else if (baseTravelRaw === null || baseTravelRaw === undefined || String(baseTravelRaw).trim() === '') {
        bdTravel.innerHTML = `<span class="muted small">Chagua mtoa huduma</span>`;
      } else {
        bdTravel.textContent = fmtTZS(baseTravelRaw);
      }
    }

    if (providerPill) providerPill.textContent = 'Mtoa huduma: ' + providerName;

    if (bookForm && providerId) {
      bookForm.setAttribute('action', `${checkoutBase}/${providerId}`);
    }

    syncHairWashInput();
    syncExtraInputs();
    syncExtraUI();
    updateGuestAuthLinks();
  }

  const cards = Array.from(document.querySelectorAll('[data-provider-card]'));
  function selectCard(card) {
    activeCard = card || null;
    selectedExtraIds = new Set();
    cards.forEach((c) => c.classList.toggle('is-selected', c === card));
    const providerId = card?.getAttribute('data-provider-id');
    renderExtraServices(providerId);
    recalcBreakdown();
    cards.forEach((c) => {
      const btn = c.querySelector('[data-select-provider]');
      if (!btn) return;
      const isSel = c === card;
      btn.textContent = isSel ? 'Umemchagua' : 'Chagua mtoa huduma huyu';
      btn.disabled = isSel;
    });
  }
  cards.forEach((c) => c.addEventListener('click', () => selectCard(c)));
  if (cards[0]) selectCard(cards[0]);
  hairWashToggle?.addEventListener('change', recalcBreakdown);
  updateGuestAuthLinks();

  // Mobile price sheet toggle
  const priceSheet = document.querySelector('[data-price-sheet]');
  const priceToggle = priceSheet?.querySelector('[data-price-toggle]');
  function setSheetExpanded(expanded) {
    if (!priceSheet || !priceToggle) return;
    priceSheet.classList.toggle('is-expanded', !!expanded);
    priceSheet.classList.toggle('is-collapsed', !expanded);
    priceToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    const chev = priceToggle.querySelector('.priceSheet__chev');
    if (chev) chev.textContent = expanded ? 'v' : '^';
  }
  priceToggle?.addEventListener('click', () => setSheetExpanded(!priceSheet.classList.contains('is-expanded')));

  // Location request
  const btnLoc = document.getElementById('btnAskLocSvc');
  const locErr = document.getElementById('locErrSvc');

  btnLoc?.addEventListener('click', () => {
    if (window.GlamoGeoPrompt && typeof window.GlamoGeoPrompt.openPrompt === 'function') {
      window.GlamoGeoPrompt.openPrompt();
      return;
    }

    if (locErr) {
      locErr.textContent = 'Location prompt haipo. Refresh ukurasa kisha jaribu tena.';
      locErr.style.display = 'block';
    }
  });
})();
</script>
@endsection


