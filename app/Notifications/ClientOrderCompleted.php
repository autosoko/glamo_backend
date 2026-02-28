<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClientOrderCompleted extends Notification
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
            'type' => 'client_order_completed',
            'order_id' => $this->order->id,
            'order_no' => $this->order->order_no,
            'message' => 'Kazi imekamilika ✅ Asante kwa kutumia Glamo.',
            'completion_note' => $this->order->completion_note,
        ];
    }
}
