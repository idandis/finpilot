<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The bell in the sidebar. Its contents travel with every page as a shared
 * Inertia prop (see HandleInertiaRequests), so all that is left here is
 * marking things read and emptying the list.
 */
class NotificationController extends Controller
{
    /**
     * Reading one - what happens when the bell's item is clicked, right
     * before following it to the list/board/plan it talks about.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->unreadNotifications()->whereKey($notification)->update(['read_at' => now()]);

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }
}
