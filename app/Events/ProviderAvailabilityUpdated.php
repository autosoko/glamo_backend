<?php

namespace App\Events;

use App\Models\Provider;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProviderAvailabilityUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public Provider $provider, public array $meta = [])
    {
    }

    public function broadcastOn(): array
    {
        $userId = (int) ($this->provider->user_id ?? 0);

        if ($userId <= 0) {
            return [];
        }

        return [
            new PrivateChannel("user.$userId"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'provider.availability.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'provider_id' => (int) $this->provider->id,
            'user_id' => (int) ($this->provider->user_id ?? 0),
            'online_status' => (string) ($this->provider->online_status ?? 'offline'),
            'offline_reason' => $this->provider->offline_reason ?: null,
            'approval_status' => (string) ($this->provider->approval_status ?? ''),
            'debt_balance' => (float) ($this->provider->debt_balance ?? 0),
            'availability_control' => $this->meta['availability_control'] ?? null,
        ];
    }
}
