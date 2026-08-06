<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkspaceUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public int $tenantId,
        public array $scopes = ['workspace'],
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('workspace.'.$this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'workspace.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'scopes' => $this->scopes,
            'at' => now()->toIso8601String(),
        ];
    }
}
