<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public array $meta = [])
    {
    }

    public function broadcastOn(): array
    {
        $this->order->loadMissing('provider:id,user_id');

        $channels = [
            new PrivateChannel("user.{$this->order->client_id}"),
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
        return 'order.status.changed';
    }

    public function broadcastWith(): array
    {
        $this->order->loadMissing(['service:id,name', 'provider:id,user_id']);

        $clientTargetScreen = (string) ($this->meta['client_target_screen'] ?? $this->meta['target_screen'] ?? 'order_details');
        $providerTargetScreen = (string) ($this->meta['provider_target_screen'] ?? $this->meta['target_screen'] ?? 'provider_order_details');

        return array_merge([
            'order_id' => (int) $this->order->id,
            'order_no' => (string) ($this->order->order_no ?? ('#' . $this->order->id)),
            'status' => (string) ($this->order->status ?? ''),
            'service' => (string) data_get($this->order, 'service.name', ''),
            'provider_user_id' => (int) data_get($this->order, 'provider.user_id', 0),
            'client_target_screen' => $clientTargetScreen,
            'provider_target_screen' => $providerTargetScreen,
            'auto_open' => (bool) ($this->meta['auto_open'] ?? false),
            'clear_active_order' => (bool) ($this->meta['clear_active_order'] ?? false),
            'show_review' => (bool) ($this->meta['show_review'] ?? false),
        ], Arr::except($this->meta, [
            'client_target_screen',
            'provider_target_screen',
            'target_screen',
            'auto_open',
            'clear_active_order',
            'show_review',
        ]));
    }
}
