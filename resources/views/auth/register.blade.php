@extends('public.layout')

@section('title', 'Jisajili - Glamo')

@section('content')
@php($authRedirect = trim((string) request()->query('redirect', '')))
<div class="authNova">
  @include('auth.partials.theme')

  <section>
    <div class="container authNova__wrap">
      <aside class="authNova__visual">
        <span class="authNova__badge">Fungua akaunti</span>
        <h1 class="authNova__heroTitle">Anza safari yako na Glamo.</h1>
        <p class="authNova__heroText">
          Thibitisha kwa OTP, weka nenosiri, na anza kutumia Glamo mara moja kama mteja.
        </p>

        <div class="authNova__checks">
          <div class="authNova__check">Uthibitisho wa OTP ndani ya dakika chache</div>
          <div class="authNova__check">Akaunti moja kwa huduma nyingi</div>
          <div class="authNova__check">Unaweza kuanza kwa simu au email</div>
        </div>

        <div class="authNova__miniStats">
          <div class="authNova__miniStat"><strong>5 dk</strong><span>Muda wa OTP</span></div>
          <div class="authNova__miniStat"><strong>Mteja</strong><span>Akaunti ya kuanzia</span></div>
          <div class="authNova__miniStat"><strong>Haraka</strong><span>Usajili</span></div>
        </div>
      </aside>

      <div class="authNova__card">
        <h2 class="authNova__title">Jisajili</h2>
        <p class="authNova__subtitle">Tuma OTP kwanza, kisha utaweka nenosiri.</p>

        @if($errors->any())
          <div class="authNova__errors">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="authNovaTabs" role="tablist" aria-label="Njia ya usajili">
          <button class="authNovaTab {{ old('channel', 'phone') === 'phone' ? 'is-active' : '' }}" type="button" data-auth-tab="phone" role="tab" aria-selected="{{ old('channel', 'phone') === 'phone' ? 'true' : 'false' }}">
            Namba ya simu
            <span class="authNovaTab__hint">Inapendekezwa</span>
          </button>
          <button class="authNovaTab {{ old('channel', 'phone') === 'email' ? 'is-active' : '' }}" type="button" data-auth-tab="email" role="tab" aria-selected="{{ old('channel', 'phone') === 'email' ? 'true' : 'false' }}">
            Email
          </button>
        </div>

        <form method="POST" action="{{ route('register.otp') }}" novalidate class="authNovaForm">
          @csrf
          <input type="hidden" name="channel" id="authChannel" value="{{ old('channel', 'phone') }}">
          <input type="hidden" name="role" value="client">

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

          <p class="authNovaMeta">OTP inaisha ndani ya dakika 5.</p>

          <p class="authNovaLinks">
            <a href="{{ route('login', $authRedirect !== '' ? ['redirect' => $authRedirect] : []) }}">Tayari una akaunti? Ingia</a>
          </p>
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
