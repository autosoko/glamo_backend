<!DOCTYPE html>
<html lang="sw">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="color-scheme" content="light">
  <meta name="supported-color-schemes" content="light">
  <title>@yield('title', 'Glamo - Urembo Ukiwa Nyumbani')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16.png') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32.png') }}">
  <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/favicon-48.png') }}">
  <link rel="icon" type="image/png" sizes="64x64" href="{{ asset('images/favicon-64.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/address.png') }}">
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Glamo">
  <meta name="theme-color" content="#5A0E24">

  <link rel="stylesheet" href="{{ asset('css/glamo-classic.css') }}?v={{ time() }}">

  <style>
    .siteBrand--plain{
      gap:0;
    }

    .siteBrand--plain .siteBrand__logo{
      height:30px;
      width:auto;
      display:block;
    }

    .siteHeader__actions{
      gap:8px;
      flex-wrap:nowrap;
      justify-content:flex-end;
    }

    @media (max-width: 900px){
      .siteHeader .container{
        padding-left:22px;
        padding-right:22px;
      }

      .siteHeader__link{
        display:none;
      }
    }

    @media (max-width: 560px){
      .siteHeader__actions{
        gap:6px;
      }

      .siteHeader__actions .btn--sm{
        padding:8px 10px;
        font-size:12px;
      }

      .siteBrand--plain .siteBrand__logo{
        height:28px;
      }
    }

    .simpleMenuCols{
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:16px;
    }

    @media (max-width: 900px){
      .simpleMenuCols{
        grid-template-columns:1fr 1fr;
      }
    }

    @media (max-width: 560px){
      .simpleMenuCols{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>

  @hasSection('custom_header')
    @yield('custom_header')
  @else
    <header class="siteHeader">
      <div class="container siteHeader__inner">
        <a class="siteBrand siteBrand--plain" href="{{ route('landing') }}">
          <img class="siteBrand__logo" src="{{ asset('images/logo.png') }}" alt="Glamo">
        </a>

        <div class="siteHeader__actions">
          <a class="siteHeader__link" href="{{ route('support') }}">Msaada</a>

          @guest
            <a class="btn btn--ghost btn--sm" href="{{ route('login') }}">Ingia</a>
            <a class="btn btn--primary btn--sm" href="{{ route('register') }}">Jisajili</a>
          @else
            @if((string) (auth()->user()->role ?? '') === 'provider')
              <a class="btn btn--ghost btn--sm" href="{{ route('provider.dashboard') }}">Dashibodi</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button class="btn btn--primary btn--sm" type="submit">Ondoka</button>
            </form>
          @endguest

          <button class="menuBtn" type="button" id="megaMenuOpen" aria-controls="megaMenu" aria-expanded="false">
            <span class="sr-only">Menyu</span>
            <span class="menuBtn__icon" aria-hidden="true"></span>
          </button>
        </div>
      </div>
    </header>

    <div class="megaMenu" id="megaMenu" aria-hidden="true">
      <div class="megaMenu__backdrop" data-close="1"></div>

      <div class="megaMenu__panel" role="dialog" aria-modal="true" aria-labelledby="megaMenuTitle">
        <div class="megaMenu__head">
          <div class="megaMenu__title" id="megaMenuTitle">Menyu</div>

          <button class="megaMenu__close" type="button" data-close="1" aria-label="Funga">
            <span aria-hidden="true">×</span>
          </button>
        </div>

        <div class="megaMenu__grid">
          <div class="megaMenu__main">
            <div class="simpleMenuCols">
              <div>
                <div class="megaCol__title">Kurasa kuu</div>
                <a class="megaLink" href="{{ route('landing') }}">Nyumbani</a>
                <a class="megaLink" href="{{ route('services.index') }}">Huduma zote</a>
                <a class="megaLink" href="{{ route('landing') }}#jinsi">Jinsi inavyofanya kazi</a>
              </div>

              <div>
                <div class="megaCol__title">Akaunti</div>
                <a class="megaLink" href="{{ route('login') }}">Ingia</a>
                <a class="megaLink" href="{{ route('register', ['as' => 'client']) }}">Jisajili kama mteja</a>
                <a class="megaLink" href="{{ route('register', ['as' => 'provider']) }}">Jisajili kama mtoa huduma</a>
              </div>

              <div>
                <div class="megaCol__title">Msaada</div>
                <a class="megaLink" href="{{ route('support') }}">Msaada</a>
                <a class="megaLink" href="{{ route('landing') }}#huduma">Huduma 4 bora</a>
                <a class="megaLink" href="{{ route('landing') }}#jinsi">Hatua za kutumia</a>
              </div>
            </div>
          </div>

          <aside class="megaMenu__side">
            <a class="megaCard" href="{{ route('register', ['as' => 'provider']) }}">
              <div>
                <div class="megaCard__title">Jiunge kama mtoa huduma</div>
                <div class="megaCard__desc">Pata wateja wa karibu kila siku</div>
              </div>
              <span class="megaCard__chev">›</span>
            </a>

            <a class="megaCard" href="{{ route('services.index') }}">
              <div>
                <div class="megaCard__title">Angalia huduma zote</div>
                <div class="megaCard__desc">Chagua huduma inayokufaa</div>
              </div>
              <span class="megaCard__chev">›</span>
            </a>

            <a class="megaCard" href="{{ route('support') }}">
              <div>
                <div class="megaCard__title">Maswali ya kawaida</div>
                <div class="megaCard__desc">Pata msaada wa haraka</div>
              </div>
              <span class="megaCard__chev">›</span>
            </a>
          </aside>
        </div>
      </div>
    </div>
  @endif

  <main>
    @if(session('error') || session('success'))
      <div class="container" style="margin-top:12px;">
        @if(session('error'))
          <div class="flash flash--error">{{ session('error') }}</div>
        @endif
        @if(session('success'))
          <div class="flash flash--success">{{ session('success') }}</div>
        @endif
      </div>
    @endif

    @yield('content')
  </main>

  @hasSection('page_footer')
    @yield('page_footer')
  @else
        <footer class="footer">
      <div class="container footer__top">
        <div class="footer__brandBlock">
          <img class="footer__logo" src="{{ asset('images/logo.png') }}" alt="Glamo" loading="lazy">
          <div class="footer__brandName">Glamo</div>

          @php
            $glamoClientPlayUrl = 'https://play.google.com/store/apps/details?id=com.beautful.link&pcampaignid=web_share';
            $glamoClientAppStoreUrl = 'https://play.google.com/store/apps/details?id=com.beautful.link&pcampaignid=web_share';
            $glamoProPlayUrl = 'https://play.google.com/store/apps/details?id=com.beautful.link&pcampaignid=web_share';
            $glamoProAppStoreUrl = 'https://play.google.com/store/apps/details?id=com.beautful.link&pcampaignid=web_share';
          @endphp

          <div class="footer__apps">
            <div class="footerAppGroup">
              <div class="footerAppLabel">Glamo (Mteja)</div>
              <div class="footerStoreBtns">
                <a class="footerStoreBtn" href="{{ $glamoClientPlayUrl }}" target="_blank" rel="noopener">
                  <img src="{{ asset('images/playstore.png') }}" alt="Store">
                </a>
                <a class="footerStoreBtn" href="{{ $glamoClientAppStoreUrl }}" target="_blank" rel="noopener">
                  <img src="{{ asset('images/appstore.png') }}" alt="Store">
                </a>
              </div>
            </div>

            <div class="footerAppGroup">
              <div class="footerAppLabel">Glamo Pro (Mtoa huduma)</div>
              <div class="footerStoreBtns">
                <a class="footerStoreBtn" href="{{ $glamoProPlayUrl }}" target="_blank" rel="noopener">
                  <img src="{{ asset('images/playstore.png') }}" alt="Store">
                </a>
                <a class="footerStoreBtn" href="{{ $glamoProAppStoreUrl }}" target="_blank" rel="noopener">
                  <img src="{{ asset('images/appstore.png') }}" alt="Store">
                </a>
              </div>
            </div>
          </div>
        </div>


        <div class="footer__col">
          <div class="footer__title">Huduma</div>
          <a class="footerLink" href="{{ route('services.index') }}">Huduma zote</a>
          <a class="footerLink" href="{{ route('safari') }}">Safari</a>
          <a class="footerLink" href="{{ route('salons') }}">Salon zetu</a>
          <a class="footerLink" href="{{ url('/malipo') }}">Malipo</a>
          <a class="footerLink" href="{{ url('/miji') }}">Miji</a>
        </div>

        <div class="footer__col">
          <div class="footer__title">Jiunge Nasi</div>
          @auth
            <a class="footerLink" href="{{ route('provider.onboarding') }}">Jisajili kama mtoa huduma</a>
          @else
            <a class="footerLink" href="{{ route('register', ['as' => 'provider']) }}">Jisajili kama mtoa huduma</a>
          @endauth
          <a class="footerLink" href="{{ route('ambassador.create') }}">Jisajili kama ambasador wa glamo</a>
        </div>

        <div class="footer__col">
          <div class="footer__title">Kampuni</div>
          <a class="footerLink" href="{{ url('/about-us') }}">Kuhusu sisi</a>
          <a class="footerLink" href="{{ route('careers') }}">Kazi</a>
        </div>
      </div>

      <div class="container footer__bottom">
        <div class="footer__social">
          <a class="socialBtn" href="https://www.instagram.com/glamo_app/" aria-label="Instagram">
            <svg class="socialBtn__icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
              <rect x="3.5" y="3.5" width="13" height="13" rx="4" fill="none" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="10" cy="10" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/>
              <circle cx="14.2" cy="5.8" r="1" fill="currentColor"/>
            </svg>
          </a>
          <a class="socialBtn" href="https://www.tiktok.com/@glamo_app?is_from_webapp=1&sender_device=pc" aria-label="TikTok">
            <svg class="socialBtn__icon" viewBox="0 0 20 20" aria-hidden="true" focusable="false">
              <path fill="currentColor" d="M13.9 2c.29 1.67 1.57 2.95 3.24 3.24v2.21a5.58 5.58 0 0 1-3.24-1.04v6.05a4.46 4.46 0 1 1-3.88-4.43v2.22a2.24 2.24 0 1 0 1.66 2.16V2h2.22z"/>
            </svg>
          </a>
        </div>
      </div>

      <div class="container footer__legal">
        <span class="muted small">© {{ date('Y') }} Glamo. Haki zote zimehifadhiwa.</span>
        <div class="footer__legalLinks">
          <a class="footerLegalLink" href="{{ url('/terms') }}">Masharti</a>
          <a class="footerLegalLink" href="{{ url('/privacy') }}">Faragha</a>
          <a class="footerLegalLink" href="{{ url('/cookies') }}">Cookies</a>
          <a class="footerLegalLink" href="{{ url('/security') }}">Usalama</a>
        </div>
      </div>
    </footer>
  @endif

    @php($geoPromptRadius = (int) config('glamo_pricing.radius_km', 10))
  <div class="geoPrompt" id="geoPromptModal" aria-hidden="true">
    <div class="geoPrompt__card" role="dialog" aria-modal="true" aria-labelledby="geoPromptTitle">
      <h3 class="geoPrompt__title" id="geoPromptTitle">Ruhusu Location Access</h3>
      <p class="geoPrompt__text">Tunatumia location yako kukuonyesha watoa huduma wa karibu ndani ya {{ $geoPromptRadius }}km, na kusave location yako kiotomatiki.</p>
      <div class="geoPrompt__status" id="geoPromptStatus"></div>
      <div class="geoPrompt__actions">
        <button class="btn btn--ghost btn--sm" type="button" id="geoPromptClose">Funga</button>
        <button class="btn btn--primary btn--sm" type="button" id="geoPromptAllow">Ruhusu location</button>
      </div>
    </div>
  </div>

  <div class="installPrompt" id="installPromptModal" aria-hidden="true">
    <div class="installPrompt__card" role="dialog" aria-modal="true" aria-labelledby="installPromptTitle">
      <h3 class="installPrompt__title" id="installPromptTitle">Install Glamo App</h3>
      <p class="installPrompt__text" id="installPromptText">Install Glamo kwenye simu yako ili uitumie haraka kama app.</p>
      <div class="installPrompt__actions">
        <button class="btn btn--ghost btn--sm" type="button" id="installPromptLater">Baadaye</button>
        <button class="btn btn--primary btn--sm" type="button" id="installPromptInstall">Install App</button>
      </div>
    </div>
  </div>

  <script>
    (function () {
      const modal = document.getElementById('geoPromptModal');
      const btnAllow = document.getElementById('geoPromptAllow');
      const btnClose = document.getElementById('geoPromptClose');
      const status = document.getElementById('geoPromptStatus');
      const csrf = "{{ csrf_token() }}";
      const saveUrl = "{{ route('location.set') }}";
      const sessionApprovedKey = 'glamo.geo.location.allowed';

      if (!modal || !btnAllow || !btnClose || !status) return;

      let requesting = false;

      function isLocationApprovedThisSession() {
        try {
          return window.sessionStorage.getItem(sessionApprovedKey) === '1';
        } catch (e) {
          return false;
        }
      }

      function markLocationApprovedThisSession() {
        try {
          window.sessionStorage.setItem(sessionApprovedKey, '1');
        } catch (e) {
          // ignore storage errors
        }
      }

      function setStatus(message, show = true) {
        status.textContent = message || '';
        status.style.display = show ? 'block' : 'none';
      }

      function openPrompt() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }

      function closePrompt() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }

      async function saveLocation(lat, lng) {
        const res = await fetch(saveUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify({ lat, lng }),
        });

        if (!res.ok) throw new Error('Imeshindikana kuhifadhi location yako.');
        return await res.json();
      }

      async function requestLocation() {
        if (requesting) return;
        setStatus('', false);

        if (!navigator.geolocation) {
          setStatus('Browser yako haiungi mkono location access.');
          return;
        }

        requesting = true;
        btnAllow.disabled = true;
        const oldText = btnAllow.textContent;
        btnAllow.textContent = 'Inaomba ruhusa...';

        navigator.geolocation.getCurrentPosition(async (pos) => {
          try {
            await saveLocation(pos.coords.latitude, pos.coords.longitude);
            markLocationApprovedThisSession();
            setStatus('Location yako imehifadhiwa kikamilifu.');
            window.dispatchEvent(new CustomEvent('glamo:location-updated', {
              detail: {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
              },
            }));

            setTimeout(() => {
              closePrompt();
            }, 900);
          } catch (error) {
            setStatus(error && error.message ? error.message : 'Kuna hitilafu wakati wa kuhifadhi location.');
          } finally {
            requesting = false;
            btnAllow.disabled = false;
            btnAllow.textContent = oldText;
          }
        }, () => {
          requesting = false;
          btnAllow.disabled = false;
          btnAllow.textContent = oldText;
          setStatus('Imeshindikana kupata location. Ruhusu location kwenye browser settings kisha jaribu tena.');
        }, {
          enableHighAccuracy: true,
          timeout: 12000,
          maximumAge: 0,
        });
      }

      btnAllow.addEventListener('click', requestLocation);
      btnClose.addEventListener('click', closePrompt);

      modal.addEventListener('click', (event) => {
        if (event.target === modal) closePrompt();
      });

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
          closePrompt();
        }
      });

      window.GlamoGeoPrompt = {
        openPrompt,
        requestLocation,
      };

      if (!isLocationApprovedThisSession()) {
        openPrompt();
        requestLocation();
      }
    })();
  </script>
  
  <script>
    (function () {
      const modal = document.getElementById('installPromptModal');
      const btnInstall = document.getElementById('installPromptInstall');
      const btnLater = document.getElementById('installPromptLater');
      const textEl = document.getElementById('installPromptText');

      if (!modal || !btnLater || !textEl) return;

      const NEXT_PROMPT_AT_KEY = 'glamo.install.prompt.next_at';
      const INSTALLED_KEY = 'glamo.install.installed';
      const GEO_APPROVED_KEY = 'glamo.geo.location.allowed';
      const PROMPT_INTERVAL_MS = 3 * 60 * 60 * 1000;

      let deferredPrompt = null;
      let locationAllowed = false;

      function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
      }

      function isIosManualInstallMode() {
        const ua = String(window.navigator.userAgent || '').toLowerCase();
        const isIos = /iphone|ipad|ipod/.test(ua);
        const isSafari = /safari/.test(ua) && !/crios|fxios|edgios|android/.test(ua);
        return isIos && isSafari && !isStandalone();
      }

      function isInstalled() {
        if (isStandalone()) return true;

        try {
          return window.localStorage.getItem(INSTALLED_KEY) === '1';
        } catch (e) {
          return false;
        }
      }

      function hasLocationAccessThisSession() {
        try {
          return window.sessionStorage.getItem(GEO_APPROVED_KEY) === '1';
        } catch (e) {
          return false;
        }
      }

      function canShowPromptNow() {
        if (isInstalled()) return false;

        try {
          const nextAt = Number(window.localStorage.getItem(NEXT_PROMPT_AT_KEY) || 0);
          return Date.now() >= nextAt;
        } catch (e) {
          return true;
        }
      }

      function postponePrompt() {
        try {
          window.localStorage.setItem(NEXT_PROMPT_AT_KEY, String(Date.now() + PROMPT_INTERVAL_MS));
        } catch (e) {
          // ignore storage errors
        }
      }

      function markInstalled() {
        try {
          window.localStorage.setItem(INSTALLED_KEY, '1');
          window.localStorage.removeItem(NEXT_PROMPT_AT_KEY);
        } catch (e) {
          // ignore storage errors
        }
      }

      function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
      }

      function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }

      function installMode() {
        if (deferredPrompt) return 'native';
        if (isIosManualInstallMode()) return 'ios';
        return 'none';
      }

      function prepareContent() {
        const mode = installMode();

        if (mode === 'native') {
          textEl.textContent = 'Install Glamo kwenye simu yako ili uitumie haraka kama app.';
          if (btnInstall) btnInstall.style.display = '';
          return true;
        }

        if (mode === 'ios') {
          textEl.textContent = 'Kwa iPhone, bonyeza Share kisha Add to Home Screen ili ku-install Glamo.';
          if (btnInstall) btnInstall.style.display = 'none';
          return true;
        }

        if (btnInstall) btnInstall.style.display = 'none';
        return false;
      }

      function maybeShowPrompt() {
        if (!(locationAllowed || hasLocationAccessThisSession())) return;
        if (!canShowPromptNow()) return;
        if (!prepareContent()) return;

        openModal();
      }

      btnLater.addEventListener('click', () => {
        postponePrompt();
        closeModal();
      });

      modal.addEventListener('click', (event) => {
        if (event.target === modal) {
          postponePrompt();
          closeModal();
        }
      });

      btnInstall?.addEventListener('click', async () => {
        if (!deferredPrompt) {
          postponePrompt();
          closeModal();
          return;
        }

        deferredPrompt.prompt();
        const choice = await deferredPrompt.userChoice.catch(() => null);
        deferredPrompt = null;

        if (!choice || choice.outcome !== 'accepted') {
          postponePrompt();
        }

        closeModal();
      });

      window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;

        if (hasLocationAccessThisSession()) {
          setTimeout(maybeShowPrompt, 800);
        }
      });

      window.addEventListener('appinstalled', () => {
        markInstalled();
        closeModal();
      });

      window.addEventListener('glamo:location-updated', () => {
        locationAllowed = true;
        setTimeout(maybeShowPrompt, 1200);
      });

      if (hasLocationAccessThisSession()) {
        locationAllowed = true;
        setTimeout(maybeShowPrompt, 1500);
      }

      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js').catch(() => null);
        });
      }
    })();
  </script>
  <script>
    (function () {
      const openBtn = document.getElementById('megaMenuOpen');
      const menu = document.getElementById('megaMenu');
      if (!openBtn || !menu) return;

      const closeEls = menu.querySelectorAll('[data-close="1"]');

      function openMenu() {
        menu.classList.add('is-open');
        menu.setAttribute('aria-hidden', 'false');
        openBtn.setAttribute('aria-expanded', 'true');
        openBtn.classList.add('is-open');
        document.body.classList.add('no-scroll');
      }

      function closeMenu() {
        menu.classList.remove('is-open');
        menu.setAttribute('aria-hidden', 'true');
        openBtn.setAttribute('aria-expanded', 'false');
        openBtn.classList.remove('is-open');
        document.body.classList.remove('no-scroll');
      }

      openBtn.addEventListener('click', () => {
        const isOpen = menu.classList.contains('is-open');
        if (isOpen) closeMenu();
        else openMenu();
      });

      closeEls.forEach((el) => el.addEventListener('click', closeMenu));

      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && menu.classList.contains('is-open')) {
          closeMenu();
        }
      });

      menu.querySelectorAll('a').forEach((a) => a.addEventListener('click', closeMenu));
    })();
  </script>

</body>
</html>














