<?php

namespace App\Services;

use App\Events\OrderCreated;
use App\Events\OrderMessageSent;
use App\Events\OrderStatusChanged;
use App\Models\Message;
use App\Models\Order;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

class OrderRealtimeService
{
    public function __construct(private readonly Dispatcher $events)
    {
    }

    public function dispatchCreated(Order $order): void
    {
        $this->dispatch(new OrderCreated($order), $order, [
            'channel' => 'order_created',
        ]);
    }

    public function dispatchMessageSent(Order $order, Message $message): void
    {
        $this->dispatch(new OrderMessageSent($order, $message), $order, [
            'channel' => 'order_message_sent',
            'message_id' => (int) $message->id,
        ]);
    }

    public function dispatchStatusChanged(Order $order, array $meta = []): void
    {
        $this->dispatch(new OrderStatusChanged($order, $meta), $order, [
            'channel' => 'order_status_changed',
            'meta' => $meta,
        ]);
    }

    private function dispatch(object $event, Order $order, array $context = []): void
    {
        try {
            $this->events->dispatch($event);
        } catch (\Throwable $e) {
            Log::warning('Order realtime dispatch failed', array_merge([
                'order_id' => (int) $order->id,
                'status' => (string) ($order->status ?? ''),
                'error' => $e->getMessage(),
            ], $context));
        }
    }
}
