@extends('public.layout')

@section('title', 'Ingia - Glamo')

@section('content')
<section class="section">
  <div class="container">
    <div class="auth">
      <div class="auth__card">
        <div class="authHeader">
          <h2 class="authTitle" id="authTitle">Ingia</h2>
          <p class="muted" id="authSubtitle">Tunapendekeza kutumia namba ya simu. Utapokea OTP kuthibitisha.</p>
        </div>

        <div class="authModeTabs" role="tablist" aria-label="Ingia au Jisajili">
          <button class="authModeTab {{ old('intent', 'login') === 'login' ? 'is-active' : '' }}" type="button" data-intent="login" role="tab" aria-selected="{{ old('intent', 'login') === 'login' ? 'true' : 'false' }}">Ingia</button>
          <button class="authModeTab {{ old('intent', 'login') === 'register' ? 'is-active' : '' }}" type="button" data-intent="register" role="tab" aria-selected="{{ old('intent', 'login') === 'register' ? 'true' : 'false' }}">Jisajili</button>
        </div>

        <div class="authTabs" role="tablist" aria-label="Njia ya kuingia">
          <button class="authTab {{ old('channel', 'phone') === 'phone' ? 'is-active' : '' }}" type="button" data-auth-tab="phone" role="tab" aria-selected="{{ old('channel', 'phone') === 'phone' ? 'true' : 'false' }}">Namba ya simu <span class="authTab__hint">(Recommended)</span></button>
          <button class="authTab {{ old('channel', 'phone') === 'email' ? 'is-active' : '' }}" type="button" data-auth-tab="email" role="tab" aria-selected="{{ old('channel', 'phone') === 'email' ? 'true' : 'false' }}">Email</button>
        </div>

        <form method="POST" action="{{ route('auth.sendOtp') }}" novalidate>
          @csrf
          <input type="hidden" name="channel" id="authChannel" value="{{ old('channel', 'phone') }}">
          <input type="hidden" name="intent" id="authIntent" value="{{ old('intent', 'login') }}">

          <div class="authPane" data-auth-pane="phone">
            <label class="label" for="countryCode">Code ya nchi</label>
            <div class="phoneRow">
              <select class="input input--select" id="countryCode" name="country_code" aria-label="Code ya nchi">
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

              <div class="phoneRow__input">
                <label class="sr-only" for="phoneLocal">Namba ya simu</label>
                <input class="input" id="phoneLocal" name="phone_local" inputmode="numeric" autocomplete="tel" placeholder="Mfano: 7XXXXXXXX" value="{{ old('phone_local') }}">
              </div>
            </div>
            @error('country_code') <div class="err">{{ $message }}</div> @enderror
            @error('phone_local') <div class="err">{{ $message }}</div> @enderror
            @error('phone') <div class="err">{{ $message }}</div> @enderror
          </div>

          <div class="authPane" data-auth-pane="email" hidden>
            <label class="label" for="emailInput">Email</label>
            <input class="input" id="emailInput" name="email" type="email" autocomplete="email" placeholder="Mfano: jina@example.com" value="{{ old('email') }}">
            @error('email') <div class="err">{{ $message }}</div> @enderror
          </div>

          <div class="authRole" id="authRole" hidden>
            <div class="label" style="margin-top:12px;">Unajisajili kama:</div>
            @php($role = old('role', 'client'))
            <div class="roleRow" role="radiogroup" aria-label="Chagua role">
              <label class="roleOpt">
                <input type="radio" name="role" value="client" {{ $role === 'client' ? 'checked' : '' }}>
                <span>Mteja</span>
              </label>
              <label class="roleOpt">
                <input type="radio" name="role" value="provider" {{ $role === 'provider' ? 'checked' : '' }}>
                <span>Mtoa huduma</span>
              </label>
            </div>
            @error('role') <div class="err">{{ $message }}</div> @enderror
          </div>

          <button class="btn btn--primary wfull" type="submit" id="authSubmitBtn">Tuma OTP</button>
          <div class="muted small">OTP inaisha ndani ya dakika 5. Kwa kuendelea unakubali masharti ya Glamo.</div>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
  (() => {
    const intentInput = document.getElementById('authIntent');
    const channelInput = document.getElementById('authChannel');
    const titleEl = document.getElementById('authTitle');
    const subtitleEl = document.getElementById('authSubtitle');
    const submitBtn = document.getElementById('authSubmitBtn');
    const roleWrap = document.getElementById('authRole');

    const modeBtns = Array.from(document.querySelectorAll('[data-intent]'));
    const tabBtns = Array.from(document.querySelectorAll('[data-auth-tab]'));
    const panes = Array.from(document.querySelectorAll('[data-auth-pane]'));

    function setIntent(next) {
      intentInput.value = next;
      modeBtns.forEach((b) => {
        const isActive = b.dataset.intent === next;
        b.classList.toggle('is-active', isActive);
        b.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      const isRegister = next === 'register';
      roleWrap.hidden = !isRegister;
      titleEl.textContent = isRegister ? 'Jisajili' : 'Ingia';
      subtitleEl.textContent = isRegister
        ? 'Jisajili kwa namba ya simu (Recommended) au email. Utapokea OTP kuthibitisha.'
        : 'Tunapendekeza kutumia namba ya simu. Utapokea OTP kuthibitisha.';
      submitBtn.textContent = isRegister ? 'Tuma OTP ya Usajili' : 'Tuma OTP';
    }

    function setChannel(next) {
      channelInput.value = next;
      tabBtns.forEach((b) => {
        const isActive = b.dataset.authTab === next;
        b.classList.toggle('is-active', isActive);
        b.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      panes.forEach((p) => (p.hidden = p.dataset.authPane !== next));
    }

    modeBtns.forEach((b) => b.addEventListener('click', () => setIntent(b.dataset.intent)));
    tabBtns.forEach((b) => b.addEventListener('click', () => setChannel(b.dataset.authTab)));

    setIntent(intentInput.value || 'login');
    setChannel(channelInput.value || 'phone');
  })();
</script>
@endsection
