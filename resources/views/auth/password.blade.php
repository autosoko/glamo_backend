@extends('public.layout')

@section('title', 'Weka Nenosiri - Glamo')

@section('content')
<div class="authNova">
  @include('auth.partials.theme')

  <section>
    <div class="container authNova__wrap">
      <aside class="authNova__visual">
        <span class="authNova__badge">Mpangilio wa nenosiri</span>
        <h1 class="authNova__heroTitle">Weka nenosiri jipya salama.</h1>
        <p class="authNova__heroText">
          Akaunti inayohusika: <b>{{ $destination ?? '' }}</b>. Hakikisha nenosiri lina nguvu kwa usalama wa akaunti.
        </p>

        <div class="authNova__checks">
          <div class="authNova__check">Tumia angalau herufi 6 au zaidi</div>
          <div class="authNova__check">Usitumie nenosiri la akaunti nyingine</div>
          <div class="authNova__check">Baada ya kuhifadhi utaingia moja kwa moja</div>
        </div>
      </aside>

      <div class="authNova__card">
        <h2 class="authNova__title">{{ ($intent ?? '') === 'reset' ? 'Weka Nenosiri Jipya' : 'Weka Nenosiri' }}</h2>
        <p class="authNova__subtitle">
          @if(!empty($phoneOtpRequired))
            Kwanza thibitisha namba ya simu kwa OTP, kisha weka nenosiri jipya.
          @else
            Andika nenosiri jipya mara mbili kuthibitisha.
          @endif
        </p>

        @if($errors->any())
          <div class="authNova__errors">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @if(!empty($phoneOtpRequired))
          <div class="authNovaInlineHint" style="margin-bottom:10px;">
            Kwa mteja aliyesajili kwa email, namba ya simu ni lazima na lazima ithibitishwe kwa OTP.
          </div>

          @if(!empty($phoneVerified))
            <div class="notice" style="margin-bottom:12px;">
              Namba imethibitishwa: <strong>{{ $phoneDestinationMasked ?? '' }}</strong>
            </div>
          @else
            <form method="POST" action="{{ route('password.phone.otp.send') }}" novalidate class="authNovaForm" style="margin-bottom:12px;">
              @csrf

              <div>
                <label class="authNovaLabel" for="countryCode">Code ya nchi</label>
                <div class="authNovaPhoneRow">
                  @php($cc = old('country_code', '255'))
                  <select class="authNovaSelect" id="countryCode" name="country_code" aria-label="Code ya nchi">
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

                  <input
                    class="authNovaInput"
                    id="phoneLocal"
                    name="phone_local"
                    inputmode="numeric"
                    autocomplete="tel"
                    placeholder="Mfano: 7XXXXXXXX"
                    value="{{ old('phone_local') }}"
                    required
                  >
                </div>
                @error('country_code') <div class="authNovaErr">{{ $message }}</div> @enderror
                @error('phone_local') <div class="authNovaErr">{{ $message }}</div> @enderror
                @error('phone') <div class="authNovaErr">{{ $message }}</div> @enderror
              </div>

              <button class="authNovaBtn authNovaBtn--primary" type="submit">Tuma OTP ya simu</button>
            </form>

            <form method="POST" action="{{ route('password.phone.otp.verify') }}" novalidate class="authNovaForm" style="margin-bottom:12px;">
              @csrf

              <div>
                <label class="authNovaLabel" for="phoneOtp">OTP ya simu</label>
                <input
                  class="authNovaInput"
                  id="phoneOtp"
                  name="phone_otp"
                  inputmode="numeric"
                  maxlength="6"
                  pattern="[0-9]*"
                  placeholder="Weka OTP ya tarakimu 6"
                  value="{{ old('phone_otp') }}"
                  required
                >
                @if(!empty($phoneDestinationMasked))
                  <div class="muted small" style="margin-top:6px;">OTP ilitumwa kwenye {{ $phoneDestinationMasked }}.</div>
                @endif
                @error('phone_otp') <div class="authNovaErr">{{ $message }}</div> @enderror
              </div>

              <button class="authNovaBtn authNovaBtn--ghost" type="submit">Thibitisha OTP ya simu</button>
            </form>
          @endif
        @endif

        <form method="POST" action="{{ route('password.store') }}" novalidate class="authNovaForm">
          @csrf

          <div>
            <label class="authNovaLabel" for="newPassword">Nenosiri</label>
            <input class="authNovaInput" id="newPassword" name="password" type="password" autocomplete="new-password" placeholder="Weka nenosiri" required>
            @error('password') <div class="authNovaErr">{{ $message }}</div> @enderror
          </div>

          <div>
            <label class="authNovaLabel" for="newPasswordConfirm">Rudia Nenosiri</label>
            <input class="authNovaInput" id="newPasswordConfirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Rudia nenosiri" required>
          </div>

          <button class="authNovaBtn authNovaBtn--primary" type="submit" {{ (!empty($phoneOtpRequired) && empty($phoneVerified)) ? 'disabled' : '' }}>
            Hifadhi nenosiri
          </button>

          @if(!empty($phoneOtpRequired) && empty($phoneVerified))
            <div class="authNovaErr">Thibitisha namba ya simu kwa OTP kwanza.</div>
          @endif

          <p class="authNovaLinks"><a href="{{ route('login') }}">Rudi kuingia</a></p>
        </form>
      </div>
    </div>
  </section>
</div>
@endsection
