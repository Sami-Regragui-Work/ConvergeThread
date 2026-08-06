<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OwnerDashboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public array $scopes = ['workspace'],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('owner'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'owner.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'scopes' => $this->scopes,
            'at' => now()->toIso8601String(),
        ];
    }
}
