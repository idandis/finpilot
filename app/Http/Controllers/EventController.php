<?php

namespace App\Http\Controllers;

use App\Http\Requests\Calendar\EventStoreRequest;
use App\Http\Requests\Calendar\EventUpdateRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EventController extends Controller
{
    public function store(EventStoreRequest $request): RedirectResponse
    {
        $request->user()->events()->create($request->validated());

        return back();
    }

    public function update(EventUpdateRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        return back();
    }

    /**
     * Moves a timed event to a new start, dragged from the Calendario
     * page's week/day grid - a lighter-weight sibling of update() that only
     * touches the start, preserving the event's original duration by
     * shifting end_at by the same amount (a promemoria, whose end_at is
     * always null, stays null).
     */
    public function reschedule(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'start_at' => ['required', 'date'],
        ]);

        $newStart = Carbon::parse($validated['start_at']);
        $durationMinutes = $event->end_at ? $event->start_at->diffInMinutes($event->end_at) : null;

        $event->update([
            'start_at' => $newStart,
            'end_at' => $durationMinutes !== null ? $newStart->copy()->addMinutes($durationMinutes) : null,
        ]);

        return back();
    }

    /**
     * Changes a timed event's end (its duration), dragged from the resize
     * handle at the bottom of its block in the Calendario page's week/day
     * grid - a lighter-weight sibling of update() that only touches end_at.
     * A promemoria (no duration by definition) or an all-day event (no
     * hourly block to resize) can't be resized.
     */
    public function resize(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);
        abort_if($event->all_day || $event->end_at === null, 422, 'Questo elemento non può essere ridimensionato.');

        $validated = $request->validate([
            'end_at' => ['required', 'date'],
        ]);

        $newEnd = Carbon::parse($validated['end_at']);

        abort_if($newEnd->lte($event->start_at), 422, 'La fine deve essere successiva all\'inizio.');

        $event->update(['end_at' => $newEnd]);

        return back();
    }

    public function destroy(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->user_id === $request->user()->id, 403);

        $event->delete();

        return back();
    }
}
