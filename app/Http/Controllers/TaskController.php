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
     * The board defaults to today, but an earlier day can be requested via
     * ?date= to browse history - never a future day. Once a day is over its
     * tasks stay wherever they were left (nothing carries over, nothing is
     * cleared): tomorrow's board is simply empty because no task has
     * tomorrow's task_date yet, and past days are shown read-only (see
     * move()/destroy()) so that history stays a faithful record.
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
     * Falls back to today on a missing, malformed, or future date rather
     * than erroring - this is only ever reached from the prev/next/"back to
     * today" links, not a form a user fills in.
     */
    private function resolveDate(?string $requested, Carbon $today): Carbon
    {
        if ($requested === null) {
            return $today;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $requested)->startOfDay();
        } catch (\Throwable) {
            return $today;
        }

        return $date->gt($today) ? $today : $date;
    }

    /**
     * New tasks always start in the "todo" column, appended to its end.
     */
    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $today = Carbon::today();

        $nextPosition = 1 + ($request->user()->tasks()
            ->whereDate('task_date', $today)
            ->where('status', 'todo')
            ->max('position') ?? -1);

        $request->user()->tasks()->create([
            ...$request->validated(),
            'status' => 'todo',
            'task_date' => $today,
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
        abort_unless($task->task_date->isToday(), 403, 'Non è possibile modificare i task dei giorni passati.');

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
        abort_unless($task->task_date->isToday(), 403, 'Non è possibile modificare i task dei giorni passati.');

        $task->delete();

        return back();
    }
}
