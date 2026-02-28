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
        // notify provider user channel + order channel
        $providerUserId = $this->order->provider?->user_id;
        return [
            new PrivateChannel("user.$providerUserId"),
            new PrivateChannel("order.{$this->order->id}"),
        ];
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
        ];
    }
}
