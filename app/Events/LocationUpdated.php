<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $actor, // provider|client
        public float $lat,
        public float $lng,
        public int $ts
    ) {}

    public function broadcastOn(): array
    {
        return [ new PrivateChannel("order.{$this->orderId}") ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'actor' => $this->actor,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'ts' => $this->ts,
        ];
    }
}
