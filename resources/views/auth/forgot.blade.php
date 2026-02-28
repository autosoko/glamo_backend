@extends('public.layout')

@section('title', 'Umesahau Nenosiri - Glamo')

@section('content')
@php($authRedirect = trim((string) request()->query('redirect', '')))
<div class="authNova">
  @include('auth.partials.theme')

  <section>
    <div class="container authNova__wrap">
      <aside class="authNova__visual">
        <span class="authNova__badge">Urejeshaji nenosiri</span>
        <h1 class="authNova__heroTitle">Rudisha akaunti yako kwa OTP.</h1>
        <p class="authNova__heroText">
          Tuma OTP kwa namba ya simu au email yako, kisha weka nenosiri jipya salama.
        </p>

        <div class="authNova__checks">
          <div class="authNova__check">Uthibitisho wa hatua 2 kwa usalama zaidi</div>
          <div class="authNova__check">OTP inatumika kwa muda mfupi tu</div>
          <div class="authNova__check">Ukikamilisha, unaweza kuingia mara moja</div>
        </div>

        <div class="authNova__miniStats">
          <div class="authNova__miniStat"><strong>5 dk</strong><span>Muda wa OTP</span></div>
          <div class="authNova__miniStat"><strong>Salama</strong><span>Urejeshaji</span></div>
          <div class="authNova__miniStat"><strong>Haraka</strong><span>Mchakato</span></div>
        </div>
      </aside>

      <div class="authNova__card">
        <h2 class="authNova__title">Umesahau Nenosiri</h2>
        <p class="authNova__subtitle">Thibitisha akaunti kwanza kwa OTP.</p>

        @if($errors->any())
          <div class="authNova__errors">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="authNovaTabs" role="tablist" aria-label="Njia ya kupata OTP">
          <button class="authNovaTab {{ old('channel', 'phone') === 'phone' ? 'is-active' : '' }}" type="button" data-auth-tab="phone" role="tab" aria-selected="{{ old('channel', 'phone') === 'phone' ? 'true' : 'false' }}">
            Namba ya simu
          </button>
          <button class="authNovaTab {{ old('channel', 'phone') === 'email' ? 'is-active' : '' }}" type="button" data-auth-tab="email" role="tab" aria-selected="{{ old('channel', 'phone') === 'email' ? 'true' : 'false' }}">
            Email
          </button>
        </div>

        <form method="POST" action="{{ route('password.otp') }}" novalidate class="authNovaForm">
          @csrf
          <input type="hidden" name="channel" id="authChannel" value="{{ old('channel', 'phone') }}">

          <div class="authPane" data-auth-pane="phone">
            <label class="authNovaLabel" for="countryCode">Code ya nchi</label>
            <div class="authNovaPhoneRow">
              <select class="authNovaSelect" id="countryCode" name="country_code" aria-label="Code ya nchi">
                @php($cc = old('country_code', '255'))
                <option value="255" {{ $cc === '255' ? 'selected' : '' }}>Tanzania (+255)</option>
                <option value="254" {{ $cc === '254' ? 'selected' : '' }}>Kenya (+254)</option>
                <option value="256" {{ $cc === '256' ? 'selected' : '' }}>Uganda (+256)</option>
                <option value="250" {{ $cc === '250' ? 'selected' : '' }}>Rwanda (+250)</option>
                <option value="257" {{ $cc === '257' ? 'selected' : '' }}>Burundi (+257)</option>
                <option value="243" {{ $cc === '243' ? 'selected' : '' }}>DRC (+243)</option>
                <option value="27" {{ $cc === '27' ? 'selected' : '' }}>South Africa (+27)</option>
                <option value="234" {{ $cc === '234' ? 'selected' : '' }}>Nigeria (+234)</option>
                <option value="251" {{ $cc === '251' ? 'selected' : '' }}>Ethiopia (+251)</option>
                <option value="1" {{ $cc === '1' ? 'selected' : '' }}>USA/Canada (+1)</option>
                <option value="44" {{ $cc === '44' ? 'selected' : '' }}>UK (+44)</option>
              </select>

              <input class="authNovaInput" id="phoneLocal" name="phone_local" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 7XXXXXXXX" value="{{ old('phone_local') }}">
            </div>
            @error('country_code') <div class="authNovaErr">{{ $message }}</div> @enderror
            @error('phone_local') <div class="authNovaErr">{{ $message }}</div> @enderror
            @error('phone') <div class="authNovaErr">{{ $message }}</div> @enderror
          </div>

          <div class="authPane" data-auth-pane="email" hidden>
            <label class="authNovaLabel" for="emailInput">Email</label>
            <input class="authNovaInput" id="emailInput" name="email" type="email" autocomplete="email" placeholder="Mfano: jina@example.com" value="{{ old('email') }}">
            @error('email') <div class="authNovaErr">{{ $message }}</div> @enderror
          </div>

          <button class="authNovaBtn authNovaBtn--primary" type="submit">Tuma OTP</button>

          <p class="authNovaLinks"><a href="{{ route('login', $authRedirect !== '' ? ['redirect' => $authRedirect] : []) }}">Rudi kuingia</a></p>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
  (() => {
    const channelInput = document.getElementById('authChannel');
    const tabBtns = Array.from(document.querySelectorAll('[data-auth-tab]'));
    const panes = Array.from(document.querySelectorAll('[data-auth-pane]'));

    function setChannel(next) {
      channelInput.value = next;

      tabBtns.forEach((btn) => {
        const active = btn.dataset.authTab === next;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panes.forEach((pane) => {
        const active = pane.dataset.authPane === next;
        pane.hidden = !active;
        pane.querySelectorAll('input, select, textarea').forEach((el) => {
          el.disabled = !active;
        });
      });
    }

    tabBtns.forEach((btn) => {
      btn.addEventListener('click', () => setChannel(btn.dataset.authTab));
    });

    setChannel(channelInput.value || 'phone');
  })();
</script>
@endsection
