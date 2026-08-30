<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\Sharing\SharedResource;
use Illuminate\Notifications\Notification;

/**
 * The small "qualcun altro ha toccato una cosa nostra" heads-up, written
 * only to the bell: a busy shopping list would otherwise turn into a dozen
 * emails an afternoon.
 */
class SharedResourceActivity extends Notification
{
    public function __construct(
        private readonly User $actor,
        private readonly SharedResource $resource,
        /** What the actor did, e.g. 'ha aggiunto "Mele"'. */
        private readonly string $action,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'activity',
            'actor' => ['id' => $this->actor->id, 'name' => $this->actor->name],
            'message' => "{$this->actor->name} {$this->action}",
            'resource' => $this->resource->toArray(),
        ];
    }
}
