@php
    $orderNo = (string) ($order->order_no ?? '-');
    $amount = (float) ($order->price_total ?? 0);
    $serviceName = (string) (data_get($order, 'service.name') ?? 'Huduma');
    $clientName = (string) (data_get($order, 'client.name') ?? 'Mteja');
    $providerName = (string) (data_get($order, 'provider.display_name') ?? 'Mtoa huduma');
    $isProvider = (string) ($audience ?? 'client') === 'provider';

    $title = $isProvider
        ? 'Una oda mpya'
        : 'Oda yako imepokelewa';
    $preheader = $isProvider
        ? 'Oda mpya imeingia kwenye akaunti yako ya Glamo.'
        : 'Tumepokea oda yako ya Glamo na tunaifanyia kazi.';
    $buttonUrl = $isProvider
        ? url('/mtoa-huduma/dashibodi')
        : url('/oda/' . (int) $order->id);
@endphp

<x-emails.glamo-layout
    :title="$title"
    :preheader="$preheader"
    :button-text="'Fungua Oda'"
    :button-url="$buttonUrl"
>
    @if($isProvider)
        <p style="margin:0 0 10px;">Habari {{ $providerName }}, umepewa oda mpya kutoka {{ $clientName }}.</p>
    @else
        <p style="margin:0 0 10px;">Habari {{ $clientName }}, oda yako imepokelewa vizuri.</p>
    @endif

    <p style="margin:0 0 6px;"><strong>Order No:</strong> {{ $orderNo }}</p>
    <p style="margin:0 0 6px;"><strong>Huduma:</strong> {{ $serviceName }}</p>
    <p style="margin:0 0 6px;"><strong>Kiasi:</strong> TZS {{ number_format($amount, 0) }}</p>

    @if($isProvider)
        <p style="margin:12px 0 0;">Tafadhali fungua dashibodi/app yako kuanza hatua za oda.</p>
    @else
        <p style="margin:12px 0 0;">Fuatilia progress ya oda kwenye ukurasa wa oda. Malipo ya mteja ni cash.</p>
    @endif
</x-emails.glamo-layout>
