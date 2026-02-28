@extends('public.layout')

@section('title', ($service->name ?? 'Huduma') . ' - Mtoa Huduma')

@section('content')
@php
  $approvalStatus = (string) ($provider->approval_status ?? 'pending');

  $statusText = [
      'approved' => 'Umeidhinishwa',
      'pending' => 'Inasubiri uhakiki',
      'needs_more_steps' => 'Imeidhinishwa kwa hatua (Partial approved)',
      'rejected' => 'Imerejeshwa kurekebishwa',
  ][$approvalStatus] ?? ucfirst($approvalStatus);

  $categoryName = (string) (data_get($service, 'category.name') ?: ucfirst((string) ($service->category ?? 'Huduma')));

  $durationMin = (int) ($service->duration_minutes ?? 60);
  if ($durationMin <= 0) {
      $durationMin = 60;
  }
  $h = intdiv($durationMin, 60);
  $m = $durationMin % 60;
  $durationText = $h > 0 ? ($h . ' saa' . ($m ? ' ' . $m . ' dk' : '')) : ($m . ' dk');

  $basePrice = (float) ($service->base_price ?? 0);
  $materialsPrice = (float) ($service->materials_price ?? 0);
  $usagePercent = (float) config('glamo_pricing.usage_percent', 5);
  $usagePrice = round(($basePrice * max(0, $usagePercent)) / 100, 2);
  $estimatedTotal = $basePrice + $materialsPrice + $usagePrice;

  $mainImage = (string) ($images[0] ?? asset('images/placeholder.svg'));

  $addDisabled = !$canOperate || $isAdded;
@endphp

<section class="section providerSvcPage">
  <div class="container">
    <a class="backLink" href="{{ route('provider.dashboard') }}">&larr; Rudi dashibodi</a>

    <div class="providerSvcPage__grid">
      <article class="providerSvcCard">
        <div class="providerSvcCard__media">
          <img src="{{ $mainImage }}" alt="{{ $service->name }}" id="providerSvcMainImage">
        </div>

        @if(count($images) > 1)
          <div class="providerSvcCard__thumbs">
            @foreach($images as $index => $img)
              <button
                class="providerSvcThumb {{ $index === 0 ? 'is-active' : '' }}"
                type="button"
                data-provider-svc-thumb="{{ $index }}"
                data-provider-svc-src="{{ $img }}"
                aria-label="Picha {{ $index + 1 }}"
              >
                <img src="{{ $img }}" alt="">
              </button>
            @endforeach
          </div>
        @endif

        <div class="providerSvcCard__body">
          <h1 class="providerSvcTitle">{{ $service->name }}</h1>
          <p class="providerSvcMeta">{{ $categoryName }} - {{ $durationText }}</p>
          <p class="providerSvcDesc">
            {{ trim((string) ($service->short_desc ?? '')) !== '' ? $service->short_desc : 'Huduma hii inapatikana kwa profile yako. Angalia picha zote kisha uiongeze kwenye huduma unazotoa.' }}
          </p>
        </div>
      </article>

      <aside class="providerSvcSide">
        <article class="providerSvcInfoCard">
          <h3>Muhtasari wa gharama</h3>
          <div class="providerSvcInfoRow"><span>Huduma</span><strong>TZS {{ number_format($basePrice, 0) }}</strong></div>
          <div class="providerSvcInfoRow"><span>Vifaa</span><strong>TZS {{ number_format($materialsPrice, 0) }}</strong></div>
          <div class="providerSvcInfoRow"><span>Matumizi</span><strong>TZS {{ number_format($usagePrice, 0) }}</strong></div>
          <div class="providerSvcInfoRow providerSvcInfoRow--total"><span>Jumla estimate</span><strong>TZS {{ number_format($estimatedTotal, 0) }}</strong></div>
        </article>

        <article class="providerSvcInfoCard">
          <h3>Hali ya profile yako</h3>
          <p class="providerSvcStatus">Status: <strong>{{ $statusText }}</strong></p>

          @if($isAdded)
            <button class="btn btn--ghost wfull" type="button" disabled>Tayari umeongeza huduma hii</button>
          @else
            <form method="POST" action="{{ route('provider.services.add', ['service' => (int) $service->id]) }}">
              @csrf
              <button class="btn btn--primary wfull" type="submit" {{ $addDisabled ? 'disabled' : '' }}>
                Add huduma hii
              </button>
            </form>
          @endif

          @if(!$canOperate)
            <p class="providerSvcHint">Profile haijaidhinishwa bado. Button ya Add imezimwa hadi uidhinishwe.</p>
          @elseif(!$isAdded)
            <p class="providerSvcHint">Ukibonyeza Add, huduma hii itaonekana kwenye huduma unazotoa.</p>
          @endif
        </article>
      </aside>
    </div>

    <article class="providerSvcGalleryPanel">
      <div class="providerSvcGalleryPanel__head">
        <h2>Picha zote za huduma hii</h2>
        <span>{{ count($images) }} picha</span>
      </div>

      <div class="providerSvcGalleryGrid">
        @foreach($images as $index => $img)
          <button
            type="button"
            class="providerSvcGalleryItem"
            data-provider-svc-thumb="{{ $index }}"
            data-provider-svc-src="{{ $img }}"
            aria-label="Picha {{ $index + 1 }}"
          >
            <img src="{{ $img }}" alt="{{ $service->name }} {{ $index + 1 }}" loading="lazy">
          </button>
        @endforeach
      </div>
    </article>
  </div>
</section>

<script>
  (function () {
    const main = document.getElementById('providerSvcMainImage');
    const thumbs = Array.from(document.querySelectorAll('[data-provider-svc-thumb]'));
    if (!main || thumbs.length === 0) return;

    function activate(target) {
      const src = target.getAttribute('data-provider-svc-src');
      if (!src) return;

      main.src = src;
      thumbs.forEach((el) => el.classList.remove('is-active'));
      target.classList.add('is-active');
    }

    thumbs.forEach((btn) => {
      btn.addEventListener('click', () => activate(btn));
    });
  })();
</script>
@endsection
