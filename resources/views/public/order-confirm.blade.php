@extends('public.layout')

@section('title', 'Oda Yako - Glamo')

@section('content')
@php
  $items = collect($orderItems ?? []);
  $serviceList = $items
    ->map(fn ($it) => data_get($it, 'service.name'))
    ->filter()
    ->values();

  $serviceName = $serviceList->isNotEmpty()
    ? $serviceList->implode(', ')
    : (data_get($order, 'service.name') ?? 'Huduma');

  $service = data_get($order, 'service');
  $serviceSlug = (string) data_get($service, 'slug');
  $serviceSlug = $serviceSlug !== '' ? $serviceSlug : null;

  $categoryRel = $service && method_exists($service, 'getRelationValue') ? $service->getRelationValue('category') : null;
  $categorySlug = strtolower((string) (data_get($categoryRel, 'slug') ?? data_get($service, 'category') ?? 'other'));
  $categorySlug = $categorySlug !== '' ? $categorySlug : 'other';

  $serviceShowUrl = route('landing') . '#services';
  if ($serviceSlug) {
    $serviceShowUrl = route('services.show', ['category' => $categorySlug, 'service' => $serviceSlug]);
  }

  $providerName = (string) (data_get($order, 'provider.display_name') ?? 'Mtoa huduma');
  $clientName = (string) (data_get($order, 'client.name') ?? (auth()->user()->name ?? 'Mteja'));
  $providerFirst = trim((string) strtok($providerName, ' '));
  $clientFirst = trim((string) strtok($clientName, ' '));
  $providerFirst = $providerFirst !== '' ? $providerFirst : 'Mtoa huduma';
  $clientFirst = $clientFirst !== '' ? $clientFirst : 'Mteja';

  $total = (float) ($order->price_total ?? 0);
  $subtotal = (float) (data_get($order, 'price_subtotal') ?? ($total + (float) (data_get($order, 'discount_amount') ?? 0)));
  $discount = (float) (data_get($order, 'discount_amount') ?? 0);
  $couponCode = (string) (data_get($order, 'coupon_code') ?? '');

  $paymentMethod = (string) (data_get($order, 'payment_method') ?? '');
  $paymentStatus = (string) (data_get($order, 'payment_status') ?? '');
  $paymentChannel = (string) (data_get($order, 'payment_channel') ?? '');

  $status = (string) ($order->status ?? 'pending');

  $reviewRow = $review ?? null;
  $hasReview = !empty($reviewRow);

  $steps = [
    ['k' => 'pending', 't' => 'Imepokelewa'],
    ['k' => 'on_the_way', 't' => 'Yuko njiani anakuja...'],
    ['k' => 'completed', 't' => 'Imekamilika'],
  ];
  $statusForTrack = in_array($status, ['accepted', 'on_the_way', 'in_progress'], true) ? 'on_the_way' : $status;
  $statusIndex = collect($steps)->search(fn ($s) => $s['k'] === $statusForTrack);
  $statusIndex = is_int($statusIndex) ? $statusIndex : 0;

  $isDone = in_array($status, ['completed', 'cancelled'], true);
  $usagePercent = (float) config('glamo_pricing.usage_percent', 5);

  $serviceFee = data_get($order, 'price_service');
  $materials = data_get($order, 'price_materials');
  $travel = data_get($order, 'price_travel');
  $usage = data_get($order, 'price_usage');

  $createdAt = data_get($order, 'created_at');
  $editDeadline = $createdAt ? $createdAt->copy()->addMinutes(2) : null;
  $canEditWindow = $editDeadline ? now()->lte($editDeadline) : false;
  $remainingEditSeconds = $canEditWindow ? max(0, now()->diffInSeconds($editDeadline, false)) : 0;

  $canEdit = $status === 'pending' && $canEditWindow && (($providerServices ?? collect())->count() > 0);
  $canCancel = in_array($status, ['pending', 'accepted', 'on_the_way'], true);

  $settledStatuses = ['held', 'released', 'refunded'];
  $isPaymentSettled = in_array($paymentStatus, $settledStatuses, true);

  $balanceAmount = $isPaymentSettled ? 0.0 : $total;

  $payLabel = match ($paymentMethod) {
    'cash' => 'Cash baada ya huduma',
    'prepay' => 'Online (legacy order)',
    default => 'Haijachaguliwa bado',
  };

  $providerLat = is_numeric(data_get($order, 'provider.current_lat')) ? (float) data_get($order, 'provider.current_lat') : null;
  $providerLng = is_numeric(data_get($order, 'provider.current_lng')) ? (float) data_get($order, 'provider.current_lng') : null;
  $hasProviderCoords = $providerLat !== null && $providerLng !== null;
  $mapUrl = $hasProviderCoords ? ('https://www.google.com/maps?q=' . $providerLat . ',' . $providerLng) : null;

  $statusLabel = match ($status) {
    'pending' => 'Imepokelewa',
    'accepted', 'on_the_way', 'in_progress' => 'Yuko njiani anakuja...',
    'suspended' => 'Imepangwa, mtoa huduma atafika muda wa ratiba',
    'completed' => 'Imekamilika',
    'cancelled' => 'Imeghairiwa',
    default => $status,
  };

