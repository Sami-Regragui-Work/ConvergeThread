<?php

namespace App\Notifications;

use App\Models\RegistrationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RegistrationApprovalRequiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly RegistrationRequest $registration,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $url = $this->registration->tenant_id !== null
            ? route('workspace.members.index')
            : route('owner.index');

        return [
            'type' => 'registration_pending',
            'email' => $this->registration->email,
            'tenant_id' => $this->registration->tenant_id,
            'tenant_name' => $this->registration->tenant?->name,
            'tenant_slug' => $this->registration->tenant_slug,
            'url' => $url,
        ];
    }
}
