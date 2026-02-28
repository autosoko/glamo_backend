@extends('public.layout')

@section('title', 'Dashibodi - Mtoa Huduma')

@section('custom_header')
  @php
    $headerOffline = strtolower((string) data_get($provider, 'online_status', 'offline')) !== 'online';
  @endphp
  <header class="providerDashHeader">
    <div class="container providerDashHeader__inner">
      <a class="providerDashHeader__brand providerDashHeader__brand--text" href="{{ route('provider.dashboard') }}">
        <strong>Glamo Pro</strong>
        <span>Dashibodi ya Mtoa Huduma</span>
      </a>

      <div class="providerDashHeader__actions">
        <span class="pdOnlineState {{ $headerOffline ? 'pdOnlineState--danger' : 'pdOnlineState--ok' }}">
          {{ $headerOffline ? 'OFFLINE' : 'ONLINE' }}
        </span>
        <button class="btn btn--primary btn--sm pdMenuToggle" type="button" data-menu-open aria-label="Fungua menu ya akaunti">
          <span class="pdMenuToggle__icon" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
          </span>
        </button>
      </div>
    </div>
  </header>
@endsection

@section('content')
@php
  $debt = (float) (data_get($provider, 'debt_balance') ?? 0);
  $totalEarnAmount = (float) ($totalEarn ?? 0);
  $walletBalanceAmount = (float) ($walletBalance ?? 0);
  $pendingEscrowAmount = (float) ($pendingEscrow ?? 0);
  $debtThreshold = (float) ($debtBlock ?? 10000);
  $providerOnlineStatusRaw = strtolower((string) data_get($provider, 'online_status', 'offline'));
  $blocked = $debt > $debtThreshold || ($providerOnlineStatusRaw === 'blocked_debt' && $debt >= $debtThreshold);
  $status = (string) ($approvalStatus ?? ($provider->approval_status ?? 'pending'));
  $canOperate = (bool) ($canOperate ?? false);
  $popupOrder = $dashboardPopupOrder ?? null;
  $actionOrder = $activeActionOrder ?? null;

  $formatDate = function ($value, string $fallback = '-') {
      if ($value instanceof \Illuminate\Support\Carbon) {
          return $value->format('d/m/Y H:i');
      }
      if ($value) {
          try {
              return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y H:i');
          } catch (\Throwable $e) {
              return (string) $value;
          }
      }
      return $fallback;
  };

  $interviewAtText = $formatDate(data_get($provider, 'interview_scheduled_at'), 'Bado haijapangwa');
  $submittedAtText = $formatDate(data_get($provider, 'onboarding_submitted_at'));

  $selectedSkillNames = collect($selectedSkills ?? [])->map(function ($slug) {
      return [
          'misuko' => 'Misuko',
          'makeup' => 'Makeup',
          'kubana' => 'Kubana',
          'massage' => 'Massage',
      ][strtolower((string) $slug)] ?? ucfirst((string) $slug);
  })->values();

  $activeIds = collect($activeServiceIds ?? [])->map(fn ($id) => (int) $id)->all();

  $locationParts = array_filter([
      (string) ($provider->region ?? ''),
      (string) ($provider->district ?? ''),
      (string) ($provider->ward ?? ''),
      (string) ($provider->village ?? ''),
  ]);
  if ((string) ($provider->house_number ?? '') !== '') {
      $locationParts[] = 'Nyumba ' . (string) $provider->house_number;
  }

  $providerName = trim((string) ($provider->business_nickname ?? ''));
  if ($providerName === '') {
      $providerName = trim((string) (($provider->first_name ?? '') . ' ' . ($provider->middle_name ?? '') . ' ' . ($provider->last_name ?? '')));
  }
  if ($providerName === '') {
      $providerName = (string) (auth()->user()->name ?? 'Mtoa huduma');
  }
  $providerShortName = trim((string) strtok($providerName, ' '));
  if ($providerShortName === '') {
      $providerShortName = $providerName;
  }
  $providerShortName = ucfirst(strtolower($providerShortName));

  $defaultDebtPhone = old('phone_number', (string) ($provider->phone_public ?: (auth()->user()->phone ?? '255')));
  if ($defaultDebtPhone === '') {
      $defaultDebtPhone = '255';
  }

  $profileSkillOptions = collect($skillOptions ?? []);
  $profileSelectedSkills = collect(old('selected_skills', $selectedSkills->all() ?? []))
      ->map(fn ($slug) => strtolower(trim((string) $slug)))
      ->filter()
      ->unique()
      ->values()
      ->all();

  $onlineStatus = strtolower((string) ($provider->online_status ?? 'offline'));
  $onlineStatusLabels = [
      'online' => 'Online',
      'blocked_debt' => 'Offline (Deni)',
      'busy' => 'Busy',
      'offline' => 'Offline',
  ];
  $onlineStatusLabel = $onlineStatusLabels[$onlineStatus] ?? ucfirst($onlineStatus);
  $debtDanger = $blocked;
  $showDebtPayButton = $debt >= 5000;
  $availabilityControlData = (array) ($availabilityControl ?? []);
  $availabilityNextAction = (string) data_get($availabilityControlData, 'next_action', $onlineStatus === 'online' ? 'offline' : 'online');
  if (!in_array($availabilityNextAction, ['online', 'offline'], true)) {
      $availabilityNextAction = $onlineStatus === 'online' ? 'offline' : 'online';
  }
  $availabilityCanToggle = (bool) data_get($availabilityControlData, 'can_toggle', true);
  $availabilityReason = trim((string) data_get($availabilityControlData, 'reason', ''));
  $availabilityButtonLabel = $availabilityNextAction === 'online' ? 'Jiweke Online' : 'Jiweke Offline';
  $debtCardClass = $debtDanger ? 'pdStatCard--dangerSurface' : 'pdStatCard--brand';
  $debtMetaClass = $debtDanger ? 'pdStatCard__meta--dangerLight' : 'pdStatCard__meta--light';
  $debtLabelClass = 'pdStatCard__label--light';
  $debtValueClass = 'pdStatCard__value--light';
  $debtAmountDefault = old('debt_amount', (string) (((int) round($debt)) > 0 ? (int) round($debt) : 5000));
  $openDebtPayModal = $errors->has('debt_amount')
      || $errors->has('payment_channel')
      || $errors->has('phone_number');
  $openWithdrawModal = $errors->has('amount')
      || $errors->has('method')
      || $errors->has('destination');
  $withdrawAmountDefault = old('amount', (string) max(1, (int) floor($walletBalanceAmount)));
  $withdrawMethodDefault = old('method', 'mobile_money');
  $withdrawDestinationDefault = old('destination', (string) ($provider->phone_public ?: (auth()->user()->phone ?? '')));

  $profileErrorFields = [
      'business_nickname',
      'phone_public',
      'alt_phone',
      'bio',
      'region',
      'district',
      'ward',
      'village',
      'house_number',
      'selected_skills',
  ];
  $openProviderMenu = collect($profileErrorFields)->contains(function (string $field) use ($errors) {
      return $errors->has($field) || $errors->has($field . '.*');
  });

  $ordersCollection = collect($orders ?? []);
  $debtLedgersCollection = collect($debtLedgers ?? []);
  $debtPaymentsCollection = collect($debtPayments ?? []);
  $activeOrders = $ordersCollection->whereNotIn('status', ['completed', 'cancelled'])->count();
  $completedOrders = $ordersCollection->where('status', 'completed')->count();

  $orderStatusLabels = [
      'pending' => 'Inasubiri',
      'accepted' => 'Yuko njiani anakuja...',
      'on_the_way' => 'Yuko njiani anakuja...',
      'arrived' => 'Yuko njiani anakuja...',
      'started' => 'Yuko njiani anakuja...',
      'in_progress' => 'Yuko njiani anakuja...',
      'suspended' => 'Imepangwa kwenda baadaye',
      'completed' => 'Imekamilika',
      'cancelled' => 'Imefutwa',
  ];

  $orderStatusClasses = [
      'pending' => 'pdStatus--warn',
      'accepted' => 'pdStatus--info',
      'on_the_way' => 'pdStatus--info',
      'arrived' => 'pdStatus--info',
      'started' => 'pdStatus--info',
      'in_progress' => 'pdStatus--info',
      'suspended' => 'pdStatus--neutral',
      'completed' => 'pdStatus--ok',
      'cancelled' => 'pdStatus--danger',
  ];

  $popupStatus = strtolower((string) data_get($popupOrder, 'status', ''));
  $popupIsPending = $popupStatus === 'pending';
  $popupScheduleLocked = in_array($popupStatus, ['accepted', 'on_the_way', 'in_progress'], true)
      && !empty(data_get($popupOrder, 'schedule_notified_at'));
  $popupDecisionLocked = $popupIsPending || $popupScheduleLocked;
  $popupHasCoords = is_numeric(data_get($popupOrder, 'client_lat')) && is_numeric(data_get($popupOrder, 'client_lng'));
  $popupCoordsText = $popupHasCoords
      ? 'Mtaa haujawekwa. Bonyeza "Fungua ramani" kuona location.'
      : 'Location ya mteja haijapatikana.';
  $popupMapUrl = $popupHasCoords
      ? ('https://www.google.com/maps?q=' . (float) data_get($popupOrder, 'client_lat') . ',' . (float) data_get($popupOrder, 'client_lng'))
      : null;
  $approveModeOld = old('approve_mode', 'now');
  $approveLaterSelected = $approveModeOld === 'later';
  $approveScheduleRaw = trim((string) old('scheduled_for', ''));
  $approveScheduleValue = '';
  if ($approveScheduleRaw !== '') {
      try {
          $approveScheduleValue = \Illuminate\Support\Carbon::parse($approveScheduleRaw)->format('Y-m-d\TH:i');
      } catch (\Throwable $e) {
          $approveScheduleValue = $approveScheduleRaw;
      }
  }

  $actionStatus = strtolower((string) data_get($actionOrder, 'status', ''));
  $actionCanComplete = in_array($actionStatus, ['accepted', 'on_the_way', 'in_progress'], true);
  $actionHasCoords = is_numeric(data_get($actionOrder, 'client_lat')) && is_numeric(data_get($actionOrder, 'client_lng'));
  $actionCoordsText = $actionHasCoords
      ? 'Mtaa haujawekwa. Bonyeza "Fungua ramani" kuona location.'
      : 'Location ya mteja haijapatikana.';
  $actionMapUrl = $actionHasCoords
      ? ('https://www.google.com/maps?q=' . (float) data_get($actionOrder, 'client_lat') . ',' . (float) data_get($actionOrder, 'client_lng'))
      : null;

  $serviceCardImage = function ($service) {
      return (string) (data_get($service, 'primary_image_url') ?: asset('images/placeholder.svg'));
  };

  $serviceDurationText = function ($minutes) {
      $total = (int) $minutes;
      if ($total <= 0) {
          $total = 60;
      }

      $h = intdiv($total, 60);
      $m = $total % 60;

      if ($h <= 0) {
          return $m . ' dk';
      }

      if ($m <= 0) {
          return $h . ' saa';
      }

      return $h . ' saa ' . $m . ' dk';
  };
