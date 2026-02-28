<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderAccepted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->order->client_id}"),
            new PrivateChannel("order.{$this->order->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.accepted';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(['provider.user','service']);

        return [
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'status' => $this->order->status,
            'provider_phone' => $this->order->provider?->phone_public ?? $this->order->provider?->user?->phone,
            'message' => 'Order received ✅ Nakupigia simu mpendwa…',
        ];
    }
}
