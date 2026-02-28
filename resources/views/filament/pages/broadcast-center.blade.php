<x-filament-panels::page>
    @php
        $segmentTotal = (int) ($segmentCounts['total'] ?? 0);
        $safeTotal = max(1, $segmentTotal);
        $emailCoverage = (int) round(((int) ($segmentCounts['email_registered'] ?? 0) / $safeTotal) * 100);
        $phoneCoverage = (int) round(((int) ($segmentCounts['phone_registered'] ?? 0) / $safeTotal) * 100);
        $bothCoverage = (int) round(((int) ($segmentCounts['both'] ?? 0) / $safeTotal) * 100);
    @endphp

    <style>
        .bcxPage {
            --bcx-bg: #f4f5f8;
            --bcx-card: #ffffff;
            --bcx-line: #e6e8ef;
            --bcx-text: #171a2b;
            --bcx-muted: #5d637b;
            --bcx-accent: #5a0e24;
            --bcx-accent-soft: #f6ebf0;
            --bcx-ok: #0b7a52;
            --bcx-shadow: 0 12px 28px -18px rgba(20, 24, 38, 0.45);
            display: grid;
            gap: 1rem;
        }

        .bcxHero {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
            gap: 0.9rem;
            border-radius: 1.1rem;
            border: 1px solid var(--bcx-line);
            background:
                radial-gradient(620px 230px at 8% 0%, rgba(90, 14, 36, 0.16), transparent 64%),
                radial-gradient(520px 200px at 93% 12%, rgba(90, 14, 36, 0.1), transparent 68%),
                var(--bcx-card);
            padding: 1rem;
            box-shadow: var(--bcx-shadow);
        }

        .bcxKicker {
            margin: 0;
            color: var(--bcx-accent);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .bcxHero__title {
            margin: 0.35rem 0 0;
            color: var(--bcx-text);
            font-size: clamp(1.25rem, 1.8vw, 1.72rem);
            line-height: 1.2;
            font-weight: 900;
        }

        .bcxHero__sub {
            margin: 0.45rem 0 0;
            color: var(--bcx-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            max-width: 68ch;
        }

        .bcxHero__focus {
            border: 1px solid rgba(90, 14, 36, 0.2);
            border-radius: 0.95rem;
            background: rgba(255, 255, 255, 0.88);
            padding: 0.85rem;
            align-self: center;
        }

        .bcxFocus__label {
            color: var(--bcx-muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            font-weight: 700;
        }

        .bcxFocus__name {
            margin-top: 0.3rem;
            color: var(--bcx-text);
            font-size: 0.92rem;
            line-height: 1.4;
            font-weight: 800;
        }

        .bcxFocus__count {
            margin-top: 0.45rem;
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.34rem 0.66rem;
            background: var(--bcx-accent-soft);
            color: var(--bcx-accent);
            font-size: 0.75rem;
            font-weight: 800;
        }

        .bcxStats {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .bcxStat {
            border: 1px solid var(--bcx-line);
            border-radius: 0.95rem;
            background: var(--bcx-card);
            padding: 0.8rem;
            box-shadow: var(--bcx-shadow);
        }

        .bcxStat__label {
            margin: 0;
            color: var(--bcx-muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }

        .bcxStat__value {
            margin: 0.28rem 0 0;
            color: var(--bcx-text);
            font-size: clamp(1.05rem, 1.4vw, 1.45rem);
            font-weight: 900;
            line-height: 1.15;
        }

        .bcxMain {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1.55fr) minmax(295px, 0.95fr);
        }

        .bcxCard {
            border: 1px solid var(--bcx-line);
            border-radius: 1rem;
            background: var(--bcx-card);
            padding: 1rem;
            box-shadow: var(--bcx-shadow);
        }

        .bcxCard__head h2,
        .bcxCard__head h3 {
            margin: 0;
            color: var(--bcx-text);
            font-size: 1.02rem;
            line-height: 1.25;
            font-weight: 800;
        }

        .bcxCard__head p {
            margin: 0.3rem 0 0;
            color: var(--bcx-muted);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        .bcxForm {
            margin-top: 0.9rem;
            display: grid;
            gap: 0.85rem;
        }

        .bcxField {
            display: grid;
            gap: 0.35rem;
        }

        .bcxField label {
            color: var(--bcx-text);
            font-size: 0.8rem;
            font-weight: 700;
        }

        .bcxInput,
        .bcxSelect,
        .bcxTextarea {
            width: 100%;
            border-radius: 0.8rem;
            border: 1px solid #d3d8e4;
            background: #fff;
            color: var(--bcx-text);
            font-size: 0.88rem;
            padding: 0.68rem 0.78rem;
            outline: 0;
            transition: border-color 0.16s ease, box-shadow 0.16s ease;
        }

        .bcxInput:focus,
        .bcxSelect:focus,
        .bcxTextarea:focus {
            border-color: var(--bcx-accent);
            box-shadow: 0 0 0 3px rgba(90, 14, 36, 0.11);
        }

        .bcxTextarea {
            min-height: 190px;
            resize: vertical;
            line-height: 1.58;
        }

        .bcxError {
            color: #b42318;
            font-size: 0.74rem;
            font-weight: 600;
        }

        .bcxCheckbox {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            border: 1px solid var(--bcx-line);
            border-radius: 0.8rem;
            padding: 0.74rem;
            background: #fbfbfd;
            color: var(--bcx-text);
            font-size: 0.84rem;
            line-height: 1.45;
        }

        .bcxCheckbox input[type="checkbox"] {
            margin-top: 0.13rem;
            accent-color: var(--bcx-accent);
        }

        .bcxActions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .bcxLoading {
            color: var(--bcx-muted);
            font-size: 0.76rem;
            font-weight: 700;
        }

        .bcxSidebar {
            display: grid;
            gap: 1rem;
            align-content: start;
        }

        .bcxRows {
            margin-top: 0.8rem;
            display: grid;
            gap: 0.62rem;
        }

        .bcxRow {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.8rem;
            color: var(--bcx-muted);
            font-size: 0.79rem;
        }

        .bcxRow strong {
            color: var(--bcx-text);
            font-size: 0.82rem;
            font-weight: 800;
        }

        .bcxMeter {
            height: 0.45rem;
            border-radius: 999px;
            background: #eceef4;
            overflow: hidden;
        }

        .bcxMeter > span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #5a0e24, #8f2a4a);
        }

        .bcxMiniGrid {
            margin-top: 0.85rem;
            display: grid;
            gap: 0.6rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .bcxMini {
            border: 1px solid var(--bcx-line);
            border-radius: 0.78rem;
            background: #fff;
            padding: 0.6rem;
        }

        .bcxMini span {
            display: block;
            color: var(--bcx-muted);
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        .bcxMini strong {
            margin-top: 0.16rem;
            display: block;
            color: var(--bcx-text);
            font-size: 1rem;
            line-height: 1.15;
            font-weight: 900;
        }

        .bcxChecklist {
            margin: 0.75rem 0 0;
            padding-left: 1rem;
            display: grid;
            gap: 0.35rem;
            color: var(--bcx-muted);
            font-size: 0.82rem;
            line-height: 1.52;
        }

        .bcxCard--result {
            padding-bottom: 0.9rem;
        }

        .bcxResults {
            margin-top: 0.85rem;
            display: grid;
            gap: 0.65rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .bcxResult {
            border: 1px solid var(--bcx-line);
            border-radius: 0.8rem;
            background: #fff;
            padding: 0.72rem;
        }

        .bcxResult__label {
            margin: 0;
            color: var(--bcx-muted);
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 700;
        }

        .bcxResult__value {
            margin: 0.22rem 0 0;
            color: var(--bcx-text);
            font-size: 1.04rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .bcxResult--ok .bcxResult__value {
            color: var(--bcx-ok);
        }

        @media (max-width: 1180px) {
            .bcxStats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .bcxMain {
                grid-template-columns: 1fr;
            }

            .bcxResults {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .bcxHero {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .bcxHero,
            .bcxCard,
            .bcxStat {
                border-radius: 0.86rem;
                padding: 0.85rem;
            }

            .bcxHero__title {
                font-size: 1.15rem;
            }

            .bcxStats,
            .bcxMiniGrid,
            .bcxResults {
                grid-template-columns: 1fr;
            }

            .bcxTextarea {
                min-height: 160px;
            }
        }
    </style>

    <div class="bcxPage">
        <section class="bcxHero">
            <div class="bcxHero__text">
                <p class="bcxKicker">Admin communication</p>
                <h1 class="bcxHero__title">Broadcast Center</h1>
                <p class="bcxHero__sub">
                    Tuma campaign ya email na SMS kwa segments tofauti za watumiaji wa Glamo.
                    Chagua walengwa sahihi, andika ujumbe wazi, kisha tuma kwa click moja.
                </p>
            </div>

            <div class="bcxHero__focus">
                <div class="bcxFocus__label">Segment ya sasa</div>
                <div class="bcxFocus__name">{{ $segmentOptions[$segment] ?? 'Walengwa' }}</div>
                <div class="bcxFocus__count">{{ number_format($segmentTotal) }} walengwa</div>
            </div>
        </section>

        <section class="bcxStats">
            <article class="bcxStat">
                <p class="bcxStat__label">Jumla ya walengwa</p>
                <p class="bcxStat__value">{{ number_format((int) ($globalCounts['total'] ?? 0)) }}</p>
            </article>
            <article class="bcxStat">
                <p class="bcxStat__label">Wenye email</p>
                <p class="bcxStat__value">{{ number_format((int) ($globalCounts['email_registered'] ?? 0)) }}</p>
            </article>
            <article class="bcxStat">
                <p class="bcxStat__label">Wenye simu</p>
                <p class="bcxStat__value">{{ number_format((int) ($globalCounts['phone_registered'] ?? 0)) }}</p>
            </article>
            <article class="bcxStat">
                <p class="bcxStat__label">Email + simu</p>
                <p class="bcxStat__value">{{ number_format((int) ($globalCounts['both'] ?? 0)) }}</p>
            </article>
        </section>

        <div class="bcxMain">
            <section class="bcxCard">
                <header class="bcxCard__head">
                    <h2>Andaa Ujumbe</h2>
                    <p>Email, in-app, na push hutumwa kila mara. Ukichagua SMS, itatumwa kwa walio na namba ya simu tu.</p>
                </header>

                <form wire:submit="dispatchBroadcast" class="bcxForm">
                    <div class="bcxField">
                        <label for="bcx-segment">Kundi la walengwa</label>
                        <select id="bcx-segment" wire:model.live="segment" class="bcxSelect">
                            @foreach($segmentOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('segment')
                            <div class="bcxError">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="bcxField">
                        <label for="bcx-subject">Subject ya email</label>
                        <input
                            id="bcx-subject"
                            type="text"
                            wire:model.defer="subject"
                            class="bcxInput"
                            placeholder="Mfano: Ofa mpya wiki hii"
                        >
                        @error('subject')
                            <div class="bcxError">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="bcxField">
                        <label for="bcx-message">Ujumbe</label>
                        <textarea
                            id="bcx-message"
                            wire:model.defer="message"
                            class="bcxTextarea"
                            placeholder="Andika ujumbe hapa..."
                        ></textarea>
                        @error('message')
                            <div class="bcxError">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="bcxCheckbox" for="bcx-sms">
                        <input id="bcx-sms" type="checkbox" wire:model="sendSms">
                        <span>Tuma pia SMS kwa watumiaji wenye namba ya simu.</span>
                    </label>

                    <div class="bcxActions">
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled">
                            Tuma broadcast
                        </x-filament::button>
                        <span wire:loading class="bcxLoading">Inatuma, subiri kidogo...</span>
                    </div>
                </form>
            </section>

            <aside class="bcxSidebar">
                <section class="bcxCard">
                    <header class="bcxCard__head">
                        <h3>Coverage ya segment</h3>
                        <p>Muhtasari wa mawasiliano yanayopatikana ndani ya kundi ulilochagua.</p>
                    </header>

                    <div class="bcxRows">
                        <div class="bcxRow">
                            <span>Email coverage</span>
                            <strong>{{ $emailCoverage }}%</strong>
                        </div>
                        <div class="bcxMeter"><span style="width: {{ max(0, min(100, $emailCoverage)) }}%;"></span></div>

                        <div class="bcxRow">
                            <span>Phone coverage</span>
                            <strong>{{ $phoneCoverage }}%</strong>
                        </div>
                        <div class="bcxMeter"><span style="width: {{ max(0, min(100, $phoneCoverage)) }}%;"></span></div>

                        <div class="bcxRow">
                            <span>Email + phone</span>
                            <strong>{{ $bothCoverage }}%</strong>
                        </div>
                        <div class="bcxMeter"><span style="width: {{ max(0, min(100, $bothCoverage)) }}%;"></span></div>
                    </div>

                    <div class="bcxMiniGrid">
                        <article class="bcxMini">
                            <span>Walengwa</span>
                            <strong>{{ number_format((int) ($segmentCounts['total'] ?? 0)) }}</strong>
                        </article>
                        <article class="bcxMini">
                            <span>Email only</span>
                            <strong>{{ number_format((int) ($segmentCounts['email_only'] ?? 0)) }}</strong>
                        </article>
                        <article class="bcxMini">
                            <span>Phone only</span>
                            <strong>{{ number_format((int) ($segmentCounts['phone_only'] ?? 0)) }}</strong>
                        </article>
                        <article class="bcxMini">
                            <span>Email + phone</span>
                            <strong>{{ number_format((int) ($segmentCounts['both'] ?? 0)) }}</strong>
                        </article>
                    </div>
                </section>

                <section class="bcxCard">
                    <header class="bcxCard__head">
                        <h3>Checklist kabla ya kutuma</h3>
                        <p>Hii itasaidia campaign iwe wazi na yenye matokeo bora.</p>
                    </header>
                    <ul class="bcxChecklist">
                        <li>Thibitisha umechagua segment sahihi.</li>
                        <li>Andika subject fupi na inayoeleweka.</li>
                        <li>Weka call-to-action moja ili ujumbe usichanganye.</li>
                        <li>Tumia SMS kwa matangazo muhimu au ya haraka.</li>
                    </ul>
                </section>
            </aside>
        </div>

        @if(!empty($lastResult))
            <section class="bcxCard bcxCard--result">
                <header class="bcxCard__head">
                    <h2>Matokeo ya utumaji wa mwisho</h2>
                    <p>Ripoti hii inaonyesha status ya kutuma email/SMS/in-app/push.</p>
                </header>

                <div class="bcxResults">
                    <article class="bcxResult">
                        <p class="bcxResult__label">Walengwa</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['recipients'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult bcxResult--ok">
                        <p class="bcxResult__label">Email sent</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['email_sent'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult">
                        <p class="bcxResult__label">Email failed</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['email_failed'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult bcxResult--ok">
                        <p class="bcxResult__label">SMS sent</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['sms_sent'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult">
                        <p class="bcxResult__label">SMS failed</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['sms_failed'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult bcxResult--ok">
                        <p class="bcxResult__label">Push sent</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['push_sent'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult">
                        <p class="bcxResult__label">Push failed</p>
                        <p class="bcxResult__value">{{ number_format((int) ($lastResult['push_failed'] ?? 0)) }}</p>
                    </article>
                    <article class="bcxResult">
                        <p class="bcxResult__label">Missing email / phone</p>
                        <p class="bcxResult__value">
                            {{ number_format((int) ($lastResult['missing_email'] ?? 0)) }} / {{ number_format((int) ($lastResult['missing_phone'] ?? 0)) }}
                        </p>
                    </article>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
