@php
    $name = trim(implode(' ', array_filter([
        trim((string) ($provider->first_name ?? '')),
        trim((string) ($provider->middle_name ?? '')),
        trim((string) ($provider->last_name ?? '')),
    ])));
    $name = $name !== '' ? $name : (string) ($user->name ?? 'Mteja');
@endphp

<x-emails.glamo-layout
    title="Asante {{ $name }}"
    preheader="Tumepokea taarifa zako za usajili wa mtoa huduma."
    :button-text="'Fungua Dashibodi'"
    :button-url="url('/mtoa-huduma/dashibodi')"
>
    <p style="margin:0 0 10px;">Tumepokea taarifa zako za usajili wa mtoa huduma kwenye Glamo.</p>
    <p style="margin:0 0 10px;">Kwa sasa maombi yako yako kwenye uhakiki wa timu yetu.</p>
    <p style="margin:0;">Ukipitishwa au ukihitaji hatua ya ziada, utapokea ujumbe kupitia SMS au email.</p>
</x-emails.glamo-layout>
