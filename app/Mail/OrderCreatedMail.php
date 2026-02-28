<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $audience = 'client'
    ) {
    }

    public function build(): self
    {
        $orderNo = (string) ($this->order->order_no ?? '');
        $subject = $this->audience === 'provider'
            ? 'Glamo: Oda mpya imeingia ' . $orderNo
            : 'Glamo: Oda yako imepokelewa ' . $orderNo;

        return $this
            ->subject(trim($subject))
            ->view('emails.order-created');
    }
}

