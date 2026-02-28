<x-filament-panels::page>
    @php
        $audienceTotal = (int) ($audienceStats['total'] ?? 0);
        $pushRegistered = (int) ($audienceStats['push_registered'] ?? 0);
        $withoutPush = (int) ($audienceStats['without_push'] ?? 0);
        $pushCoverage = $audienceTotal > 0 ? (int) round(($pushRegistered / $audienceTotal) * 100) : 0;
        $configured = (bool) ($pushStatus['configured'] ?? false);
    @endphp

    <style>
        .ancPage {
            --anc-bg: #f6f7fb;
            --anc-card: #ffffff;
            --anc-border: #e5e7ef;
            --anc-text: #1b1e2e;
            --anc-muted: #636b84;
            --anc-accent: #5a0e24;
            --anc-accent-soft: #f6ebf0;
            --anc-ok: #0b7a52;
            --anc-warn: #92400e;
            display: grid;
            gap: 1rem;
        }

        .ancHero,
        .ancCard {
            background: var(--anc-card);
            border: 1px solid var(--anc-border);
            border-radius: 1rem;
            padding: 1rem;
        }

        .ancHero {
            display: grid;
            gap: 0.6rem;
            background:
                radial-gradient(560px 220px at 0% 0%, rgba(90, 14, 36, 0.14), transparent 65%),
                var(--anc-card);
        }

        .ancKicker {
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--anc-accent);
        }

        .ancTitle {
            margin: 0;
            color: var(--anc-text);
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .ancSub {
            margin: 0;
            color: var(--anc-muted);
            font-size: 0.88rem;
            line-height: 1.6;
        }

        .ancStatus {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 800;
            width: fit-content;
        }

        .ancStatus--ok {
            background: #eaf7f2;
            color: var(--anc-ok);
        }

        .ancStatus--warn {
            background: #fff6e8;
            color: var(--anc-warn);
        }

        .ancGrid {
            display: grid;
            grid-template-columns: minmax(0, 1.5fr) minmax(250px, 1fr);
            gap: 1rem;
        }

        .ancField {
            display: grid;
            gap: 0.35rem;
            margin-bottom: 0.82rem;
        }

        .ancField label {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--anc-text);
        }

        .ancInput,
        .ancSelect,
        .ancTextarea {
            border: 1px solid #d3d9e5;
            border-radius: 0.78rem;
            padding: 0.68rem 0.78rem;
            font-size: 0.9rem;
            color: var(--anc-text);
            background: #fff;
            outline: 0;
            width: 100%;
        }

        .ancTextarea {
            min-height: 180px;
            resize: vertical;
            line-height: 1.55;
        }

        .ancInput:focus,
        .ancSelect:focus,
        .ancTextarea:focus {
            border-color: var(--anc-accent);
            box-shadow: 0 0 0 3px rgba(90, 14, 36, 0.12);
        }

        .ancError {
            color: #b42318;
            font-size: 0.74rem;
            font-weight: 600;
        }

        .ancRows {
            display: grid;
            gap: 0.7rem;
            margin-top: 0.4rem;
        }

        .ancRow {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.8rem;
            font-size: 0.82rem;
            color: var(--anc-muted);
        }

        .ancRow strong {
            color: var(--anc-text);
            font-weight: 800;
        }

        .ancMeter {
            height: 0.46rem;
            border-radius: 999px;
            background: #eceff5;
            overflow: hidden;
        }

        .ancMeter > span {
            display: block;
            height: 100%;
            background: linear-gradient(90deg, #5a0e24, #8b2948);
            border-radius: inherit;
        }

        .ancResults {
            display: grid;
            gap: 0.65rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            margin-top: 0.85rem;
        }

        .ancResult {
            border: 1px solid var(--anc-border);
            border-radius: 0.78rem;
            padding: 0.7rem;
            background: #fff;
        }

        .ancResult__label {
            margin: 0;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--anc-muted);
            font-weight: 700;
        }

        .ancResult__value {
            margin: 0.2rem 0 0;
            color: var(--anc-text);
            font-size: 1.02rem;
            font-weight: 900;
            line-height: 1.2;
        }

        @media (max-width: 1000px) {
            .ancGrid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 740px) {
            .ancResults {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ancPage">
        <section class="ancHero">
            <p class="ancKicker">Admin push center</p>
            <h1 class="ancTitle">Tuma notification kwenye app</h1>
            <p class="ancSub">Chagua app ya walengwa (mteja au mtoa huduma), andika notification, kisha tuma moja kwa moja kwenye in-app na push.</p>
            <div class="ancStatus {{ $configured ? 'ancStatus--ok' : 'ancStatus--warn' }}">
                {{ $configured ? 'Push imeconfigure vizuri' : 'Push haijaconfigure (FCM key inahitajika)' }}
            </div>
        </section>

        <div class="ancGrid">
            <section class="ancCard">
                <form wire:submit="dispatchNotification">
                    <div class="ancField">
                        <label for="anc-audience">Tuma kwa app gani</label>
                        <select id="anc-audience" wire:model.live="audience" class="ancSelect">
                            @foreach($audienceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('audience')
                            <div class="ancError">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="ancField">
                        <label for="anc-title">Kichwa cha notification</label>
                        <input
                            id="anc-title"
                            type="text"
                            wire:model.defer="notificationTitle"
                            class="ancInput"
                            placeholder="Mfano: Ofa mpya leo"
                        >
                        @error('notificationTitle')
                            <div class="ancError">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="ancField">
                        <label for="anc-message">Ujumbe</label>
                        <textarea
                            id="anc-message"
                            wire:model.defer="notificationMessage"
                            class="ancTextarea"
                            placeholder="Andika ujumbe wa kwenda kwenye app..."
                        ></textarea>
                        @error('notificationMessage')
                            <div class="ancError">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="ancField">
                        <label for="anc-screen">Target screen (option)</label>
                        <input
                            id="anc-screen"
                            type="text"
                            wire:model.defer="targetScreen"
                            class="ancInput"
                            placeholder="home / order_details / offers"
                        >
                        @error('targetScreen')
                            <div class="ancError">{{ $message }}</div>
                        @enderror
                    </div>

                    <x-filament::button type="submit" icon="heroicon-o-paper-airplane" wire:loading.attr="disabled">
                        Tuma notification
                    </x-filament::button>
                </form>
            </section>

            <aside class="ancCard">
                <h3 style="margin:0;color:var(--anc-text);font-size:1rem;font-weight:800;">Muhtasari wa walengwa</h3>
                <div class="ancRows">
                    <div class="ancRow">
                        <span>Jumla ya walengwa</span>
                        <strong>{{ number_format($audienceTotal) }}</strong>
                    </div>
                    <div class="ancRow">
                        <span>Wenye push token active</span>
                        <strong>{{ number_format($pushRegistered) }}</strong>
                    </div>
                    <div class="ancMeter"><span style="width: {{ max(0, min(100, $pushCoverage)) }}%;"></span></div>
                    <div class="ancRow">
                        <span>Bila push token active</span>
                        <strong>{{ number_format($withoutPush) }}</strong>
                    </div>
                    <div class="ancRow">
                        <span>Push coverage</span>
                        <strong>{{ $pushCoverage }}%</strong>
                    </div>
                </div>
            </aside>
        </div>

        @if(!empty($lastResult))
            <section class="ancCard">
                <h3 style="margin:0;color:var(--anc-text);font-size:1rem;font-weight:800;">Matokeo ya utumaji wa mwisho</h3>
                <div class="ancResults">
                    <article class="ancResult">
                        <p class="ancResult__label">Walengwa</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['recipients'] ?? 0)) }}</p>
                    </article>
                    <article class="ancResult">
                        <p class="ancResult__label">In-app saved</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['database_sent'] ?? 0)) }}</p>
                    </article>
                    <article class="ancResult">
                        <p class="ancResult__label">Push tokens</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['push_tokens'] ?? 0)) }}</p>
                    </article>
                    <article class="ancResult">
                        <p class="ancResult__label">Push attempted</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['push_attempted'] ?? 0)) }}</p>
                    </article>
                    <article class="ancResult">
                        <p class="ancResult__label">Push sent</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['push_sent'] ?? 0)) }}</p>
                    </article>
                    <article class="ancResult">
                        <p class="ancResult__label">Push failed</p>
                        <p class="ancResult__value">{{ number_format((int) ($lastResult['push_failed'] ?? 0)) }}</p>
                    </article>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
