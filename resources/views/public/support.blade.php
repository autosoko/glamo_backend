@extends('public.layout')

@section('title', 'Support - Glamo')

@section('content')
@php
  $topics = [
    [
      'id' => 'clients',
      'title' => 'Kwa Wateja',
      'desc' => 'Booking, malipo, location, na maswali ya kawaida.',
      'icon' => '🙋🏽‍♀️',
    ],
    [
      'id' => 'providers',
      'title' => 'Kwa Watoa Huduma',
      'desc' => 'Kuonekana kwenye app, huduma, na ratiba.',
      'icon' => '💇🏽‍♀️',
    ],
    [
      'id' => 'booking',
      'title' => 'Booking',
      'desc' => 'Jinsi ya ku-book, kubadilisha, au ku-cancel.',
      'icon' => '📅',
    ],
    [
      'id' => 'payments',
      'title' => 'Malipo',
      'desc' => 'Bei, makato, na malipo salama.',
      'icon' => '💳',
    ],
    [
      'id' => 'account',
      'title' => 'Account',
      'desc' => 'OTP, kuingia, na usalama wa akaunti.',
      'icon' => '🔐',
    ],
    [
      'id' => 'safety',
      'title' => 'Usalama',
      'desc' => 'Miongozo na taarifa muhimu.',
      'icon' => '🛡️',
    ],
  ];

  $guides = [
    [
      'topic' => 'clients',
      'date' => date('M d, Y'),
      'title' => 'Jinsi ya ku-book huduma haraka',
      'excerpt' => 'Chagua huduma, weka location (hiari), kisha book ndani ya dakika chache.',
      'image' => asset('images/slide 4.jpg'),
    ],
    [
      'topic' => 'payments',
      'date' => date('M d, Y'),
      'title' => 'Bei na malipo vinafanyaje kazi?',
      'excerpt' => 'Elewa base price, promos, na jinsi ya kulipa salama.',
      'image' => asset('images/slide 3.jpg'),
    ],
    [
      'topic' => 'account',
      'date' => date('M d, Y'),
      'title' => 'OTP haijafika — nifanye nini?',
      'excerpt' => 'Hatua za haraka kuhakikisha unapata OTP kwa wakati.',
      'image' => asset('images/slide 2.jpg'),
    ],
    [
      'topic' => 'booking',
      'date' => date('M d, Y'),
      'title' => 'Location ni ya hiari, lakini inasaidia',
      'excerpt' => 'Ukiwasha location, utaona watoa huduma walio karibu zaidi.',
      'image' => asset('images/slide 1.jpg'),
    ],
  ];

  $faqs = [
    'clients' => [
      [
        'q' => 'Ninawezaje ku-book huduma?',
        'a' => 'Nenda kwenye “Tafuta huduma”, chagua huduma unayotaka, kisha ingia (OTP) na uendelee ku-book.',
      ],
      [
        'q' => 'Je, lazima niwashe location?',
        'a' => 'Hapana. Lakini ukiwasha, utaona watoa huduma walio karibu zaidi na itasaidia upate haraka.',
      ],
      [
        'q' => 'Ninawezaje kubadilisha booking?',
        'a' => 'Kwa sasa, ukihitaji kubadilisha booking tafadhali wasiliana na support. (Feature hii inakuja).',
      ],
    ],
    'providers' => [
      [
        'q' => 'Ninawezaje kuonekana kwa wateja?',
        'a' => 'Hakikisha una approval, uko online, na una location iliyowashwa. Pia weka huduma zako active.',
      ],
      [
        'q' => 'Ninawezaje kuongeza huduma ninazotoa?',
        'a' => 'Baada ya kuingia, utaweza kuchagua huduma unazotoa na kuweka bei (feature inaboreshwa).',
      ],
      [
        'q' => 'Kwa nini siioni booking?',
        'a' => 'Angalia status yako (online), location, na kama una debt (ikiwa ipo) inaweza kukuzuia kuonekana.',
      ],
    ],
    'booking' => [
      [
        'q' => 'Booking inachukua muda gani kuthibitishwa?',
        'a' => 'Inategemea upatikanaji wa watoa huduma. Ukiwasha location, nafasi za karibu huonekana haraka.',
      ],
      [
        'q' => 'Naweza ku-cancel booking?',
        'a' => 'Ndiyo (taratibu zinategemea status). Kwa sasa, support inaweza kukusaidia haraka.',
      ],
    ],
    'payments' => [
      [
        'q' => 'Bei inamaanisha nini (base price)?',
        'a' => 'Base price ni bei ya kuanzia. Bei halisi inaweza kutofautiana kulingana na mtoa huduma na mahitaji.',
      ],
      [
        'q' => 'Ninalipaje?',
        'a' => 'Njia za malipo zinaboreshwa. Kwa sasa, utapata maelekezo kwenye hatua za booking.',
      ],
    ],
    'account' => [
      [
        'q' => 'Sina OTP / OTP imechelewa',
        'a' => 'Hakikisha namba yako iko sahihi, simu ina network, kisha jaribu tena. Ikiendelea, wasiliana na support.',
      ],
      [
        'q' => 'Ninawezaje kubadilisha namba ya simu?',
        'a' => 'Kwa sasa, support itakusaidia kubadilisha taarifa zako. (Self-service inakuja).',
      ],
    ],
    'safety' => [
      [
        'q' => 'Nifanye nini nikipata tatizo na mtoa huduma?',
        'a' => 'Tafadhali wasiliana na support mara moja. Tutaandika report na kuchukua hatua stahiki.',
      ],
      [
        'q' => 'Glamo inahifadhi vipi taarifa zangu?',
        'a' => 'Tunahifadhi taarifa kwa usalama na tunazingatia kanuni za faragha. (Policy page inakuja).',
      ],
    ],
  ];

  $topicById = collect($topics)->keyBy('id');
