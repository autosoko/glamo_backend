<?php

namespace App\Mail;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProviderStatusUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Provider $provider,
        public User $user,
        public array $payload
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Glamo: Taarifa ya status ya akaunti yako')
            ->view('emails.provider-status-updated');
    }
}

