@extends('public.layout')

@section('title', 'Checkout - Glamo')

@section('content')
@php
  $categoryName = data_get($service, 'category.name') ?: (data_get($category, 'name') ?: 'Huduma');
  $providerName = data_get($provider, 'display_name') ?: 'Mtoa huduma';

  $svcImg = (string) (data_get($service, 'primary_image_url') ?: asset('images/placeholder.svg'));
  $pImg = (string) (data_get($provider, 'profile_image_url') ?: asset('images/placeholder.svg'));

  $items = collect(data_get($quote, 'items', []));
  $distanceKm = (float) data_get($quote, 'distance_km', 0);
  $couponCodeValue = old('coupon_code', $couponCode ?? '');

  $sumService = (float) data_get($quote, 'sum_service', 0);
  $sumHairWash = (float) data_get($quote, 'hair_wash', 0);
  $sumMaterials = (float) data_get($quote, 'sum_materials', 0);
  $sumUsage = (float) data_get($quote, 'sum_usage', 0);
  $sumTravel = (float) data_get($quote, 'travel', 0);
  $addressTextValue = old('address_text', $addressTextDefault ?? '');
  $paymentMethodValue = old('payment_method', $paymentMethodDefault ?? 'cash');
@endphp

<section class="section checkoutFlow">
  <div class="container">
    <div class="checkoutFlow__layout">
      <main class="checkoutFlow__main">
        <a class="backLink" href="{{ route('services.show', ['category' => $category->slug, 'service' => $service->slug]) }}">&lt;- Rudi kwenye huduma</a>

        <header class="checkoutFlow__header">
          <span class="checkoutFlow__step">Hatua 1 ya 1</span>
          <h1 class="checkoutFlow__title">Kamilisha booking yako</h1>
          <p class="checkoutFlow__subtitle">Hakiki huduma zako, chagua njia ya malipo, kisha weka oda.</p>
        </header>

        <article class="card card--soft checkoutFlow__block">
          <div class="checkoutFlow__blockHead">
            <h2>Huduma ulizochagua</h2>
            <span>{{ number_format($items->count()) }} huduma</span>
          </div>

          <div class="checkoutList">
            @forelse($items as $it)
              @php
                $lineName = (string) (data_get($it, 'service_name') ?: 'Huduma');
                $lineTotal = (float) data_get($it, 'line_total', 0);
                if ($lineTotal <= 0) {
                  $lineTotal = (float) data_get($it, 'service_price', 0)
                    + (float) data_get($it, 'materials_price', 0)
                    + (float) data_get($it, 'usage_price', 0);
                }
              @endphp
              <div class="checkoutList__item">
                <div class="checkoutList__main">
                  <strong>{{ $lineName }}</strong>
                  <span>Gharama ya huduma + vifaa + matumizi</span>
                </div>
                <div class="checkoutList__value">TZS {{ number_format($lineTotal, 0) }}</div>
              </div>
            @empty
              <div class="checkoutList__item">
                <div class="checkoutList__main">
                  <strong>{{ $service->name }}</strong>
                  <span>{{ $categoryName }}</span>
                </div>
                <div class="checkoutList__value">TZS {{ number_format((float) ($subtotal ?? 0), 0) }}</div>
              </div>
            @endforelse
          </div>
        </article>

        <article class="card card--soft checkoutFlow__block">
          <div class="checkoutFlow__blockHead">
            <h2>Coupon code (hiari)</h2>
            @if(($discount ?? 0) > 0)
              <span>-TZS {{ number_format((float) $discount, 0) }}</span>
            @endif
          </div>

          <form method="POST" action="{{ route('services.checkout.coupon', ['category' => $category->slug, 'service' => $service->slug]) }}">
            @csrf
            <div class="couponRow">
              <input class="input" name="coupon_code" placeholder="Weka coupon code" value="{{ $couponCodeValue }}" autocomplete="off">
              <button class="btn btn--ghost" type="submit">Tumia</button>
            </div>
            @if(!empty($couponErr))
              <div class="err">{{ $couponErr }}</div>
            @endif
          </form>
        </article>

        <article class="card card--soft checkoutFlow__block">
          <div class="checkoutFlow__blockHead">
            <h2>Weka oda sasa</h2>
          </div>

          <form method="POST" id="confirmForm" action="{{ route('services.checkout.confirm', ['category' => $category->slug, 'service' => $service->slug]) }}">
            @csrf

            <label class="label">Njia ya malipo</label>
            <div class="payMethodList" id="payMethodList">
              <label class="payMethodItem">
                <input type="radio" name="payment_method" value="cash" {{ $paymentMethodValue === 'cash' ? 'checked' : '' }}>
                <span>Cash baada ya huduma</span>
              </label>
              <label class="payMethodItem">
                <input type="radio" name="payment_method" value="prepay" {{ $paymentMethodValue === 'prepay' ? 'checked' : '' }}>
                <span>Lipa sasa online (escrow salama)</span>
              </label>
            </div>
            @error('payment_method') <div class="err">{{ $message }}</div> @enderror

            <div id="onlinePaymentFields" style="display:none; margin-top:10px;">
              <div class="muted small" style="margin-top:10px;">
                Ukichagua online hapa, utaenda kwenye ukurasa wa malipo uchague:
                kulipa kwa simu au kulipa kwa kadi, kisha ukimaliza malipo oda itaundwa moja kwa moja.
              </div>
            </div>

            <label class="label" for="checkoutAddressText">Mtaa / maelekezo ya location</label>
            <input
              class="input"
              id="checkoutAddressText"
              name="address_text"
              placeholder="Mfano: Mtaa wa Makumbusho, karibu na kituo cha daladala"
              value="{{ $addressTextValue }}"
              maxlength="255"
              required
            >
            @error('address_text') <div class="err">{{ $message }}</div> @enderror

            <button class="btn btn--primary wfull" id="checkoutSubmit" type="submit">Weka oda sasa</button>
            <div class="muted small" style="margin-top:10px;">
              Kwa cash: oda inaingia moja kwa moja. Kwa online: utaenda payment page kwanza.
            </div>
          </form>
        </article>
      </main>

      <aside class="checkoutFlow__aside">
        <article class="card checkoutFlow__summary">
          <h2 class="checkoutFlow__summaryTitle">Muhtasari wa booking</h2>

          <div class="checkoutFlow__serviceCard">
            <div class="svcMini">
              <div class="svcMini__img" style="background-image:url('{{ $svcImg }}')"></div>
              <div class="svcMini__main">
                <div class="muted small">{{ $categoryName }}</div>
                <div class="svcMini__name">{{ $service->name }}</div>
              </div>
            </div>
          </div>

          <div class="checkoutFlow__providerCard">
            <div class="provMini">
              <img class="provMini__avatar" src="{{ $pImg }}" alt="{{ $providerName }}" loading="lazy">
              <div class="provMini__main">
                <div class="provMini__name">{{ $providerName }}</div>
                <div class="muted small">Umbali: {{ number_format($distanceKm, 1) }} km</div>
              </div>
            </div>
          </div>

          <div class="checkoutFlow__rows">
            <div class="checkoutFlow__row">
              <span>Gharama ya huduma</span>
              <span>TZS {{ number_format($sumService, 0) }}</span>
            </div>
            @if($sumHairWash > 0)
              <div class="checkoutFlow__row">
                <span>Kuosha nywele</span>
                <span>TZS {{ number_format($sumHairWash, 0) }}</span>
              </div>
            @endif
            <div class="checkoutFlow__row">
              <span>Vifaa na material</span>
              <span>TZS {{ number_format($sumMaterials, 0) }}</span>
            </div>
            <div class="checkoutFlow__row">
              <span>Usafiri</span>
              <span>TZS {{ number_format($sumTravel, 0) }}</span>
            </div>
            <div class="checkoutFlow__row">
              <span>Matumizi</span>
              <span>TZS {{ number_format($sumUsage, 0) }}</span>
            </div>
            <div class="checkoutFlow__row">
              <span>Subtotal</span>
              <span>TZS {{ number_format((float) ($subtotal ?? 0), 0) }}</span>
            </div>

            @if(($discount ?? 0) > 0)
              <div class="checkoutFlow__row checkoutFlow__row--discount">
                <span>Punguzo ({{ $couponCodeValue ?: 'coupon' }})</span>
                <span>-TZS {{ number_format((float) $discount, 0) }}</span>
              </div>
            @endif

            @if(($rawTotal ?? 0) > 0 && abs((float) ($rawTotal ?? 0) - (float) ($total ?? 0)) > 0.001)
              <div class="checkoutFlow__row">
                <span>Jumla kabla ya kukadiria cash</span>
                <span>TZS {{ number_format((float) $rawTotal, 0) }}</span>
              </div>
            @endif

            @if(($cashAdjustment ?? 0) > 0)
              <div class="checkoutFlow__row">
                <span>Makadirio ya cash adjustment</span>
                <span>+TZS {{ number_format((float) $cashAdjustment, 0) }}</span>
              </div>
            @endif

            <div class="checkoutFlow__row checkoutFlow__row--total">
              <span>Jumla ya oda</span>
              <span>TZS {{ number_format((float) ($total ?? 0), 0) }}</span>
            </div>
          </div>

          <p class="checkoutFlow__note">Kwa cash, jumla inaweza kuzungushwa juu kidogo kurahisisha malipo. Kwa online, unalipa kiasi halisi baada ya discount.</p>
        </article>
      </aside>
    </div>
  </div>
</section>

<script>
(() => {
  const methodInputs = Array.from(document.querySelectorAll('input[name="payment_method"]'));
  const onlineWrap = document.getElementById('onlinePaymentFields');
  const submitBtn = document.getElementById('checkoutSubmit');

  function syncMode() {
    const selectedMethod = String((methodInputs.find((el) => el.checked)?.value || 'cash')).trim();
    const isPrepay = selectedMethod === 'prepay';

    if (onlineWrap) {
      onlineWrap.style.display = isPrepay ? '' : 'none';
    }

    if (submitBtn) {
      submitBtn.textContent = isPrepay ? 'Endelea kwenda malipo' : 'Weka oda sasa';
    }
  }

  methodInputs.forEach((input) => input.addEventListener('change', syncMode));
  syncMode();
})();
</script>

@endsection
