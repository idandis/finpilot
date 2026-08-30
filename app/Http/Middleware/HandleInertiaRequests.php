<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => $this->notifications($request->user()),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * What the bell in the sidebar shows: how many are still unread, plus
     * the most recent ones. Kept short on purpose - it rides along with
     * every single page response.
     *
     * @return array{unread: int, items: array<int, array<string, mixed>>}|null
     */
    private function notifications(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->latest()->limit(15)->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? 'activity',
                    'message' => $notification->data['message'] ?? '',
                    'resource' => $notification->data['resource'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
