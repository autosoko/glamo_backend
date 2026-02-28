@extends('public.layout')

@section('title', 'Thibitisha OTP - Glamo')

@section('content')
@php
  $oldOtp = preg_replace('/\D+/', '', (string) old('otp', ''));
  $oldOtp = substr($oldOtp, 0, 6);
  $digits = str_split(str_pad($oldOtp, 6, ' '));
@endphp
<div class="authNova">
  @include('auth.partials.theme')

  <section>
    <div class="container authNova__wrap">
      <aside class="authNova__visual">
        <span class="authNova__badge">Uthibitisho wa OTP</span>
        <h1 class="authNova__heroTitle">Thibitisha namba ya usalama.</h1>
        <p class="authNova__heroText">
          Tumeituma OTP kwenye <b>{{ $destination }}</b>. Weka tarakimu 6 kuthibitisha hatua hii.
        </p>

        <div class="authNova__checks">
          <div class="authNova__check">OTP ina muda wa dakika 5</div>
          <div class="authNova__check">Ukikosea code, utatuma OTP mpya</div>
          <div class="authNova__check">Baada ya uthibitisho utaweka nenosiri</div>
        </div>

        <div class="authNovaInlineHint">
          {{ ($intent ?? '') === 'reset' ? 'Hii ni hatua ya kurejesha nenosiri.' : 'Hii ni hatua ya usajili wa akaunti mpya.' }}
        </div>
      </aside>

      <div class="authNova__card">
        <h2 class="authNova__title">Thibitisha OTP</h2>
        <p class="authNova__subtitle">Ingiza code ya tarakimu 6 tuliyotuma kwenye {{ $destination }}.</p>

        @if($errors->any())
          <div class="authNova__errors">
            <ul>
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('otp.verify.submit') }}" class="authNovaForm" id="otpForm" novalidate>
          @csrf
          <input type="hidden" name="otp" id="otpCombined" value="{{ $oldOtp }}">

          <div>
            <label class="authNovaLabel">Weka OTP</label>
            <div class="authOtp" id="otpDigits" data-autosubmit="1">
              @for($i = 0; $i < 6; $i++)
                <input
                  class="authOtp__digit"
                  type="text"
                  inputmode="numeric"
                  pattern="[0-9]*"
                  maxlength="1"
                  data-otp-digit
                  value="{{ trim($digits[$i] ?? '') }}"
                  aria-label="Tarakimu ya OTP {{ $i + 1 }}"
                >
              @endfor
            </div>
            <div class="authOtp__hint">Ukimaliza tarakimu ya mwisho, form itasubmit yenyewe.</div>
            @error('otp') <div class="authNovaErr">{{ $message }}</div> @enderror
            <div class="authNovaErr" id="otpClientError" style="display:none;"></div>
          </div>

          <button class="authNovaBtn authNovaBtn--primary" type="submit" id="otpSubmitBtn">Thibitisha OTP</button>

          <p class="authNovaLinks">
            OTP inaisha ndani ya dakika 5. Ukichelewa,
            <a href="{{ ($intent ?? '') === 'reset' ? route('password.request') : route('register') }}">tuma tena</a>.
          </p>
        </form>
      </div>
    </div>
  </section>
</div>

<script>
  (() => {
    const form = document.getElementById('otpForm');
    const hiddenOtp = document.getElementById('otpCombined');
    const digits = Array.from(document.querySelectorAll('[data-otp-digit]'));
    const errorEl = document.getElementById('otpClientError');
    const submitBtn = document.getElementById('otpSubmitBtn');

    if (!form || !hiddenOtp || !digits.length) {
      return;
    }

    function normalize(value) {
      return String(value || '').replace(/\D+/g, '').slice(0, 1);
    }

    function syncHidden() {
      hiddenOtp.value = digits.map((input) => input.value || '').join('');
      return hiddenOtp.value;
    }

    function clearClientError() {
      if (!errorEl) return;
      errorEl.style.display = 'none';
      errorEl.textContent = '';
    }

    function showClientError(message) {
      if (!errorEl) return;
      errorEl.textContent = message;
      errorEl.style.display = 'block';
    }

    function focusAt(index) {
      if (index < 0 || index >= digits.length) return;
      digits[index].focus();
      digits[index].select();
    }

    function maybeAutoSubmit() {
      const code = syncHidden();
      if (code.length !== 6) return;

      clearClientError();
      if (submitBtn) {
        submitBtn.disabled = true;
      }
      form.requestSubmit();
    }

    digits.forEach((input, index) => {
      input.addEventListener('input', () => {
        input.value = normalize(input.value);
        clearClientError();
        syncHidden();

        if (input.value && index < digits.length - 1) {
          focusAt(index + 1);
          return;
        }

        if (index === digits.length - 1 && input.value) {
          maybeAutoSubmit();
        }
      });

      input.addEventListener('keydown', (event) => {
        if (event.key === 'Backspace' && !input.value && index > 0) {
          focusAt(index - 1);
          return;
        }

        if (event.key === 'ArrowLeft' && index > 0) {
          event.preventDefault();
          focusAt(index - 1);
          return;
        }

        if (event.key === 'ArrowRight' && index < digits.length - 1) {
          event.preventDefault();
          focusAt(index + 1);
        }
      });

      input.addEventListener('paste', (event) => {
        event.preventDefault();
        const text = (event.clipboardData || window.clipboardData).getData('text') || '';
        const nums = text.replace(/\D+/g, '').slice(0, 6).split('');
        if (!nums.length) return;

        nums.forEach((num, idx) => {
          if (digits[idx]) {
            digits[idx].value = num;
          }
        });

        syncHidden();
        if (nums.length >= 6) {
          maybeAutoSubmit();
        } else {
          focusAt(nums.length);
        }
      });
    });

    form.addEventListener('submit', (event) => {
      const code = syncHidden();
      if (/^\d{6}$/.test(code)) {
        clearClientError();
        return;
      }

      event.preventDefault();
      showClientError('Weka OTP sahihi ya tarakimu 6.');
      focusAt(0);
    });

    const initial = syncHidden();
    if (initial.length === 6) {
      focusAt(5);
    } else {
      const firstEmpty = digits.findIndex((input) => !input.value);
      focusAt(firstEmpty >= 0 ? firstEmpty : 0);
    }
  })();
</script>
@endsection
