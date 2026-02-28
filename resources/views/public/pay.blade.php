@extends('public.layout')

@section('title', 'Malipo - Glamo')

@section('content')
@php
  $categoryName = data_get($service, 'category.name') ?: (data_get($category, 'name') ?: 'Huduma');
  $providerName = data_get($provider, 'display_name') ?: 'Mtoa huduma';

  $svcImg = (string) (data_get($service, 'primary_image_url') ?: asset('images/placeholder.svg'));
  $pImg = (string) (data_get($provider, 'profile_image_url') ?: asset('images/placeholder.svg'));

  $items = collect(data_get($quote, 'items', []));
  $distanceKm = (float) data_get($quote, 'distance_km', 0);

  $couponCodeValue = (string) ($couponCode ?? '');
  $pc = old('payment_channel', $paymentChannel ?? '');
  $authUser = auth()->user();
  $phoneValue = old('phone_number', (string) (data_get($authUser, 'phone') ?? ''));
  if ($phoneValue === '') {
    $phoneValue = '255';
  }

  $sumService = (float) data_get($quote, 'sum_service', 0);
  $sumHairWash = (float) data_get($quote, 'hair_wash', 0);
  $sumMaterials = (float) data_get($quote, 'sum_materials', 0);
  $sumUsage = (float) data_get($quote, 'sum_usage', 0);
  $sumTravel = (float) data_get($quote, 'travel', 0);
  $subtotal = max(0, $sumService + $sumHairWash + $sumMaterials + $sumUsage + $sumTravel);
@endphp

<section class="section checkoutFlow checkoutFlow--pay">
  <div class="container">
    <div class="checkoutFlow__layout">
      <main class="checkoutFlow__main">
        <a class="backLink" href="{{ route('services.checkout', ['category' => $category->slug, 'service' => $service->slug]) }}">&lt;- Rudi checkout</a>

        <header class="checkoutFlow__header">
          <span class="checkoutFlow__step">Hatua 2 ya 2</span>
          <h1 class="checkoutFlow__title">Lipa booking yako</h1>
          <p class="checkoutFlow__subtitle">Chagua kama unalipa kwa simu au kwa kadi. Baada ya malipo kuthibitishwa, oda itaundwa moja kwa moja.</p>
        </header>

        <article class="card card--soft checkoutFlow__block">
          <div class="checkoutFlow__blockHead">
            <h2>Chagua channel ya malipo</h2>
          </div>

          <form method="POST" action="{{ route('services.pay.confirm', ['category' => $category->slug, 'service' => $service->slug]) }}" id="payConfirmForm">
            @csrf

            <label class="label" for="paymentChannel">Channel</label>
            <select class="input input--select" id="paymentChannel" name="payment_channel" required>
              <option value="" {{ $pc === '' ? 'selected' : '' }}>Chagua channel</option>
              <option value="mobile" {{ $pc === 'mobile' ? 'selected' : '' }}>Simu (Mobile Money)</option>
              <option value="card" {{ $pc === 'card' ? 'selected' : '' }}>Kadi (Visa / Mastercard)</option>
            </select>
            @error('payment_channel')
              <div class="err">{{ $message }}</div>
            @enderror

            <div class="payChannelTips">
              <button class="payChannelTips__btn" type="button" data-pay-channel="mobile">Lipa kwa simu</button>
              <button class="payChannelTips__btn" type="button" data-pay-channel="card">Lipa kwa kadi</button>
            </div>

            <div id="mobileFields" style="margin-top:12px; display:none;">
              <label class="label" for="phoneNumber">Namba ya simu ya kulipia</label>
              <input
                class="input"
                id="phoneNumber"
                name="phone_number"
                type="tel"
                inputmode="numeric"
                autocomplete="tel"
                placeholder="Mfano: 07XXXXXXXX au 2557XXXXXXXX"
                value="{{ $phoneValue }}"
              >
              @error('phone_number')
                <div class="err">{{ $message }}</div>
              @enderror
              <div class="muted small" style="margin-top:6px;">Utapokea prompt ya kuthibitisha malipo kwenye simu yako.</div>
            </div>

            <div id="cardFields" style="margin-top:12px; display:none;">
              <label class="label" for="cardName">Jina la mwenye kadi</label>
              <input class="input" id="cardName" name="card_name" placeholder="Mfano: Amina Salum" value="{{ old('card_name') }}">
              @error('card_name')
                <div class="err">{{ $message }}</div>
              @enderror

              <label class="label" for="cardNumber" style="margin-top:10px;">Namba ya kadi</label>
              <input class="input" id="cardNumber" name="card_number" inputmode="numeric" placeholder="4242 4242 4242 4242" value="{{ old('card_number') }}">
              @error('card_number')
                <div class="err">{{ $message }}</div>
              @enderror

              <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                <div>
                  <label class="label" for="cardExpiry">Expire date (MM/YY)</label>
                  <input class="input" id="cardExpiry" name="card_expiry" placeholder="09/27" value="{{ old('card_expiry') }}">
                  @error('card_expiry')
                    <div class="err">{{ $message }}</div>
                  @enderror
                </div>
                <div>
                  <label class="label" for="cardCvv">CVV</label>
                  <input class="input" id="cardCvv" name="card_cvv" inputmode="numeric" placeholder="123" value="{{ old('card_cvv') }}">
                  @error('card_cvv')
                    <div class="err">{{ $message }}</div>
                  @enderror
                </div>
              </div>
            </div>

            <div class="notice" style="margin-top:14px;">
              <div class="notice__body">
                <div class="notice__title">Malipo salama kwa escrow</div>
                <div class="muted small">
                  Mteja analipa sasa, lakini mtoa huduma atalipwa baada ya huduma kukamilika. Hii inalinda pande zote mbili.
                </div>
              </div>
            </div>

            <button class="btn btn--primary wfull" type="submit" id="paySubmitBtn">Lipa na endelea</button>
          </form>
        </article>
      </main>

      <aside class="checkoutFlow__aside">
        <article class="card checkoutFlow__summary">
          <h2 class="checkoutFlow__summaryTitle">Muhtasari wa malipo</h2>

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
              <span>Huduma</span>
              <span>{{ number_format($items->count()) }} item</span>
            </div>
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
              <span>TZS {{ number_format($subtotal, 0) }}</span>
            </div>

            @if(($discount ?? 0) > 0 && $couponCodeValue !== '')
              <div class="checkoutFlow__row checkoutFlow__row--discount">
                <span>Punguzo ({{ $couponCodeValue }})</span>
                <span>-TZS {{ number_format((float) $discount, 0) }}</span>
              </div>
            @endif

            <div class="checkoutFlow__row checkoutFlow__row--total">
              <span>Jumla ya kulipa</span>
              <span>TZS {{ number_format((float) ($total ?? 0), 0) }}</span>
            </div>
          </div>

          <p class="checkoutFlow__note">Ukifanikiwa kulipa, oda itaingia moja kwa moja kwenye mfumo na mtoa huduma ataarifiwa.</p>
        </article>
      </aside>
    </div>
  </div>
