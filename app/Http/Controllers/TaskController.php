<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\TaskMoveRequest;
use App\Http\Requests\Tasks\TaskRescheduleRequest;
use App\Http\Requests\Tasks\TaskStoreRequest;
use App\Http\Requests\Tasks\TaskUpdateRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The board defaults to today, but any other day can be requested via
     * ?date= - earlier days to browse history, later days to see/manage
     * tasks already planned ahead. Once a day is over its tasks stay
     * wherever they were left (nothing carries over, nothing is cleared):
     * past days are shown read-only (see move()/destroy()) so that history
     * stays a faithful record, while today and future days stay editable.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $date = $this->resolveDate($request->query('date'), $today);

        $tasks = $request->user()->tasks()
            ->whereDate('task_date', $date)
            ->orderBy('position')
            ->get(['id', 'title', 'description', 'status', 'position']);

        return Inertia::render('Tasks/Index', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'tasks' => $tasks,
        ]);
    }

    /**
     * Falls back to today on a missing or malformed date rather than
     * erroring - this is only ever reached from the prev/next/"back to
     * today" links, not a form a user fills in.
     */
    private function resolveDate(?string $requested, Carbon $today): Carbon
    {
        if ($requested === null) {
            return $today;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
        } catch (\Throwable) {
            return $today;
        }
    }

    /**
     * New tasks always start in the "todo" column, appended to its end, on
     * the day the board is currently showing (today or a future day planned
     * ahead of time - past days are read-only, see move()/destroy()).
     */
    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $taskDate = $request->validated('task_date')
            ? Carbon::parse($request->validated('task_date'))->startOfDay()
            : Carbon::today();

        $nextPosition = 1 + ($request->user()->tasks()
            ->whereDate('task_date', $taskDate)
            ->where('status', 'todo')
            ->max('position') ?? -1);

        $request->user()->tasks()->create([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => 'todo',
            'task_date' => $taskDate,
            'position' => $nextPosition,
        ]);

        return back();
    }

    /**
     * Editing a task's own content (title/description) - same "past days
     * are read-only" rule as move()/destroy(), since rewriting what a task
     * said after its day is over would break the historical record.
     */
    public function update(TaskUpdateRequest $request, Task $task): RedirectResponse
    {
        abort_unless(! $task->task_date->lt(Carbon::today()), 403, 'Non è possibile modificare i task dei giorni passati.');

        $task->update($request->validated());

        return back();
    }

    /**
     * Moves a task to a day of the user's choosing (the picker defaults to
     * tomorrow client-side) - the one action allowed even on an otherwise
     * read-only past day, since it doesn't rewrite what happened, it just
     * moves the still-open item to where it'll actually get done. Always
     * appended to the end of its status column on the new day, same
     * positioning convention as store()/move(). Can't target a past day,
     * keeping the "past days are read-only" invariant intact.
     */
    public function reschedule(TaskRescheduleRequest $request, Task $task): RedirectResponse
    {
        $newDate = Carbon::parse($request->validated('task_date'))->startOfDay();

        $nextPosition = 1 + (Task::query()
            ->where('user_id', $task->user_id)
            ->whereDate('task_date', $newDate)
            ->where('status', $task->status)
            ->max('position') ?? -1);

        $task->update(['task_date' => $newDate, 'position' => $nextPosition]);

        return back();
    }

    /**
     * Drag-and-drop, both between columns and within the same column: the
     * task is inserted at the given index of the target column and every
     * other task in that column is reindexed around it so positions stay a
     * dense, gapless sequence.
     */
    public function move(TaskMoveRequest $request, Task $task): RedirectResponse
    {
        abort_unless(! $task->task_date->lt(Carbon::today()), 403, 'Non è possibile modificare i task dei giorni passati.');

        $status = $request->validated('status');
        $position = $request->validated('position');

        $columnTasks = Task::query()
            ->where('user_id', $task->user_id)
            ->whereDate('task_date', $task->task_date)
            ->where('status', $status)
            ->where('id', '!=', $task->id)
            ->orderBy('position')
            ->get();

        $columnTasks->splice(min($position, $columnTasks->count()), 0, [$task]);

        foreach ($columnTasks->values() as $index => $columnTask) {
            if ($columnTask->status !== $status || $columnTask->position !== $index) {
                $columnTask->update(['status' => $status, 'position' => $index]);
            }
        }

        return back();
    }

    /**
     * Assigns (or changes) the task's calendar time slot - dragged from the
     * Calendario page, not the Task board itself (which never shows or
     * needs scheduled_time). Same past-day rule as move()/destroy(): a task
     * already on a past day can't be touched, and it can't be dragged onto
     * one either.
     */
    public function schedule(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        abort_unless(! $task->task_date->lt(Carbon::today()), 403, 'Non è possibile modificare i task dei giorni passati.');

        $validated = $request->validate([
            'task_date' => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time' => ['required', 'date_format:H:i'],
        ]);

        $task->update([
            'task_date' => $validated['task_date'],
            'scheduled_time' => Carbon::createFromFormat('H:i', $validated['scheduled_time'])->format('H:i:s'),
        ]);

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->user_id === $request->user()->id, 403);
        abort_unless(! $task->task_date->lt(Carbon::today()), 403, 'Non è possibile modificare i task dei giorni passati.');

        $task->delete();

        return back();
    }
}
