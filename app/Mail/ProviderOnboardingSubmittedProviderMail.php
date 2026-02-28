<?php

namespace App\Mail;

use App\Models\Provider;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProviderOnboardingSubmittedProviderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Provider $provider,
        public User $user
    ) {
    }

    public function build()
    {
        return $this->subject('Glamo: Tumepokea taarifa zako')
            ->view('emails.provider-onboarding-provider');
    }
}