</section>

<script>
(() => {
  const select = document.getElementById('paymentChannel');
  const tipButtons = Array.from(document.querySelectorAll('[data-pay-channel]'));
  const submitBtn = document.getElementById('paySubmitBtn');
  const mobileFields = document.getElementById('mobileFields');
  const cardFields = document.getElementById('cardFields');
  const phoneInput = document.getElementById('phoneNumber');
  const cardName = document.getElementById('cardName');
  const cardNumber = document.getElementById('cardNumber');
  const cardExpiry = document.getElementById('cardExpiry');
  const cardCvv = document.getElementById('cardCvv');

  function setButtonState() {
    const val = String(select?.value || '').trim();
    tipButtons.forEach((btn) => {
      btn.classList.toggle('is-active', btn.getAttribute('data-pay-channel') === val);
    });

    const isMobile = val === 'mobile';
    const isCard = val === 'card';

    if (mobileFields) {
      mobileFields.style.display = isMobile ? '' : 'none';
    }

    if (cardFields) {
      cardFields.style.display = isCard ? '' : 'none';
    }

    if (phoneInput) {
      if (isMobile) {
        phoneInput.setAttribute('required', 'required');
      } else {
        phoneInput.removeAttribute('required');
      }
    }

    [cardName, cardNumber, cardExpiry, cardCvv].forEach((field) => {
      if (!field) {
        return;
      }

      if (isCard) {
        field.setAttribute('required', 'required');
      } else {
        field.removeAttribute('required');
      }
    });

    if (submitBtn) {
      submitBtn.textContent = 'Lipa na endelea';
    }
  }

  tipButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      if (!select) {
        return;
      }
      select.value = String(btn.getAttribute('data-pay-channel') || '');
      setButtonState();
    });
  });

  select?.addEventListener('change', () => {
    setButtonState();
  });

  setButtonState();
})();
</script>
@endsection
