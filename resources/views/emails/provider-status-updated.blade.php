<x-emails.glamo-layout
    title="Habari {{ $payload['name'] ?? 'Mtoa huduma' }}"
    preheader="Kuna mabadiliko ya status kwenye akaunti yako ya Glamo."
    :button-text="'Fungua Dashibodi'"
    :button-url="url('/mtoa-huduma/dashibodi')"
>
    <p style="margin:0 0 10px;">{{ $payload['headline'] ?? 'Kuna mabadiliko ya status kwenye akaunti yako ya Glamo.' }}</p>

    <p style="margin:0 0 6px;"><strong>Uhakiki:</strong> {{ $payload['approval_label'] ?? '-' }}</p>
    <p style="margin:0 0 6px;"><strong>Online:</strong> {{ $payload['online_label'] ?? '-' }}</p>

    @if(!empty($payload['interview_label']))
        <p style="margin:0 0 6px;"><strong>Interview:</strong> {{ $payload['interview_label'] }}</p>
    @endif

    @if(!empty($payload['interview_time']))
        <p style="margin:0 0 6px;"><strong>Tarehe ya interview:</strong> {{ $payload['interview_time'] }}</p>
    @endif

    @if(!empty($payload['interview_location']))
        <p style="margin:0 0 6px;"><strong>Sehemu ya interview:</strong> {{ $payload['interview_location'] }}</p>
    @endif

    @if(!empty($payload['interview_type']))
        <p style="margin:0 0 6px;"><strong>Aina ya interview:</strong> {{ $payload['interview_type'] }}</p>
    @endif

    @if(!empty($payload['note']))
        <p style="margin:0;"><strong>Maelezo:</strong> {{ $payload['note'] }}</p>
    @endif
</x-emails.glamo-layout>