@endphp

{{-- FEATURED GUIDES --}}
<section class="section supportFeatured">
  <div class="container">
    <a class="btn btn--ghost btn--sm supportViewAll" href="#faq">View all ↗</a>

    <div class="supportFeatured__grid">
      @php $main = $guides[0] ?? null; @endphp
      @if($main)
        <a class="supportFeatureCard" href="#faq-{{ $main['topic'] }}">
          <div class="supportFeatureCard__media" aria-hidden="true">
            <img src="{{ $main['image'] }}" alt="" loading="lazy">
          </div>
          <div class="supportFeatureCard__body">
            <div class="supportFeatureCard__date muted small">{{ $main['date'] }}</div>
            <div class="supportFeatureCard__title">{{ $main['title'] }}</div>
            <p class="supportFeatureCard__excerpt muted">{{ $main['excerpt'] }}</p>
          </div>
        </a>
      @endif

      <div class="supportFeatured__side">
        @foreach(array_slice($guides, 1, 3) as $g)
          <a class="supportMiniCard" href="#faq-{{ $g['topic'] }}">
            <div class="supportMiniCard__thumb" aria-hidden="true">
              <img src="{{ $g['image'] }}" alt="" loading="lazy">
            </div>
            <div class="supportMiniCard__body">
              <div class="supportMiniCard__title">{{ $g['title'] }}</div>
              <div class="muted small">{{ $g['date'] }}</div>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </div>
</section>

{{-- SUPPORT HERO --}}
<section class="supportHero" aria-label="Support search">
  <div class="container supportHero__inner">
    <h1 class="supportHero__title">Tunawezaje kukusaidia?</h1>

    <div class="supportSearch" role="search">
      <span class="supportSearch__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path fill="currentColor" d="M10 2a8 8 0 1 0 4.9 14.3l4.4 4.4a1 1 0 0 0 1.4-1.4l-4.4-4.4A8 8 0 0 0 10 2Zm0 2a6 6 0 1 1 0 12a6 6 0 0 1 0-12Z"/>
        </svg>
      </span>
      <input id="supportSearchInput" class="supportSearch__input" type="search" placeholder="Andika swali lako" autocomplete="off">
    </div>

    <div class="supportTopics" id="topics" aria-label="Support topics">
      @foreach($topics as $t)
        <a class="supportTopicCard" href="#faq-{{ $t['id'] }}" data-topic="{{ $t['id'] }}">
          <div class="supportTopicCard__title">{{ $t['title'] }}</div>
          <div class="supportTopicCard__meta muted small">
            <span aria-hidden="true">{{ $t['icon'] }}</span> {{ $t['desc'] }}
          </div>
        </a>
      @endforeach
    </div>

    <div class="supportResults muted small" id="supportResults" style="display:none;"></div>
  </div>
