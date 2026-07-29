<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Models\Workout;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CalendarController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const VIEWS = ['mese', 'settimana', 'giorno'];

    /**
     * Renders whichever of the three views (?vista=) is requested, anchored
     * on a given day (?data=, defaulting to today) - the backend only
     * resolves the visible date range and the items overlapping it, the
     * month/week/day grid layout itself is a frontend concern (same
     * "dumb backend, smart frontend" split TaskController's board uses).
     *
     * "items" merges three different sources - Event, Task and Workout -
     * into one flat, uniformly-shaped list so the frontend doesn't need to
     * know about three separate props. Only Event is actually owned by the
     * calendar; a task/workout here is a read-and-reposition view onto data
     * that still lives on (and is still edited from) its own page.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $view = in_array($request->query('vista'), self::VIEWS, true) ? $request->query('vista') : 'mese';
        $date = $this->resolveDate($request->query('data'), $today);
        $user = $request->user();

        [$rangeStart, $rangeEnd] = $this->rangeFor($view, $date);

        $events = $user->events()
            ->where('start_at', '<=', $rangeEnd)
            ->where(function ($query) use ($rangeStart) {
                $query->where(fn ($q) => $q->whereNotNull('end_at')->where('end_at', '>=', $rangeStart))
                    ->orWhere(fn ($q) => $q->whereNull('end_at')->where('start_at', '>=', $rangeStart));
            })
            ->get();

        $items = collect()
            ->concat($events->map(fn (Event $event) => $this->serializeEvent($event)))
            ->concat($this->tasksInRange($user, $rangeStart, $rangeEnd)->map(fn (Task $task) => $this->serializeTask($task)))
            ->concat($this->workoutsInRange($user, $rangeStart, $rangeEnd)->map(fn (Workout $workout) => $this->serializeWorkout($workout)))
            ->sortBy('start_at')
            ->values();

        return Inertia::render('Calendar/Index', [
            'view' => $view,
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'rangeStart' => $rangeStart->toDateString(),
            'rangeEnd' => $rangeEnd->toDateString(),
            'items' => $items->all(),
        ]);
    }

    /**
     * Falls back to today on a missing or malformed date, same as
     * TaskController::resolveDate() - this is only ever reached from the
     * prev/next/"back to today" links, not a form a user fills in.
     */
    private function resolveDate(?string $requested, Carbon $today): Carbon
    {
        if ($requested === null) {
            return $today->copy();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
        } catch (Throwable) {
            return $today->copy();
        }
    }

    /**
     * The month view's range is padded to full Monday-Sunday weeks on both
     * ends, so the grid always shows complete rows (leading/trailing days
     * from the adjacent months included, exactly like a real calendar).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeFor(string $view, Carbon $date): array
    {
        return match ($view) {
            'giorno' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'settimana' => [$date->copy()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfWeek(Carbon::SUNDAY)],
            default => [
                $date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY),
                $date->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY),
            ],
        };
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasksInRange(User $user, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        return $user->tasks()
            ->whereDate('task_date', '>=', $rangeStart->toDateString())
            ->whereDate('task_date', '<=', $rangeEnd->toDateString())
            ->get();
    }

    /**
     * @return Collection<int, Workout>
     */
    private function workoutsInRange(User $user, Carbon $rangeStart, Carbon $rangeEnd): Collection
    {
        return $user->workouts()
            ->whereDate('workout_date', '>=', $rangeStart->toDateString())
            ->whereDate('workout_date', '<=', $rangeEnd->toDateString())
            ->get();
    }

    /**
     * Formats a Carbon instant as a *timezone-naive* wall-clock string
     * (no "Z"/offset suffix) - deliberately, since this app has no
     * per-user timezone setting and the whole calendar (create dialog,
     * drag, resize) treats every start_at/end_at as "the hour the user
     * typed or dropped it on", not an instant in UTC. app.timezone is
     * UTC, so a suffixed ISO string (toIso8601String()) would make the
     * browser reinterpret and shift it by the browser's own UTC offset
     * on the way back out - this format is parsed by JS's `new Date()`
     * as local time, matching how the frontend already builds these
     * strings from local wall-clock getters (see resources/js/lib/calendar.ts).
     */
    private function naiveDatetime(CarbonInterface $datetime): string
    {
        return $datetime->format('Y-m-d\TH:i:s');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'source' => 'evento',
            'title' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'type' => $event->type,
            'start_at' => $this->naiveDatetime($event->start_at),
            'end_at' => $event->end_at ? $this->naiveDatetime($event->end_at) : null,
            'all_day' => $event->all_day,
            'scheduled' => true,
            'color' => $event->color,
            'href' => null,
        ];
    }

    /**
     * A task without a scheduled_time has no time slot of its own yet - it
     * shows in the calendar's all-day row (same as a promemoria) at the
     * start of its day, draggable from there onto an hour to schedule it
     * for the first time. Once scheduled it gets a 30-minute visual block;
     * tasks don't have a real duration, this is display-only.
     *
     * @return array<string, mixed>
     */
    private function serializeTask(Task $task): array
    {
        $isScheduled = $task->scheduled_time !== null;
        $startAt = $isScheduled
            ? Carbon::parse($task->task_date->toDateString().' '.$task->scheduled_time)
            : $task->task_date->copy()->startOfDay();

        return [
            'id' => $task->id,
            'source' => 'task',
            'title' => $task->title,
            'description' => $task->description,
            'location' => null,
            'type' => null,
            'start_at' => $this->naiveDatetime($startAt),
            'end_at' => $isScheduled ? $this->naiveDatetime($startAt->copy()->addMinutes(30)) : null,
            'all_day' => ! $isScheduled,
            'scheduled' => $isScheduled,
            'color' => null,
            'href' => route('tasks.index', ['date' => $task->task_date->toDateString()]),
        ];
    }

    /**
     * Same "all-day row until dragged onto an hour" treatment as
     * serializeTask() - a workout without scheduled_time shows unscheduled,
     * and gets a 1-hour visual block once it has a time (display-only, a
     * workout has no real duration either).
     *
     * @return array<string, mixed>
     */
    private function serializeWorkout(Workout $workout): array
    {
        $isScheduled = $workout->scheduled_time !== null;
        $startAt = $isScheduled
            ? Carbon::parse($workout->workout_date->toDateString().' '.$workout->scheduled_time)
            : $workout->workout_date->copy()->startOfDay();

        return [
            'id' => $workout->id,
            'source' => 'allenamento',
            'title' => $workout->title ?? 'Allenamento',
            'description' => null,
            'location' => null,
            'type' => null,
            'start_at' => $this->naiveDatetime($startAt),
            'end_at' => $isScheduled ? $this->naiveDatetime($startAt->copy()->addHour()) : null,
            'all_day' => ! $isScheduled,
            'scheduled' => $isScheduled,
            'color' => null,
            'href' => route('workouts.show', $workout),
        ];
    }
}
