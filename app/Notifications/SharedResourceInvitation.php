<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Sharing\SharedResource;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Ti hanno invitato": the one notification that also leaves the app, since
 * an invited person may not be looking at Finpilot at all when it happens.
 * Everything that follows (see SharedResourceActivity) stays in the bell.
 */
class SharedResourceInvitation extends Notification
{
    public function __construct(
        private readonly User $inviter,
        private readonly SharedResource $resource,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->inviter->name} ti ha condiviso «{$this->resource->name}»")
            ->markdown('mail.shared-resource-invitation', [
                'invited' => $notifiable,
                'inviter' => $this->inviter,
                'resource' => $this->resource,
                'url' => url($this->resource->url),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invitation',
            'actor' => ['id' => $this->inviter->id, 'name' => $this->inviter->name],
            'message' => "{$this->inviter->name} ti ha condiviso «{$this->resource->name}»",
            'resource' => $this->resource->toArray(),
        ];
    }
}
