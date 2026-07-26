<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\TaskMoveRequest;
use App\Http\Requests\Tasks\TaskStoreRequest;
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
     * Drag-and-drop between columns: the task is always appended to the end
     * of the target column (no fine-grained reordering within a column).
     */
    public function move(TaskMoveRequest $request, Task $task): RedirectResponse
    {
        abort_unless(! $task->task_date->lt(Carbon::today()), 403, 'Non è possibile modificare i task dei giorni passati.');

        $status = $request->validated('status');

        $nextPosition = 1 + (Task::query()
            ->where('user_id', $task->user_id)
            ->whereDate('task_date', $task->task_date)
            ->where('status', $status)
            ->max('position') ?? -1);

        $task->update(['status' => $status, 'position' => $nextPosition]);

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
