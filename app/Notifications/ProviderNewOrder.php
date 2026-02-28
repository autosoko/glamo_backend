<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProviderNewOrder extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'provider_new_order',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'service' => $this->order->service?->name,
            'price_total' => (float) $this->order->price_total,
            'client_phone' => $this->order->client?->phone,
            'client_lat' => (float) $this->order->client_lat,
            'client_lng' => (float) $this->order->client_lng,
            'message' => 'Una oda mpya. Fungua ili uthibitishe.',
        ];
    }
}
