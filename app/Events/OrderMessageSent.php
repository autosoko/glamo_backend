<?php

namespace App\Events;

use App\Models\Message;
use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order, public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->order->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name,phone,role');

        return [
            'order_id' => (int) $this->order->id,
            'conversation_id' => (int) $this->message->conversation_id,
            'message' => [
                'id' => (int) $this->message->id,
                'body' => (string) ($this->message->body ?? ''),
                'sender' => [
                    'id' => (int) $this->message->sender_id,
                    'name' => (string) data_get($this->message, 'sender.name', ''),
                    'role' => (string) data_get($this->message, 'sender.role', ''),
                    'phone' => (string) data_get($this->message, 'sender.phone', ''),
                ],
                'read_at' => optional($this->message->read_at)->toIso8601String(),
                'created_at' => optional($this->message->created_at)->toIso8601String(),
            ],
        ];
    }
}
