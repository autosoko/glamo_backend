<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        $this->order->loadMissing('provider:id,user_id');

        $channels = [
            new PrivateChannel("order.{$this->order->id}"),
        ];

        $providerUserId = (int) data_get($this->order, 'provider.user_id', 0);
        if ($providerUserId > 0) {
            $channels[] = new PrivateChannel("user.$providerUserId");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.created';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(['service','client','provider.user']);

        return [
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'status' => $this->order->status,
            'service' => $this->order->service?->name,
            'price_total' => (float) $this->order->price_total,
            'client_phone' => $this->order->client?->phone,
            'client_lat' => (float) $this->order->client_lat,
            'client_lng' => (float) $this->order->client_lng,
            'target_screen' => 'provider_order_details',
            'auto_open' => true,
            'clear_active_order' => false,
        ];
    }
}