@endphp

<section class="section providerDash">
  <div class="container">
    <div class="providerDash__hero">
      <div class="providerDash__heroMain">
        <span class="pdEyebrow">Glamo Pro Dashboard</span>
        <h1 class="pdTitle">Karibu, {{ $providerShortName }}</h1>

        <div class="pdHeroQuickGrid">
          <div class="pdHeroQuickItem">
            <span>Oda zinazoendelea</span>
            <strong>{{ number_format($activeOrders) }}</strong>
          </div>
          <div class="pdHeroQuickItem">
            <span>Oda zilizokamilika</span>
            <strong>{{ number_format($completedOrders) }}</strong>
          </div>
        </div>
      </div>
    </div>

    <article class="pdPanel" style="margin-top:12px;">
      <div class="pdPanel__head">
        <h3 class="pdPanel__title">Status ya Upatikanaji</h3>
        <span class="pdPanel__sub">Jiweke online au offline kwa kubonyeza button hapa chini</span>
      </div>

      <div class="pdActionGrid" style="margin-bottom:12px;">
        <div class="pdActionItem">
          <span>Hali ya sasa</span>
          <strong>{{ $onlineStatusLabel }}</strong>
          @if((string) ($provider->offline_reason ?? '') !== '')
            <div class="muted small">{{ (string) $provider->offline_reason }}</div>
          @endif
        </div>

        <div class="pdActionItem">
          <span>Badilisha status</span>
          <form method="POST" action="{{ route('provider.availability.update') }}" style="margin-top:6px;">
            @csrf
            <input type="hidden" name="online_status" value="{{ $availabilityNextAction }}">
            <button class="btn btn--primary btn--sm" type="submit" {{ $availabilityCanToggle ? '' : 'disabled' }}>
              {{ $availabilityButtonLabel }}
            </button>
          </form>
          @if(!$availabilityCanToggle && $availabilityReason !== '')
            <div class="muted small" style="margin-top:6px;">{{ $availabilityReason }}</div>
          @endif
          @error('online_status')
            <div class="err" style="margin-top:6px;">{{ $message }}</div>
          @enderror
        </div>
      </div>
    </article>

    <div class="pdMenuSheet {{ $openProviderMenu ? 'is-open' : '' }}" id="providerMenuSheet" data-sheet-open="{{ $openProviderMenu ? '1' : '0' }}">
      <div class="pdMenuSheet__backdrop" data-menu-close></div>
      <aside class="pdMenuSheet__panel" role="dialog" aria-modal="true" aria-label="Menu ya mtoa huduma">
        <div class="pdMenuSheet__head">
          <div>
            <h3 class="pdMenuSheet__title">Menu ya Akaunti</h3>
            <p class="pdMenuSheet__sub">Hapa ndipo una-edit profile, taarifa binafsi na kutoka akaunti.</p>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-menu-close>Funga</button>
        </div>

        <div class="pdMenuProfile">
          <div class="pdMenuProfile__name">{{ $providerName }}</div>
          <div class="pdMenuProfile__line">{{ $onlineStatusLabel }}</div>
          <div class="pdMenuProfile__line">{{ (string) ($provider->phone_public ?: (auth()->user()->phone ?? '-')) }}</div>
        </div>

        <div class="pdMenuMeta">
          <div class="pdMetaItem">
            <span class="pdMetaItem__k">Tarehe ya submit</span>
            <span class="pdMetaItem__v">{{ $submittedAtText }}</span>
          </div>
          <div class="pdMetaItem">
            <span class="pdMetaItem__k">Eneo la kazi</span>
            <span class="pdMetaItem__v">{{ !empty($locationParts) ? implode(', ', $locationParts) : '-' }}</span>
          </div>
          <div class="pdMetaItem">
            <span class="pdMetaItem__k">Ujuzi</span>
            <span class="pdMetaItem__v">{{ $selectedSkillNames->isNotEmpty() ? $selectedSkillNames->join(', ') : '-' }}</span>
          </div>
        </div>

        <div class="pdDivider"></div>

        <div class="pdPanel__head pdPanel__head--tight">
          <h3 class="pdPanel__title">Profile Yangu</h3>
          <span class="pdPanel__sub">Badilisha taarifa za profile na ujuzi</span>
        </div>

        <form method="POST" action="{{ route('provider.profile.update') }}" class="pdFormStack" id="profile-form">
          @csrf

          <label class="label" for="profileNickname">Nickname ya biashara</label>
          <input
            class="input"
            id="profileNickname"
            name="business_nickname"
            value="{{ old('business_nickname', (string) ($provider->business_nickname ?? '')) }}"
            placeholder="Mfano: Erick Beauty Studio"
          >
          @error('business_nickname') <div class="err">{{ $message }}</div> @enderror

          <label class="label" for="profilePhone">Simu ya kazi</label>
          <input
            class="input"
            id="profilePhone"
            name="phone_public"
            value="{{ old('phone_public', (string) ($provider->phone_public ?: (auth()->user()->phone ?? ''))) }}"
            placeholder="Mfano: 07XXXXXXXX au 2557XXXXXXXX"
            required
          >
          @error('phone_public') <div class="err">{{ $message }}</div> @enderror

          <label class="label" for="profileAltPhone">Simu mbadala</label>
          <input
            class="input"
            id="profileAltPhone"
            name="alt_phone"
            value="{{ old('alt_phone', (string) ($provider->alt_phone ?? '')) }}"
            placeholder="Hiari"
          >
          @error('alt_phone') <div class="err">{{ $message }}</div> @enderror

          <label class="label" for="profileBio">Bio</label>
          <textarea class="input" id="profileBio" name="bio" rows="3" placeholder="Andika maelezo mafupi ya profile yako...">{{ old('bio', (string) ($provider->bio ?? '')) }}</textarea>
          @error('bio') <div class="err">{{ $message }}</div> @enderror

          <div class="pdProfileGrid">
            <div>
              <label class="label" for="profileRegion">Mkoa</label>
              <input class="input" id="profileRegion" name="region" value="{{ old('region', (string) ($provider->region ?? '')) }}">
              @error('region') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="label" for="profileDistrict">Wilaya</label>
              <input class="input" id="profileDistrict" name="district" value="{{ old('district', (string) ($provider->district ?? '')) }}">
              @error('district') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="label" for="profileWard">Kata</label>
              <input class="input" id="profileWard" name="ward" value="{{ old('ward', (string) ($provider->ward ?? '')) }}">
              @error('ward') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="label" for="profileVillage">Mtaa/Kijiji</label>
              <input class="input" id="profileVillage" name="village" value="{{ old('village', (string) ($provider->village ?? '')) }}">
              @error('village') <div class="err">{{ $message }}</div> @enderror
            </div>
            <div>
              <label class="label" for="profileHouse">Namba ya nyumba</label>
              <input class="input" id="profileHouse" name="house_number" value="{{ old('house_number', (string) ($provider->house_number ?? '')) }}">
              @error('house_number') <div class="err">{{ $message }}</div> @enderror
            </div>
          </div>

          <label class="label">Ujuzi</label>
          @if($profileSkillOptions->isEmpty())
            <div class="pdEmpty">Hakuna list ya category kwa sasa.</div>
          @else
            <div class="pdSkillGrid">
              @foreach($profileSkillOptions as $skillSlug => $skillName)
                <label class="pdSkillChip">
                  <input
                    type="checkbox"
                    name="selected_skills[]"
                    value="{{ $skillSlug }}"
                    {{ in_array((string) $skillSlug, $profileSelectedSkills, true) ? 'checked' : '' }}
                  >
                  <span>{{ $skillName }}</span>
                </label>
              @endforeach
            </div>
          @endif
          @error('selected_skills') <div class="err">{{ $message }}</div> @enderror
          @error('selected_skills.*') <div class="err">{{ $message }}</div> @enderror

          <button class="btn btn--primary wfull" type="submit">Hifadhi mabadiliko ya profile</button>
        </form>

        <div class="pdDivider"></div>
        <div class="pdPanel__head pdPanel__head--tight">
          <h3 class="pdPanel__title">Historia ya Deni</h3>
          <span class="pdPanel__sub">Mabadiliko ya commission</span>
        </div>
        @if($debtLedgersCollection->isEmpty())
          <div class="pdEmpty">Hakuna rekodi bado.</div>
        @else
          <div class="pdLedgerList">
            @foreach($debtLedgersCollection as $l)
              <div class="pdLedgerItem">
                <div>
                  <div class="pdLedgerItem__title">{{ $l->type }}</div>
                  <div class="pdLedgerItem__meta">{{ $l->note }}</div>
                  <div class="pdLedgerItem__meta">{{ $l->created_at }}</div>
                </div>
                <div class="pdLedgerItem__amount">
                  <div>{{ number_format((float) $l->amount, 0) }}</div>
                  <div class="pdLedgerItem__meta">Bal: {{ number_format((float) $l->balance_after, 0) }}</div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        <div class="pdDivider"></div>
        <div class="pdPanel__head pdPanel__head--tight">
          <h3 class="pdPanel__title">Malipo ya deni ya hivi karibuni</h3>
          <span class="pdPanel__sub">Historia ya malipo uliyojaribu au kukamilisha</span>
        </div>
        @if($debtPaymentsCollection->isEmpty())
          <div class="pdEmpty">Bado hujalipa deni kupitia mfumo.</div>
        @else
          <div class="pdLedgerList">
            @foreach($debtPaymentsCollection->take(8) as $pmt)
              <div class="pdLedgerItem">
                <div>
                  <div class="pdLedgerItem__title">{{ strtoupper((string) ($pmt->method ?? 'mobile')) }}</div>
                  <div class="pdLedgerItem__meta">{{ $pmt->reference ?: '-' }}</div>
                  <div class="pdLedgerItem__meta">{{ $pmt->created_at }}</div>
                </div>
                <div class="pdLedgerItem__amount">
                  <div>TZS {{ number_format((float) ($pmt->amount ?? 0), 0) }}</div>
                  <div class="pdLedgerItem__meta">{{ ucfirst((string) ($pmt->status ?? 'pending')) }}</div>
                </div>
              </div>
            @endforeach
          </div>
        @endif

        <div class="pdMenuActions">
          <a class="btn btn--ghost wfull" href="{{ route('support') }}">Msaada</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn--danger wfull" type="submit">Ondoka</button>
          </form>
        </div>
      </aside>
    </div>

    @if($status === 'needs_more_steps' || $status === 'rejected')
      <div class="pdPanel pdPanel--interview">
        <h3 class="pdPanel__title">Ratiba ya interview/demo</h3>
        <div class="pdInterviewGrid">
          <div class="pdInterviewItem">
            <span class="pdInterviewItem__k">Tarehe na saa</span>
            <span class="pdInterviewItem__v">{{ $interviewAtText }}</span>
          </div>
          <div class="pdInterviewItem">
            <span class="pdInterviewItem__k">Aina ya interview</span>
            <span class="pdInterviewItem__v">{{ data_get($provider, 'interview_type') ?: 'Demo ya vitendo' }}</span>
          </div>
          <div class="pdInterviewItem">
            <span class="pdInterviewItem__k">Eneo</span>
            <span class="pdInterviewItem__v">{{ data_get($provider, 'interview_location') ?: 'Utapewa taarifa baadaye' }}</span>
          </div>
          <div class="pdInterviewItem">
            <span class="pdInterviewItem__k">Maelezo</span>
            <span class="pdInterviewItem__v">{{ data_get($provider, 'approval_note') ?: data_get($provider, 'rejection_reason') ?: 'Subiri ujumbe kutoka timu yetu.' }}</span>
          </div>
        </div>
      </div>
    @endif

    @if($popupOrder)
      @php
        $popupOrderNo = (string) ($popupOrder->order_no ?? 'N/A');
        $popupServiceName = (string) (data_get($popupOrder, 'service.name') ?? 'Huduma');
        $popupClientName = (string) (data_get($popupOrder, 'client.name') ?? 'Mteja');
        $popupClientPhone = (string) (data_get($popupOrder, 'client.phone') ?? '-');
        $popupAmount = (float) ($popupOrder->price_total ?? 0);
        $popupAddress = trim((string) ($popupOrder->address_text ?? ''));
      @endphp

      <div class="pdOrderModal" id="providerOrderPopup" data-locked="{{ $popupDecisionLocked ? '1' : '0' }}">
        <div class="pdOrderModal__backdrop" {{ $popupDecisionLocked ? '' : 'data-popup-close' }}></div>
        <div class="pdOrderModal__card" role="dialog" aria-modal="true" aria-labelledby="providerOrderPopupTitle">
          <div class="pdOrderModal__head">
            <div>
              <h3 class="pdOrderModal__title" id="providerOrderPopupTitle">
                {{ $popupIsPending ? 'Oda Mpya Imeingia' : 'Oda Inakusubiri Uikamilishe' }}
              </h3>
              <p class="pdOrderModal__sub">
                {{ $popupIsPending ? 'Kwenye approve, chagua kama unaenda sasa au unapanga muda wa kwenda baadaye.' : 'Mteja na location sasa vinaonekana baada ya approval.' }}
              </p>
            </div>

            @if(!$popupDecisionLocked)
              <button class="btn btn--ghost btn--sm" type="button" data-popup-close>Funga</button>
            @endif
          </div>

          <div class="pdOrderModal__metaGrid">
            <div class="pdOrderModal__metaItem">
              <span>Order</span>
              <strong>{{ $popupOrderNo }}</strong>
            </div>
            <div class="pdOrderModal__metaItem">
              <span>Huduma</span>
              <strong>{{ $popupServiceName }}</strong>
            </div>
            <div class="pdOrderModal__metaItem">
              <span>Mteja</span>
              <strong>{{ $popupClientName }}</strong>
            </div>
            <div class="pdOrderModal__metaItem">
              <span>Amount</span>
              <strong>TZS {{ number_format($popupAmount, 0) }}</strong>
            </div>
          </div>

          @if($popupIsPending)
            <div class="pdOrderModal__actions">
              <form
                method="POST"
                action="{{ route('provider.orders.approve', ['order' => (int) $popupOrder->id]) }}"
                class="pdApproveForm"
                data-approve-form
              >
                @csrf
                <div class="pdApproveMode">
                  <label class="pdApproveOption">
                    <input
                      type="radio"
                      name="approve_mode"
                      value="now"
                      {{ !$approveLaterSelected ? 'checked' : '' }}
                      data-approve-mode
                    >
                    <span>Naenda sasa</span>
                  </label>
                  <label class="pdApproveOption">
                    <input
                      type="radio"
                      name="approve_mode"
                      value="later"
                      {{ $approveLaterSelected ? 'checked' : '' }}
                      data-approve-mode
                    >
                    <span>Nitaenda baadaye</span>
                  </label>
                </div>
                @error('approve_mode') <div class="err">{{ $message }}</div> @enderror

                <div class="pdApproveSchedule {{ $approveLaterSelected ? '' : 'is-hidden' }}" data-approve-schedule>
                  <label class="label" for="approveScheduleInput">Ratiba ya kwenda (tarehe na saa)</label>
                  <input
                    class="input"
                    id="approveScheduleInput"
                    type="datetime-local"
                    name="scheduled_for"
                    value="{{ $approveScheduleValue }}"
                    {{ $approveLaterSelected ? '' : 'disabled' }}
                  >
                  @error('scheduled_for') <div class="err">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn--primary" type="submit">Approve</button>
              </form>

              <form method="POST" action="{{ route('provider.orders.reject', ['order' => (int) $popupOrder->id]) }}" class="pdOrderModal__reject">
                @csrf
                <input class="input" type="text" name="reason" placeholder="Sababu ya reject (hiari)">
                <button class="btn btn--ghost" type="submit">Reject</button>
              </form>
            </div>
          @else
            <div class="pdOrderModal__secure">
              <div class="pdOrderModal__secureItem">
                <span>Simu ya mteja</span>
                <strong>{{ $popupClientPhone }}</strong>
              </div>
              <div class="pdOrderModal__secureItem">
                <span>Location ya mteja</span>
                <strong>{{ $popupAddress !== '' ? $popupAddress : $popupCoordsText }}</strong>
              </div>
              @if($popupMapUrl)
                <a class="btn btn--ghost btn--sm" href="{{ $popupMapUrl }}" target="_blank" rel="noopener">Fungua ramani</a>
              @endif
            </div>

            <div class="pdOrderModal__actions pdOrderModal__actions--stack">
              <form method="POST" action="{{ route('provider.orders.complete', ['order' => (int) $popupOrder->id]) }}" class="pdInlineForm pdInlineForm--compact">
                @csrf
                <input class="input" type="text" name="note" placeholder="Maelezo ya kumaliza (hiari)">
                <button class="btn btn--primary" type="submit">Nimemaliza kazi</button>
              </form>
            </div>
          @endif
        </div>
      </div>
    @endif

    <div class="pdStats pdStats--finance">
      <article class="pdStatCard pdStatCard--brand pdStatCard--earnLayout">
        <div class="pdStatCard__head">
          <span class="pdStatCard__label pdStatCard__label--light">Jumla ya mapato yako</span>
          <button
            class="pdAmountToggle"
            type="button"
            data-amount-toggle
            data-target="earn"
            aria-label="Onyesha kiasi"
            aria-pressed="false"
          >
            <span class="pdAmountToggle__eye" aria-hidden="true"></span>
          </button>
        </div>
        <strong
          class="pdStatCard__value pdStatCard__value--light"
          data-amount-value="earn"
          data-amount="{{ (int) round($totalEarnAmount) }}"
          data-visible="0"
        >
          TZS •••••
        </strong>
      </article>

      <article class="pdStatCard {{ $debtCardClass }}">
        <div class="pdStatCard__head">
          <span class="pdStatCard__label {{ $debtLabelClass }}">Unadaiwa</span>
          <button
            class="pdAmountToggle"
            type="button"
            data-amount-toggle
            data-target="debt"
            aria-label="Onyesha kiasi"
            aria-pressed="false"
          >
            <span class="pdAmountToggle__eye" aria-hidden="true"></span>
          </button>
        </div>
        <strong
          class="pdStatCard__value {{ $debtValueClass }}"
          data-amount-value="debt"
          data-amount="{{ (int) round($debt) }}"
          data-visible="0"
        >
          TZS •••••
        </strong>
        @if($blocked)
          <span class="pdStatCard__meta {{ $debtMetaClass }}">Umezidi TZS {{ number_format((float) ($debtBlock ?? 10000), 0) }}, online imefungwa kwa sasa.</span>
        @else
          <span class="pdStatCard__meta {{ $debtMetaClass }}">Kikomo cha kufungiwa online ni TZS {{ number_format((float) ($debtBlock ?? 10000), 0) }}.</span>
        @endif

        @if($showDebtPayButton)
          <div class="pdDebtCardAction">
            <button class="btn btn--ghost btn--sm" type="button" data-debt-modal-open>Lipia deni sasa</button>
          </div>
        @endif
      </article>
    </div>

    <article class="pdPanel" style="margin-top:12px;">
      <div class="pdPanel__head">
        <h3 class="pdPanel__title">Wallet na Withdrawal</h3>
        <span class="pdPanel__sub">Pesa ya online huingia wallet baada ya oda kukamilika</span>
      </div>

      <div class="pdActionGrid" style="margin-bottom:12px;">
        <div class="pdActionItem">
          <span>Wallet balance</span>
          <strong>TZS {{ number_format($walletBalanceAmount, 0) }}</strong>
        </div>
        <div class="pdActionItem">
          <span>Pending escrow</span>
          <strong>TZS {{ number_format($pendingEscrowAmount, 0) }}</strong>
        </div>
        <div class="pdActionItem">
          <span>Commission rule</span>
          <strong>{{ rtrim(rtrim(number_format((float) config('glamo_pricing.commission_percent', 10), 2, '.', ''), '0'), '.') }}%</strong>
        </div>
      </div>

      <form method="POST" action="{{ route('provider.withdraw') }}" class="pdFormStack">
        @csrf
        <label class="label" for="withdrawAmount">Kiasi cha kutoa (TZS)</label>
        <input class="input" id="withdrawAmount" name="amount" inputmode="numeric" value="{{ $withdrawAmountDefault }}" required>
        @error('amount') <div class="err">{{ $message }}</div> @enderror

        <label class="label" for="withdrawMethod">Njia ya payout</label>
        <select class="input" id="withdrawMethod" name="method">
          <option value="mobile_money" @selected($withdrawMethodDefault === 'mobile_money')>Mobile money</option>
          <option value="mpesa" @selected($withdrawMethodDefault === 'mpesa')>M-Pesa</option>
          <option value="tigopesa" @selected($withdrawMethodDefault === 'tigopesa')>Tigo Pesa</option>
          <option value="airtelmoney" @selected($withdrawMethodDefault === 'airtelmoney')>Airtel Money</option>
          <option value="halopesa" @selected($withdrawMethodDefault === 'halopesa')>HaloPesa</option>
        </select>
        @error('method') <div class="err">{{ $message }}</div> @enderror

        <label class="label" for="withdrawDestination">Namba ya kupokea payout</label>
        <input class="input" id="withdrawDestination" name="destination" type="tel" inputmode="numeric" value="{{ $withdrawDestinationDefault }}" placeholder="07XXXXXXXX au 2557XXXXXXXX">
        @error('destination') <div class="err">{{ $message }}</div> @enderror

        <div class="muted small">
          Vigezo na masharti: 1) Commission ya {{ rtrim(rtrim(number_format((float) config('glamo_pricing.commission_percent', 10), 2, '.', ''), '0'), '.') }}% hukatwa kwenye kila oda.
          2) Kiasi kinachobaki (takriban 90%) huingia wallet baada ya kubonyeza "Nimemaliza kazi".
          3) Unaweza kuwithdraw kiasi kilichopo wallet tu.
        </div>

        <button class="btn btn--primary wfull" type="submit" {{ $walletBalanceAmount > 0 ? '' : 'disabled' }}>
          Toa pesa kutoka wallet
        </button>
      </form>
    </article>

    <div class="pdOrderModal {{ $openDebtPayModal ? '' : 'is-hidden' }}" id="providerDebtModal">
      <div class="pdOrderModal__backdrop" data-debt-modal-close></div>
      <div class="pdOrderModal__card pdOrderModal__card--sm" role="dialog" aria-modal="true" aria-labelledby="providerDebtModalTitle">
        <div class="pdOrderModal__head">
          <div>
            <h3 class="pdOrderModal__title" id="providerDebtModalTitle">Lipa deni la sasa</h3>
            <p class="pdOrderModal__sub">Weka kiasi na namba ya simu, kisha thibitisha prompt kwenye simu yako.</p>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-debt-modal-close>Funga</button>
        </div>

        <form method="POST" action="{{ route('provider.debt.pay') }}" class="pdFormStack pdDebtModalForm">
          @csrf

          <label class="label" for="debtPayAmount">Kiasi cha kulipa deni (TZS)</label>
          <input class="input" id="debtPayAmount" name="debt_amount" placeholder="Mfano: 10000" inputmode="numeric" value="{{ $debtAmountDefault }}" required>
          @error('debt_amount') <div class="err">{{ $message }}</div> @enderror

          <div class="pdDebtActions">
            <button class="btn btn--ghost btn--sm" type="button" data-fill-debt="5000">TZS 5,000</button>
            <button class="btn btn--ghost btn--sm" type="button" data-fill-debt="10000">TZS 10,000</button>
            <button class="btn btn--ghost btn--sm" type="button" data-fill-debt="{{ (int) round($debt) }}">Lipa deni lote</button>
          </div>

          <label class="label" for="debtPaymentChannel">Mtandao wa malipo</label>
          <select class="input" id="debtPaymentChannel" name="payment_channel" required>
            <option value="">Chagua channel</option>
            <option value="mpesa" @selected(old('payment_channel') === 'mpesa')>M-Pesa</option>
            <option value="tigopesa" @selected(old('payment_channel') === 'tigopesa')>Tigo Pesa</option>
            <option value="airtelmoney" @selected(old('payment_channel') === 'airtelmoney')>Airtel Money</option>
            <option value="halopesa" @selected(old('payment_channel') === 'halopesa')>HaloPesa</option>
          </select>
          @error('payment_channel') <div class="err">{{ $message }}</div> @enderror

          <label class="label" for="debtPayPhone">Namba ya kulipia</label>
          <input
            class="input"
            id="debtPayPhone"
            name="phone_number"
            type="tel"
            inputmode="numeric"
            autocomplete="tel"
            placeholder="Mfano: 07XXXXXXXX au 2557XXXXXXXX"
            value="{{ $defaultDebtPhone }}"
            required
          >
          @error('phone_number') <div class="err">{{ $message }}</div> @enderror

          <div class="muted small" style="margin-top:8px;">
            Utapokea prompt ya kuthibitisha malipo kwenye simu yako. Deni litapungua baada ya webhook ya uthibitisho wa malipo.
          </div>

          <button class="btn btn--primary wfull" type="submit" {{ ($debt <= 0) ? 'disabled' : '' }}>
            Lipa deni sasa
          </button>
        </form>
      </div>
    </div>

    <div class="pdMain pdMain--single">
      <div class="pdMain__left">
        @if($actionOrder)
          @php
            $actionOrderNo = (string) ($actionOrder->order_no ?? 'N/A');
            $actionServiceName = (string) (data_get($actionOrder, 'service.name') ?? 'Huduma');
            $actionClientName = (string) (data_get($actionOrder, 'client.name') ?? 'Mteja');
            $actionClientPhone = (string) (data_get($actionOrder, 'client.phone') ?? '-');
            $actionAmount = (float) ($actionOrder->price_total ?? 0);
            $actionAddress = trim((string) ($actionOrder->address_text ?? ''));
          @endphp

          <article class="pdPanel pdPanel--action">
            <div class="pdPanel__head">
              <h3 class="pdPanel__title">Oda ya Kufanyia Kazi Sasa</h3>
              <span class="pdPanel__sub">{{ $orderStatusLabels[$actionStatus] ?? ucfirst($actionStatus) }}</span>
            </div>

            <div class="pdActionGrid">
              <div class="pdActionItem">
                <span>Order</span>
                <strong>{{ $actionOrderNo }}</strong>
              </div>
              <div class="pdActionItem">
                <span>Huduma</span>
                <strong>{{ $actionServiceName }}</strong>
              </div>
              <div class="pdActionItem">
                <span>Mteja</span>
                <strong>{{ $actionClientName }}</strong>
              </div>
              <div class="pdActionItem">
                <span>Amount</span>
                <strong>TZS {{ number_format($actionAmount, 0) }}</strong>
              </div>
              <div class="pdActionItem">
                <span>Namba ya mteja</span>
                <strong>{{ $actionClientPhone }}</strong>
              </div>
              <div class="pdActionItem">
                <span>Location</span>
                <strong>{{ $actionAddress !== '' ? $actionAddress : $actionCoordsText }}</strong>
              </div>
            </div>

            <div class="pdActionButtons">
              @if($actionMapUrl)
                <a class="btn btn--ghost btn--sm" href="{{ $actionMapUrl }}" target="_blank" rel="noopener">Fungua ramani</a>
              @endif

              @if($actionCanComplete)
                <form method="POST" action="{{ route('provider.orders.complete', ['order' => (int) $actionOrder->id]) }}" class="pdInlineForm pdInlineForm--compact">
                  @csrf
                  <input class="input" type="text" name="note" placeholder="Maelezo ya kumaliza (hiari)">
                  <button class="btn btn--primary btn--sm" type="submit">Nimemaliza kazi</button>
                </form>
              @endif
            </div>
          </article>
        @endif

        <article class="pdPanel">
          <div class="pdPanel__head">
            <h3 class="pdPanel__title">Oda za hivi karibuni</h3>
            <span class="pdPanel__sub">Ufuatiliaji wa kazi, malipo na commission</span>
          </div>

          <div class="pdTableWrap">
            <table class="pdTable">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Status</th>
                  <th>Malipo</th>
                  <th class="is-num">Jumla</th>
                  <th class="is-num">Commission</th>
                  <th class="is-num">Unapata</th>
                </tr>
              </thead>
              <tbody>
                @forelse(($orders ?? collect()) as $o)
                  @php
                    $t = (float) ($o->price_total ?? 0);
                    $c = (float) ($o->commission_amount ?? 0);
                    $p = (float) ($o->payout_amount ?? max(0, $t - $c));
                    $pm = (string) ($o->payment_method ?? '');
                    $pmLabel = $pm === 'prepay' ? 'Online' : 'Cash';
                    $orderStatus = strtolower((string) ($o->status ?? 'pending'));
                    $orderStatusLabel = $orderStatusLabels[$orderStatus] ?? ucfirst($orderStatus);
                    $orderStatusClass = $orderStatusClasses[$orderStatus] ?? 'pdStatus--neutral';
                  @endphp
                  <tr>
                    <td>{{ $o->order_no }}</td>
                    <td><span class="pdStatus {{ $orderStatusClass }}">{{ $orderStatusLabel }}</span></td>
                    <td>{{ $pmLabel }}</td>
                    <td class="is-num">{{ number_format($t, 0) }}</td>
                    <td class="is-num">{{ number_format($c, 0) }}</td>
                    <td class="is-num">{{ number_format($p, 0) }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="pdEmptyCell">Huna oda kwa sasa.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </article>

        <article class="pdPanel" id="huduma-zangu">
          <div class="pdPanel__head">
            <h3 class="pdPanel__title">Huduma unazotoa</h3>
            <span class="pdPanel__sub">Ona ulizochagua, kisha expand huduma zote ukitaka kuongeza nyingine</span>
          </div>

          @if(!$canOperate)
            <div class="flash flash--error" style="margin:0 0 12px;">
              Profile yako bado haijapitishwa kikamilifu. Unaweza kuona huduma, lakini button ya Add huduma itabaki disabled hadi uidhinishwe.
            </div>
          @endif

          @php
            $allowedServicesCollection = collect($allowedServices ?? []);
            $selectedServices = $allowedServicesCollection
              ->filter(fn ($svc) => in_array((int) $svc->id, $activeIds, true))
              ->values();
          @endphp

          @if($allowedServicesCollection->isEmpty())
            <div class="pdEmpty">Hakuna huduma za kuchagua kwa sasa.</div>
          @else
            <div class="pdSelectedWrap">
              <div class="pdPanel__head pdPanel__head--tight">
                <h4 class="pdPanel__title">Huduma ulizochagua</h4>
                <span class="pdPanel__sub">{{ number_format($selectedServices->count()) }} huduma active</span>
              </div>

              @if($selectedServices->isEmpty())
                <div class="pdEmpty">Bado hujachagua huduma. Bonyeza “Onyesha huduma zote” kisha chagua unazotaka.</div>
              @else
                <div class="pdServiceCatalog pdServiceCatalog--selected">
                  @foreach($selectedServices as $svc)
                    @php
                      $sid = (int) $svc->id;
                      $svcImage = $serviceCardImage($svc);
                      $svcDuration = $serviceDurationText($svc->duration_minutes ?? 60);
                      $svcCat = data_get($svc, 'category.name') ?: ucfirst((string) ($svc->category ?? '-'));
                      $svcDesc = trim((string) ($svc->short_desc ?? ''));
                    @endphp

                    <a
                      href="{{ route('provider.services.show', ['service' => $sid]) }}"
                      class="pdSvcCard pdSvcCard--selected {{ !$canOperate ? 'is-disabled' : '' }}"
                    >
                      <div class="pdSvcCard__media">
                        <img src="{{ $svcImage }}" alt="{{ $svc->name }}" loading="lazy">
                        <span class="pdSvcCard__pill pdSvcCard__pill--on">Imechaguliwa</span>
                      </div>

                      <div class="pdSvcCard__body">
                        <div class="pdSvcCard__top">
                          <h4 class="pdSvcCard__title">{{ $svc->name }}</h4>
                          <span class="pdSvcCard__cat">{{ $svcCat }}</span>
                        </div>

                        <div class="pdSvcCard__meta">
                          <span>{{ $svcDuration }}</span>
                          <span>-</span>
                          <span>TZS {{ number_format((float) ($svc->base_price ?? 0), 0) }}</span>
                        </div>

                        @if($svcDesc !== '')
                          <p class="pdSvcCard__desc">{{ $svcDesc }}</p>
                        @else
                          <p class="pdSvcCard__desc">Bonyeza hapa uone taarifa zaidi za huduma hii.</p>
                        @endif
                      </div>
                    </a>
                  @endforeach
                </div>
              @endif
            </div>

            @php
              $serviceCategories = $allowedServicesCollection
                ->map(function ($svc) {
                    $catName = (string) (data_get($svc, 'category.name') ?: ucfirst((string) ($svc->category ?? '-')));
                    $catKey = strtolower((string) (data_get($svc, 'category.slug') ?: $catName));

                    return [
                        'key' => trim($catKey),
                        'name' => trim($catName),
                    ];
                })
                ->filter(fn ($item) => $item['key'] !== '' && $item['name'] !== '')
                ->unique('key')
                ->sortBy('name')
                ->values();

              $collapseAllByDefault = $selectedServices->isNotEmpty();
            @endphp

            <div class="pdAllServicesToggle">
              <button
                class="btn btn--ghost btn--sm"
                type="button"
                id="pdToggleAllServices"
                data-collapsed="{{ $collapseAllByDefault ? '1' : '0' }}"
                data-default-collapsed="{{ $collapseAllByDefault ? '1' : '0' }}"
                aria-expanded="{{ $collapseAllByDefault ? 'false' : 'true' }}"
                aria-controls="pdAllServicesWrap"
              >
                {{ $collapseAllByDefault ? 'Onyesha huduma zote' : 'Ficha huduma zote' }}
              </button>
              <span class="muted small">Tumia hii expand/collapse kuonyesha au kuficha orodha ya huduma zote.</span>
            </div>

            <div id="pdAllServicesWrap" class="pdAllServicesWrap {{ $collapseAllByDefault ? 'is-collapsed' : '' }}">
              <div class="pdServiceTools">
                <div class="pdServiceTools__search">
                  <input
                    class="input"
                    type="search"
                    id="pdServiceSearch"
                    placeholder="Tafuta huduma kwa jina..."
                    autocomplete="off"
                  >
                </div>

                <div class="pdServiceTools__filters">
                  <select class="input" id="pdServiceCategory">
                    <option value="">Category zote</option>
                    @foreach($serviceCategories as $cat)
                      <option value="{{ $cat['key'] }}">{{ $cat['name'] }}</option>
                    @endforeach
                  </select>

                  <select class="input" id="pdServiceState">
                    <option value="">Hali zote</option>
                    <option value="added">Zilizoongezwa</option>
                    <option value="not_added">Zisizoongezwa</option>
                  </select>
                </div>
              </div>

              <div class="pdServiceTools__meta" id="pdServiceCount"></div>

              <div class="pdServiceCatalog" id="pdServiceCatalog">
                @foreach($allowedServicesCollection as $svc)
                  @php
                    $sid = (int) $svc->id;
                    $isActive = in_array($sid, $activeIds, true);
                    $svcImage = $serviceCardImage($svc);
                    $svcDuration = $serviceDurationText($svc->duration_minutes ?? 60);
                    $svcCat = data_get($svc, 'category.name') ?: ucfirst((string) ($svc->category ?? '-'));
                    $svcCatKey = strtolower((string) (data_get($svc, 'category.slug') ?: $svcCat));
                    $svcDesc = trim((string) ($svc->short_desc ?? ''));
                    $svcSearch = strtolower(trim((string) ($svc->name . ' ' . $svcCat . ' ' . $svcDesc)));
                  @endphp

                  <a
                    href="{{ route('provider.services.show', ['service' => $sid]) }}"
                    class="pdSvcCard {{ $isActive ? 'is-added' : '' }} {{ !$canOperate ? 'is-disabled' : '' }}"
                    data-service-search="{{ $svcSearch }}"
                    data-service-category="{{ $svcCatKey }}"
                    data-service-added="{{ $isActive ? '1' : '0' }}"
                  >
                    <div class="pdSvcCard__media">
                      <img src="{{ $svcImage }}" alt="{{ $svc->name }}" loading="lazy">
                      <span class="pdSvcCard__pill {{ $isActive ? 'pdSvcCard__pill--on' : 'pdSvcCard__pill--off' }}">
                        {{ $isActive ? 'Imeongezwa' : 'Haijaongezwa' }}
                      </span>
                    </div>

                    <div class="pdSvcCard__body">
                      <div class="pdSvcCard__top">
                        <h4 class="pdSvcCard__title">{{ $svc->name }}</h4>
                        <span class="pdSvcCard__cat">{{ $svcCat }}</span>
                      </div>

                      <div class="pdSvcCard__meta">
                        <span>{{ $svcDuration }}</span>
                        <span>-</span>
                        <span>TZS {{ number_format((float) ($svc->base_price ?? 0), 0) }}</span>
                      </div>

                      @if($svcDesc !== '')
                        <p class="pdSvcCard__desc">{{ $svcDesc }}</p>
                      @else
                        <p class="pdSvcCard__desc">Bonyeza hapa uone picha zote na taarifa zaidi za huduma hii.</p>
                      @endif
                    </div>
                  </a>
                @endforeach
              </div>
            </div>
          @endif
        </article>
      </div>

    </div>
  </div>
</section>

<script>
  (function () {
    const catalog = document.getElementById('pdServiceCatalog');
    const searchInput = document.getElementById('pdServiceSearch');
    const categorySelect = document.getElementById('pdServiceCategory');
    const stateSelect = document.getElementById('pdServiceState');
    const countEl = document.getElementById('pdServiceCount');
    const allServicesWrap = document.getElementById('pdAllServicesWrap');
    const toggleAllBtn = document.getElementById('pdToggleAllServices');
    const popup = document.getElementById('providerOrderPopup');
    const menuSheet = document.getElementById('providerMenuSheet');
    const menuOpenButtons = Array.from(document.querySelectorAll('[data-menu-open]'));
    const menuCloseButtons = Array.from(document.querySelectorAll('[data-menu-close]'));
    const debtModal = document.getElementById('providerDebtModal');
    const debtOpenButtons = Array.from(document.querySelectorAll('[data-debt-modal-open]'));
    const debtCloseButtons = Array.from(document.querySelectorAll('[data-debt-modal-close]'));

    if (popup) {
      const locked = String(popup.getAttribute('data-locked') || '0') === '1';
      const closeEls = Array.from(popup.querySelectorAll('[data-popup-close]'));
      const approveForm = popup.querySelector('[data-approve-form]');

      const closePopup = () => {
        if (locked) return;
        popup.classList.add('is-hidden');
      };

      closeEls.forEach((el) => {
        el.addEventListener('click', (event) => {
          event.preventDefault();
          closePopup();
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closePopup();
        }
      });

      if (approveForm) {
        const approveModes = Array.from(approveForm.querySelectorAll('[data-approve-mode]'));
        const scheduleWrap = approveForm.querySelector('[data-approve-schedule]');
        const scheduleInput = approveForm.querySelector('input[name="scheduled_for"]');

        const syncApproveSchedule = () => {
          const selected = approveModes.find((input) => input.checked)?.value || 'now';
          const useSchedule = selected === 'later';

          if (scheduleWrap) {
            scheduleWrap.classList.toggle('is-hidden', !useSchedule);
          }

          if (scheduleInput) {
            scheduleInput.disabled = !useSchedule;
            scheduleInput.required = useSchedule;
          }
        };

        approveModes.forEach((input) => {
          input.addEventListener('change', syncApproveSchedule);
        });

        syncApproveSchedule();
      }
    }

    if (menuSheet) {
      const openMenu = () => {
        menuSheet.classList.add('is-open');
        document.body.classList.add('is-provider-menu-open');
      };

      const closeMenu = () => {
        menuSheet.classList.remove('is-open');
        document.body.classList.remove('is-provider-menu-open');
      };

      const startOpen = String(menuSheet.getAttribute('data-sheet-open') || '0') === '1';
      if (startOpen) openMenu();

      menuOpenButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          openMenu();
        });
      });

      menuCloseButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          closeMenu();
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeMenu();
        }
      });
    }

    if (debtModal) {
      const openDebtModal = () => {
        debtModal.classList.remove('is-hidden');
      };

      const closeDebtModal = () => {
        debtModal.classList.add('is-hidden');
      };

      debtOpenButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          openDebtModal();
        });
      });

      debtCloseButtons.forEach((btn) => {
        btn.addEventListener('click', (event) => {
          event.preventDefault();
          closeDebtModal();
        });
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeDebtModal();
        }
      });
    }

    const amountToggles = Array.from(document.querySelectorAll('[data-amount-toggle]'));
    const amountMask = 'TZS •••••';

    function formatAmountValue(amount) {
      return `TZS ${Number.isFinite(amount) ? amount.toLocaleString('en-US') : '0'}`;
    }

    function setAmountVisibility(btn, valueEl, visible) {
      const amount = Number(valueEl.getAttribute('data-amount') || '0');
      const showValue = Boolean(visible);

      valueEl.textContent = showValue ? formatAmountValue(amount) : amountMask;
      valueEl.setAttribute('data-visible', showValue ? '1' : '0');
      valueEl.classList.toggle('is-hidden', !showValue);

      btn.classList.toggle('is-revealed', showValue);
      btn.setAttribute('aria-pressed', showValue ? 'true' : 'false');
      btn.setAttribute('aria-label', showValue ? 'Ficha kiasi' : 'Onyesha kiasi');
    }

    amountToggles.forEach((btn) => {
      const target = String(btn.getAttribute('data-target') || '').trim();
      if (target === '') return;

      const valueEl = document.querySelector(`[data-amount-value="${target}"]`);
      if (!valueEl) return;

      setAmountVisibility(btn, valueEl, false);

      btn.addEventListener('click', () => {
        const isVisible = String(valueEl.getAttribute('data-visible') || '0') === '1';
        setAmountVisibility(btn, valueEl, !isVisible);
      });
    });

    const debtAmountInput = document.getElementById('debtPayAmount');
    const debtFillButtons = Array.from(document.querySelectorAll('[data-fill-debt]'));
    debtFillButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const val = String(btn.getAttribute('data-fill-debt') || '').trim();
        if (debtAmountInput && val !== '') {
          debtAmountInput.value = val;
          debtAmountInput.focus();
        }
      });
    });

    function syncAllServicesToggle(collapsed) {
      if (!allServicesWrap || !toggleAllBtn) return;

      allServicesWrap.classList.toggle('is-collapsed', collapsed);
      toggleAllBtn.setAttribute('data-collapsed', collapsed ? '1' : '0');
      toggleAllBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      toggleAllBtn.textContent = collapsed ? 'Onyesha huduma zote' : 'Ficha huduma zote';
    }

    if (allServicesWrap && toggleAllBtn) {
      const startCollapsed = String(toggleAllBtn.getAttribute('data-default-collapsed') || '0') === '1';
      syncAllServicesToggle(startCollapsed);

      toggleAllBtn.addEventListener('click', () => {
        const isCollapsed = String(toggleAllBtn.getAttribute('data-collapsed') || '0') === '1';
        syncAllServicesToggle(!isCollapsed);
      });
    }

    if (!catalog) return;

    const cards = Array.from(catalog.querySelectorAll('.pdSvcCard'));
    if (cards.length === 0) return;

    function normalize(v) {
      return String(v || '').toLowerCase().trim();
    }

    function applyFilters() {
      const query = normalize(searchInput?.value);
      const cat = normalize(categorySelect?.value);
      const state = normalize(stateSelect?.value);

      let visible = 0;

      cards.forEach((card) => {
        const text = normalize(card.getAttribute('data-service-search'));
        const cardCat = normalize(card.getAttribute('data-service-category'));
        const isAdded = card.getAttribute('data-service-added') === '1';

        const byText = query === '' || text.includes(query);
        const byCat = cat === '' || cardCat === cat;
        const byState = state === ''
          || (state === 'added' && isAdded)
          || (state === 'not_added' && !isAdded);

        const show = byText && byCat && byState;
        card.classList.toggle('is-hidden', !show);

        if (show) visible += 1;
      });

      if (countEl) {
        countEl.textContent = `Inaonyesha huduma ${visible} kati ya ${cards.length}.`;
      }
    }

    searchInput?.addEventListener('input', applyFilters);
    categorySelect?.addEventListener('change', applyFilters);
    stateSelect?.addEventListener('change', applyFilters);
    applyFilters();
  })();
</script>
@endsection

@section('page_footer')
  <footer class="providerSimpleFooter">
    <div class="container providerSimpleFooter__inner">
      <div class="providerSimpleFooter__brand">
        <img src="{{ asset('images/logo.png') }}" alt="Glamo" loading="lazy">
        <span>Dashibodi ya Mtoa Huduma</span>
      </div>
      <div class="providerSimpleFooter__copy">© {{ date('Y') }} Glamo</div>
    </div>
  </footer>
@endsection