</section>

{{-- FAQ --}}
<section id="faq" class="section">
  <div class="container">
    <div class="section__head section__head--center">
      <h2 class="section__title">Maswali</h2>
      <p class="section__subtitle">Tumia search au chagua category.</p>
    </div>

    <div class="supportFaq">
      @foreach($faqs as $topicId => $items)
        @php
          $topic = $topicById->get($topicId);
          $topicTitle = data_get($topic, 'title') ?? ucfirst($topicId);
        @endphp

        <div class="supportFaqGroup" id="faq-{{ $topicId }}" data-topic="{{ $topicId }}">
          <div class="supportFaqGroup__head">
            <div class="supportFaqGroup__title">{{ $topicTitle }}</div>
            <div class="muted small">{{ count($items) }} maswali</div>
          </div>

          <div class="supportFaqGroup__list">
            @foreach($items as $it)
              <details class="supportFaqItem" data-topic="{{ $topicId }}">
                <summary class="supportFaqItem__q">{{ $it['q'] }}</summary>
                <div class="supportFaqItem__a muted">{{ $it['a'] }}</div>
              </details>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>

{{-- CONTACT --}}
<section class="section section--soft">
  <div class="container">
    <div class="supportContact">
      <div>
        <div class="supportContact__title">Bado unahitaji msaada?</div>
        <div class="muted">Tupigie au tutumie ujumbe — tupo tayari kukusaidia.</div>
      </div>
      <div class="supportContact__actions">
        <a class="btn btn--ghost" href="mailto:info@glamo.tz">Email</a>
        <a class="btn btn--primary" href="{{ route('register') }}">Anza sasa</a>
      </div>
    </div>
  </div>
</section>

<script>
(function () {
  const input = document.getElementById('supportSearchInput');
  const results = document.getElementById('supportResults');
  const items = Array.from(document.querySelectorAll('.supportFaqItem'));
  const groups = Array.from(document.querySelectorAll('.supportFaqGroup'));
  const topicCards = Array.from(document.querySelectorAll('.supportTopicCard'));

  function apply() {
    const q = (input?.value || '').trim().toLowerCase();
    let visible = 0;

    items.forEach((d) => {
      const question = (d.querySelector('.supportFaqItem__q')?.textContent || '').toLowerCase();
      const answer = (d.querySelector('.supportFaqItem__a')?.textContent || '').toLowerCase();
      const show = !q || (question + ' ' + answer).includes(q);
      d.style.display = show ? '' : 'none';
      if (show) visible++;
      if (q) d.open = show;
      else d.open = false;
    });

    groups.forEach((g) => {
      const any = Array.from(g.querySelectorAll('.supportFaqItem')).some((d) => d.style.display !== 'none');
      g.style.display = any ? '' : 'none';
    });

    topicCards.forEach((card) => {
      if (!q) { card.style.display = ''; return; }
      const topic = (card.getAttribute('data-topic') || '').toLowerCase();
      const g = document.getElementById('faq-' + topic);
      card.style.display = g && g.style.display !== 'none' ? '' : 'none';
    });

    if (results) {
      if (!q) {
        results.style.display = 'none';
        results.textContent = '';
      } else {
        results.style.display = 'block';
        results.textContent = 'Matokeo: ' + visible;
      }
    }
  }

  input?.addEventListener('input', apply);
  apply();
})();
</script>
@endsection
