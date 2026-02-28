<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientOrderAccepted extends Notification
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $providerPhone = $this->order->provider?->phone_public
            ?? $this->order->provider?->user?->phone;

        return [
            'type' => 'client_order_accepted',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'status' => $this->order->status,
            'service' => $this->order->service?->name,
            'price_total' => (float) $this->order->price_total,
            'provider_phone' => $providerPhone,
            'message' => 'Order received ✅ Nakupigia simu mpendwa…',
        ];
    }
}
