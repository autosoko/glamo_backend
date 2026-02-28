@php
    $name = trim(implode(' ', array_filter([
        trim((string) ($provider->first_name ?? '')),
        trim((string) ($provider->middle_name ?? '')),
        trim((string) ($provider->last_name ?? '')),
    ])));
    $name = $name !== '' ? $name : (string) ($user->name ?? 'Mtoa huduma');

    $skills = collect((array) ($provider->selected_skills ?? []))
        ->map(fn ($skill) => ucwords(str_replace(['-', '_'], ' ', strtolower((string) $skill))))
        ->filter()
        ->values()
        ->all();
@endphp

<x-emails.glamo-layout
    title="Maombi mapya ya mtoa huduma"
    preheader="Mtoa huduma mpya amewasilisha taarifa za usajili."
    :button-text="'Fungua Admin Panel'"
    :button-url="url('/admin')"
>
    <p style="margin:0 0 10px;">Mtoa huduma mpya amewasilisha taarifa za usajili.</p>

    <p style="margin:0 0 6px;"><strong>Jina:</strong> {{ $name }}</p>
    <p style="margin:0 0 6px;"><strong>Email:</strong> {{ $user->email ?: 'Hakuna email' }}</p>
    <p style="margin:0 0 6px;"><strong>Simu:</strong> {{ $provider->phone_public ?: ($user->phone ?: 'Hakuna simu') }}</p>
    <p style="margin:0 0 6px;"><strong>Aina ya kitambulisho:</strong> {{ strtoupper((string) ($provider->id_type ?? '-')) }}</p>
    <p style="margin:0 0 6px;"><strong>Ujuzi:</strong> {{ !empty($skills) ? implode(', ', $skills) : 'Haijatajwa' }}</p>
    <p style="margin:0;"><strong>Muda wa kutuma:</strong> {{ optional($provider->onboarding_submitted_at)->format('Y-m-d H:i') ?: now()->format('Y-m-d H:i') }}</p>
</x-emails.glamo-layout>