@endphp

<style>
  .orderClassic {
    max-width: 1060px;
    margin: 0 auto;
  }

  .orderClassic__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .orderClassic__title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    letter-spacing: -0.01em;
  }

  .orderClassic__sub {
    margin: 6px 0 0;
  }

  .orderClassic__badge {
    padding: 7px 12px;
    border-radius: 999px;
    background: #f4ecef;
    border: 1px solid #ead8df;
    color: #5a0e24;
    font-weight: 700;
    font-size: 0.8rem;
    white-space: nowrap;
  }

  .orderClassic__grid {
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 14px;
    margin-top: 14px;
  }

  .orderCard {
    border: 1px solid #ece7ea;
    border-radius: 14px;
    background: #fff;
    padding: 14px;
  }

  .orderCard__title {
    margin: 0 0 10px;
    font-size: 1rem;
    font-weight: 800;
    color: #1f2a37;
  }

  .orderCard__rows {
    display: grid;
    gap: 9px;
  }

  .orderCard__row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 0.93rem;
  }

  .orderCard__val {
    font-weight: 700;
    text-align: right;
  }

  .orderCard__hint {
    margin-top: 10px;
    font-size: 0.84rem;
  }

  .orderCard__cta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
  }

  .orderMsg {
    border-radius: 14px;
    border: 1px solid #efe3e8;
    background: linear-gradient(135deg, #fff6f8 0%, #ffffff 100%);
    padding: 14px;
  }

  .orderMsg__from {
    font-size: 0.78rem;
    color: #7b5160;
    font-weight: 700;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }

  .orderMsg__txt {
    margin: 0;
    font-size: 0.95rem;
    color: #2b3340;
  }

  .orderTrack {
    margin-top: 12px;
  }

  .orderTrack__meta {
    display: grid;
    gap: 7px;
    margin-top: 10px;
    font-size: 0.92rem;
  }

  .orderTrack__coords {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85rem;
    color: #1f2a37;
  }

  .orderClassic__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }

  .orderClassic__doneActions {
    margin-top: 14px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .paymentHero {
    border: 1px dashed #dac6cf;
    background: #fdf8fa;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 10px;
  }

  .paymentHero__label {
    font-size: 0.8rem;
    color: #7b5160;
  }

  .paymentHero__amount {
    font-size: 1.35rem;
    font-weight: 900;
    color: #4b0d1f;
    margin-top: 4px;
  }

  .paymentPill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #f4ecef;
    border: 1px solid #ead8df;
    font-size: 0.78rem;
    font-weight: 700;
    color: #5a0e24;
  }

  .liveDot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #1f9d55;
    display: inline-block;
  }

  @media (max-width: 960px) {
    .orderClassic__grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<section class="section">
  <div class="container">
    <div class="orderClassic">
      <div class="orderClassic__head">
        <div>
          <h1 class="orderClassic__title">Order {{ $order->order_no }}</h1>
          <p class="muted orderClassic__sub">Fuatilia progress, malipo, na location ya mtoa huduma hapa hapa.</p>
        </div>
        <div class="orderClassic__badge" id="orderStatusBadge">{{ $statusLabel }}</div>
      </div>

      @if($status !== 'cancelled')
        <div class="track" aria-label="Progress" id="orderProgressTrack" data-current-status="{{ $status }}">
          @foreach($steps as $i => $s)
            <div class="trackStep {{ $i <= $statusIndex ? 'is-done' : '' }}" data-step-key="{{ $s['k'] }}">
              <div class="trackDot" aria-hidden="true"></div>
              <div class="trackLabel">{{ $s['t'] }}</div>
            </div>
          @endforeach
        </div>
      @endif

      <div class="orderClassic__grid">
        <div>
          <article class="orderCard">
            <h2 class="orderCard__title">Muhtasari wa oda</h2>
            <div class="orderCard__rows">
              <div class="orderCard__row">
                <span class="muted">Huduma</span>
                <span class="orderCard__val">
                  @if($serviceList->count() > 1)
                    {{ $serviceList->implode(', ') }}
                  @else
                    {{ $serviceName }}
                  @endif
                </span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Mtoa huduma</span>
                <span class="orderCard__val">{{ $providerName }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Status</span>
                <span class="orderCard__val" id="orderStatusText">{{ $statusLabel }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Malipo</span>
                <span class="orderCard__val">{{ $payLabel }}@if($paymentChannel !== '') ({{ $paymentChannel }})@endif</span>
              </div>
              @if($paymentStatus !== '')
                <div class="orderCard__row">
                  <span class="muted">Payment status</span>
                  <span class="orderCard__val" id="paymentStatusText">{{ $paymentStatus }}</span>
                </div>
              @endif
              @if($couponCode !== '' && $discount > 0)
                <div class="orderCard__row">
                  <span class="muted">Coupon</span>
                  <span class="orderCard__val">{{ $couponCode }} (-TZS {{ number_format($discount, 0) }})</span>
                </div>
              @endif
            </div>

            @if($canEditWindow && $status === 'pending')
              <div class="orderCard__hint muted" id="editWindowHint">
                Unaweza kubadilisha huduma ndani ya: <strong id="editCountdown" data-seconds="{{ $remainingEditSeconds }}">{{ gmdate('i:s', $remainingEditSeconds) }}</strong>
              </div>
            @elseif($status === 'pending')
              <div class="orderCard__hint muted">Muda wa kubadilisha huduma umeisha (dakika 2).</div>
            @endif
          </article>

          <article class="orderMsg" style="margin-top:12px;">
            <div class="orderMsg__from">Ujumbe kutoka {{ $providerFirst }}</div>
            <p class="orderMsg__txt">
              Karibu {{ $clientFirst }}, nakupigia muda si mrefu. Endelea kufurahia huduma ya Glamo.
            </p>
          </article>

          <article class="orderCard orderTrack">
            <h2 class="orderCard__title">Tracking ya mtoa huduma</h2>
            <div class="paymentPill"><span class="liveDot"></span> Live tracking</div>

            <div class="orderTrack__meta">
              <div>
                <span class="muted">Status ya safari:</span>
                <strong id="trackingStatus">{{ $statusLabel }}</strong>
              </div>

              <div>
                <span class="muted">Location ya provider:</span>
                <div class="orderTrack__coords" id="trackingCoords">
                  @if($hasProviderCoords)
                    Lat {{ number_format($providerLat, 6) }}, Lng {{ number_format($providerLng, 6) }}
                  @else
                    Inasubiri location update...
                  @endif
                </div>
              </div>

              <div>
                <span class="muted">Updated:</span>
                <strong id="trackingUpdated">
                  @if(data_get($order, 'provider.last_location_at'))
                    {{ data_get($order, 'provider.last_location_at')->diffForHumans() }}
                  @else
                    bado
                  @endif
                </strong>
              </div>

              <div>
                @if($mapUrl)
                  <a class="btn btn--ghost btn--sm" href="{{ $mapUrl }}" target="_blank" rel="noopener" id="trackingMapLink">Fungua ramani</a>
                @else
                  <a class="btn btn--ghost btn--sm" href="#" id="trackingMapLink" style="display:none;">Fungua ramani</a>
                @endif
              </div>
            </div>
          </article>

          <article class="orderCard" style="margin-top:12px;">
            <h2 class="orderCard__title">Price breakdown</h2>
            <div class="orderCard__rows">
              <div class="orderCard__row">
                <span class="muted">Gharama ya huduma</span>
                <span class="orderCard__val">TZS {{ number_format((float) ($serviceFee ?? 0), 0) }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Vifaa na material</span>
                <span class="orderCard__val">TZS {{ number_format((float) ($materials ?? 0), 0) }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Usafiri</span>
                <span class="orderCard__val">TZS {{ number_format((float) ($travel ?? 0), 0) }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Matumizi</span>
                <span class="orderCard__val">TZS {{ number_format((float) ($usage ?? 0), 0) }}</span>
              </div>
              <div class="orderCard__row">
                <span class="muted">Subtotal</span>
                <span class="orderCard__val">TZS {{ number_format($subtotal, 0) }}</span>
              </div>
              @if($discount > 0)
                <div class="orderCard__row">
                  <span class="muted">Punguzo</span>
                  <span class="orderCard__val">-TZS {{ number_format($discount, 0) }}</span>
                </div>
              @endif
              <div class="orderCard__row" style="border-top:1px solid #ece7ea; padding-top:8px;">
                <span class="muted"><strong>Total amount</strong></span>
                <span class="orderCard__val">TZS {{ number_format($total, 0) }}</span>
              </div>
            </div>
          </article>

          @if($status === 'completed')
            <article class="orderCard" style="margin-top:12px;">
              <h2 class="orderCard__title">Review</h2>

              @if($hasReview)
                <div class="muted small">Ulishaweka review.</div>
                <div style="margin-top:10px;">
                  <div>Rating: {{ (int) data_get($reviewRow, 'rating', 0) }}/5</div>
                  @if((string) data_get($reviewRow, 'comment') !== '')
                    <div class="muted small" style="margin-top:6px;">{{ data_get($reviewRow, 'comment') }}</div>
                  @endif
                </div>
              @else
                <form method="POST" action="{{ route('orders.review.store', ['order' => $order->id]) }}">
                  @csrf

                  <label class="label" for="rating">Rating</label>
                  <select class="input input--select" id="rating" name="rating" required>
                    <option value="">Chagua...</option>
                    <option value="5">5 - Bora sana</option>
                    <option value="4">4 - Nzuri</option>
                    <option value="3">3 - Wastani</option>
                    <option value="2">2 - Mbaya</option>
                    <option value="1">1 - Mbaya sana</option>
                  </select>
                  @error('rating') <div class="err">{{ $message }}</div> @enderror

                  <label class="label" for="comment" style="margin-top:10px;">Maoni (hiari)</label>
                  <textarea class="input" id="comment" name="comment" rows="3" placeholder="Andika maoni...">{{ old('comment') }}</textarea>
                  @error('comment') <div class="err">{{ $message }}</div> @enderror

                  <button class="btn btn--primary wfull" style="margin-top:12px;" type="submit">Tuma review</button>
                </form>
              @endif
            </article>
          @endif
        </div>

        <div>
          <article class="orderCard">
            <h2 class="orderCard__title">Malipo ya oda</h2>

            <div class="paymentHero">
              <div class="paymentHero__label">Balance ya kulipa</div>
              <div class="paymentHero__amount" id="balanceAmount">TZS {{ number_format($balanceAmount, 0) }}</div>
              <div class="muted small" style="margin-top:6px;">
                @if($isPaymentSettled)
                  Malipo tayari yamekamilika.
                @else
                  @if($paymentMethod === 'prepay')
                    Kamilisha malipo sasa ili oda iendelee. Pesa itahifadhiwa salama (escrow) hadi kazi ikamilike.
                  @else
                    Malipo ni cash baada ya huduma.
                  @endif
                @endif
              </div>
            </div>

            @if(!$isPaymentSettled && $status !== 'cancelled' && $status !== 'completed')
              <div class="orderCard__cta" style="margin-top:10px;">
                <form method="POST" action="{{ route('orders.payment.mode', ['order' => $order->id]) }}" style="display:grid; gap:8px; width:100%;">
                  @csrf

                  <label class="label" for="orderPayMethod">Chagua njia ya malipo</label>
                  <select class="input input--select" id="orderPayMethod" name="payment_method">
                    <option value="cash" {{ $paymentMethod === 'cash' ? 'selected' : '' }}>Cash baada ya huduma</option>
                    <option value="prepay" {{ $paymentMethod === 'prepay' ? 'selected' : '' }}>Lipa sasa online</option>
                  </select>

                  <div id="orderPayChannelWrap" style="{{ $paymentMethod === 'prepay' ? '' : 'display:none;' }}">
                    <label class="label" for="orderPayChannel">Channel</label>
                    <select class="input input--select" id="orderPayChannel" name="payment_channel">
                      <option value="">Chagua channel</option>
                      <option value="mobile" {{ $paymentChannel === 'mobile' ? 'selected' : '' }}>Mobile money</option>
                      <option value="card" {{ $paymentChannel === 'card' ? 'selected' : '' }}>Card</option>
                    </select>
                  </div>

                  <button class="btn btn--ghost btn--sm" type="submit">Hifadhi njia ya malipo</button>
                </form>
              </div>

              @if($paymentMethod === 'prepay')
                <div class="orderCard__cta" style="margin-top:10px;">
                  <form method="POST" action="{{ route('orders.payment.start', ['order' => $order->id]) }}" style="display:grid; gap:8px; width:100%;">
                    @csrf
                    <input type="hidden" name="payment_channel" value="{{ $paymentChannel !== '' ? $paymentChannel : 'mobile' }}">

                    <div id="orderPayPhoneWrap" style="{{ $paymentChannel === 'mobile' ? '' : 'display:none;' }}">
                      <label class="label" for="orderPayPhone">Namba ya kulipia (mobile)</label>
                      <input class="input" id="orderPayPhone" name="phone_number" placeholder="07XXXXXXXX au 2557XXXXXXXX" value="{{ old('phone_number', auth()->user()->phone ?? '') }}">
                    </div>

                    <button class="btn btn--primary btn--sm" type="submit">Anzisha malipo</button>
                  </form>

                  <form method="POST" action="{{ route('orders.payment.refresh', ['order' => $order->id]) }}">
                    @csrf
                    <button class="btn btn--ghost btn--sm" type="submit">Refresh payment status</button>
                  </form>
                </div>
              @endif

              <div class="muted small" style="margin-top:8px;">
                Ukilipia online, ukisitisha oda kabla huduma kukamilika unaweza kuomba refund kwa kuweka sababu.
              </div>
            @endif
          </article>

          <article class="orderCard" style="margin-top:12px;">
            <h2 class="orderCard__title">Vitendo vya oda</h2>
            <div class="muted small">Huwezi kuweka booking nyingine mpaka oda hii ikamilike au u-cancel.</div>

            <div class="orderClassic__actions">
              @if($canEdit)
                <button class="btn btn--ghost" type="button" id="btnEditServices">Badilisha huduma</button>
              @endif

              @if($canCancel)
                <form method="POST" action="{{ route('orders.cancel', ['order' => $order->id]) }}" onsubmit="return confirm('Una uhakika unataka ku-cancel oda hii?');" style="display:grid; gap:8px; width:100%;">
                  @csrf
                  <label class="label" for="cancelReason">Sababu ya kusitisha/refund</label>
                  <textarea class="input" id="cancelReason" name="reason" rows="2" maxlength="500" required placeholder="Eleza kwa kifupi sababu ya kusitisha oda hii"></textarea>
                  <button class="btn btn--ghost" type="submit">Cancel oda</button>
                </form>
              @endif
            </div>
          </article>

          @if($isDone)
            <article class="orderCard" style="margin-top:12px;">
              <h2 class="orderCard__title">Hatua inayofuata</h2>
              <div class="muted small">Oda hii imefungwa. Unaweza kurejea nyumbani au ku-book huduma nyingine.</div>

              <div class="orderClassic__doneActions">
                <a class="btn btn--ghost" href="{{ route('landing') }}">Rudi nyumbani</a>
                <a class="btn btn--primary" href="{{ $serviceShowUrl }}">Book huduma nyingine</a>
              </div>
            </article>
          @endif

          <div class="muted small" style="margin-top:12px;">
            Ukihitaji msaada: <a href="{{ route('support') }}">Support</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

@if($canEdit)
  <div class="modal" id="orderServicesModal" aria-hidden="true">
    <div class="modal__card modal__card--wide" role="dialog" aria-modal="true" aria-labelledby="orderServicesTitle">
      <div class="modalHead">
        <div class="modal__title" id="orderServicesTitle">Badilisha huduma</div>
        <button class="btn btn--ghost btn--sm" type="button" data-ordsvc-close>Funga</button>
      </div>

      <input class="input" id="ordSvcSearch" placeholder="Tafuta huduma..." autocomplete="off">

      <form method="POST" action="{{ route('orders.services.update', ['order' => $order->id]) }}">
        @csrf
        <div class="allSvcList" id="ordSvcList">
          @foreach(($providerServices ?? collect()) as $svc)
            @php
              $svcPrice = (float) ($svc->pivot?->price_override ?? 0);
              if ($svcPrice <= 0) $svcPrice = (float) ($svc->base_price ?? 0);
              $svcMaterials = (float) ($svc->materials_price ?? 0);
              $svcUsage = round(($svcPrice * $usagePercent) / 100, 2);
              $svcTotal = $svcPrice + $svcMaterials + $svcUsage;
              $checked = in_array((int) $svc->id, $selectedServiceIds ?? [], true);
            @endphp

            <div class="allSvcItem" data-name="{{ strtolower((string) $svc->name) }}">
              <input class="allSvcItem__check" type="checkbox" name="service_ids[]" value="{{ (int) $svc->id }}" {{ $checked ? 'checked' : '' }}>
              <div class="allSvcItem__main">
                <div class="allSvcItem__name">{{ $svc->name }}</div>
                <div class="muted small">TZS {{ number_format($svcTotal, 0) }}</div>
              </div>
            </div>
          @endforeach
        </div>

        <div class="modal__actions">
          <button class="btn btn--primary" type="submit">Sawa</button>
        </div>
      </form>

      <div class="muted small" style="margin-top:10px;">Unaweza kubadilisha huduma ndani ya dakika 2 tangu oda kuundwa.</div>
    </div>
  </div>
@endif

<script>
(() => {
  const statusLabels = {
    pending: 'Imepokelewa',
    accepted: 'Yuko njiani anakuja...',
    on_the_way: 'Yuko njiani anakuja...',
    in_progress: 'Yuko njiani anakuja...',
    suspended: 'Imepangwa, mtoa huduma atafika muda wa ratiba',
    completed: 'Imekamilika',
    cancelled: 'Imeghairiwa',
  };

  function updateProgress(status) {
    const track = document.getElementById('orderProgressTrack');
    if (!track) return;

    const order = ['pending', 'on_the_way', 'completed'];
    const normalizedStatus = ['accepted', 'on_the_way', 'in_progress'].includes(status) ? 'on_the_way' : status;
    const idx = order.indexOf(normalizedStatus);
    if (idx < 0) return;

    track.querySelectorAll('[data-step-key]').forEach((el) => {
      const stepKey = String(el.getAttribute('data-step-key') || '');
      const stepIdx = order.indexOf(stepKey);
      el.classList.toggle('is-done', stepIdx >= 0 && stepIdx <= idx);
    });
  }

  const trackingUrl = @json(route('orders.tracking', ['order' => $order->id]));
  const statusText = document.getElementById('orderStatusText');
  const statusBadge = document.getElementById('orderStatusBadge');
  const trackingStatus = document.getElementById('trackingStatus');
  const trackingCoords = document.getElementById('trackingCoords');
  const trackingUpdated = document.getElementById('trackingUpdated');
  const mapLink = document.getElementById('trackingMapLink');
  const paymentStatusText = document.getElementById('paymentStatusText');

  async function pollTracking() {
    try {
      const res = await fetch(trackingUrl, {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
      });

      if (!res.ok) return;
      const payload = await res.json();
      if (!payload || !payload.order) return;

      const status = String(payload.order.status || '');
      const label = statusLabels[status] || status;

      if (statusText) statusText.textContent = label;
      if (statusBadge) statusBadge.textContent = label;
      if (trackingStatus) trackingStatus.textContent = label;
      updateProgress(status);

      if (paymentStatusText) {
        const payStatus = String(payload.order.payment_status || '');
        if (payStatus !== '') paymentStatusText.textContent = payStatus;
      }

      const lat = payload.provider ? payload.provider.lat : null;
      const lng = payload.provider ? payload.provider.lng : null;
      if (trackingCoords) {
        if (typeof lat === 'number' && typeof lng === 'number') {
          trackingCoords.textContent = `Lat ${lat.toFixed(6)}, Lng ${lng.toFixed(6)}`;
        } else {
          trackingCoords.textContent = 'Inasubiri location update...';
        }
      }

      if (mapLink) {
        if (typeof lat === 'number' && typeof lng === 'number') {
          mapLink.style.display = '';
          mapLink.setAttribute('href', `https://www.google.com/maps?q=${lat},${lng}`);
        } else {
          mapLink.style.display = 'none';
          mapLink.setAttribute('href', '#');
        }
      }

      if (trackingUpdated) {
        const ts = payload.provider ? String(payload.provider.last_location_at || '') : '';
        trackingUpdated.textContent = ts !== '' ? 'imeboreshwa sasa' : 'bado';
      }
    } catch (_e) {
      // silent in UI
    }
  }

  pollTracking();
  setInterval(pollTracking, 15000);

  const editCountdown = document.getElementById('editCountdown');
  if (editCountdown) {
    let seconds = Number(editCountdown.getAttribute('data-seconds') || 0);
    const btnEdit = document.getElementById('btnEditServices');
    const hint = document.getElementById('editWindowHint');

    const timer = setInterval(() => {
      seconds -= 1;
      if (seconds <= 0) {
        clearInterval(timer);
        editCountdown.textContent = '00:00';
        if (btnEdit) btnEdit.disabled = true;
        if (hint) hint.textContent = 'Muda wa kubadilisha huduma umeisha (dakika 2).';
        return;
      }

      const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
      const ss = String(seconds % 60).padStart(2, '0');
      editCountdown.textContent = `${mm}:${ss}`;
    }, 1000);
  }

  const modal = document.getElementById('orderServicesModal');
  const btnOpen = document.getElementById('btnEditServices');
  const btnClose = modal?.querySelector('[data-ordsvc-close]');
  const search = document.getElementById('ordSvcSearch');
  const list = document.getElementById('ordSvcList');

  function openServicesModal() {
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    if (search) search.focus();
  }

  function closeServicesModal() {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
  }

  btnOpen?.addEventListener('click', openServicesModal);
  btnClose?.addEventListener('click', closeServicesModal);
  modal?.addEventListener('click', (e) => { if (e.target === modal) closeServicesModal(); });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeServicesModal();
    }
  });

  search?.addEventListener('input', () => {
    const q = String(search.value || '').trim().toLowerCase();
    list?.querySelectorAll('.allSvcItem').forEach((el) => {
      const name = String(el.getAttribute('data-name') || '');
      el.style.display = !q || name.includes(q) ? '' : 'none';
    });
  });

  const methodSelect = document.getElementById('orderPayMethod');
  const channelWrap = document.getElementById('orderPayChannelWrap');
  const channelSelect = document.getElementById('orderPayChannel');
  const phoneWrap = document.getElementById('orderPayPhoneWrap');
  const phoneInput = document.getElementById('orderPayPhone');

  function syncOrderPaymentControls() {
    const method = String(methodSelect?.value || '').trim();
    const channel = String(channelSelect?.value || '').trim();
    const isPrepay = method === 'prepay';
    const needsPhone = isPrepay && channel === 'mobile';

    if (channelWrap) {
      channelWrap.style.display = isPrepay ? '' : 'none';
    }
    if (phoneWrap) {
      phoneWrap.style.display = needsPhone ? '' : 'none';
    }

    if (channelSelect) {
      if (isPrepay) {
        channelSelect.setAttribute('required', 'required');
      } else {
        channelSelect.removeAttribute('required');
      }
    }

    if (phoneInput) {
      if (needsPhone) {
        phoneInput.setAttribute('required', 'required');
      } else {
        phoneInput.removeAttribute('required');
      }
    }
  }

  methodSelect?.addEventListener('change', syncOrderPaymentControls);
  channelSelect?.addEventListener('change', syncOrderPaymentControls);
  syncOrderPaymentControls();
})();
</script>
@endsection
