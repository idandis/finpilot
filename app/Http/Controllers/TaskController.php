<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\TaskAssignRequest;
use App\Http\Requests\Tasks\TaskMoveRequest;
use App\Http\Requests\Tasks\TaskRescheduleRequest;
use App\Http\Requests\Tasks\TaskStoreRequest;
use App\Http\Requests\Tasks\TaskUpdateRequest;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use App\Services\Sharing\SharedResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * The board's columns as they read on screen - only needed to say
     * where a task was dragged to (see move()).
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'todo' => 'Da fare',
        'in_progress' => 'In corso',
        'done' => 'Fatto',
    ];

    /**
     * Two kinds of board live on this page.
     *
     * "Daily" (no ?board=) is the original one: it defaults to today, but
     * any other day can be requested via ?date= - earlier days to browse
     * history, later days to see/manage tasks already planned ahead. Once a
     * day is over its tasks stay wherever they were left (nothing carries
     * over, nothing is cleared): past days are shown read-only (see
     * move()/destroy()) so that history stays a faithful record, while
     * today and future days stay editable.
     *
     * A user-created board (?board=<id>, see TaskBoardController) is a plain
     * kanban list instead: no day at all, so no date navigation and no
     * read-only rule - its tasks sit there until they're moved or deleted.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = Carbon::today();
        $date = $this->resolveDate($request->query('date'), $today);
        $board = $this->resolveBoard($user, $request->query('board'));

        // A board's tasks belong to the board, not to whoever created
        // them, so on a shared board everyone sees the same cards.
        $tasks = Task::query()
            ->when(
                $board,
                fn ($query) => $query->where('task_board_id', $board->id),
                fn ($query) => $query->where('user_id', $user->id)->whereNull('task_board_id')->whereDate('task_date', $date),
            )
            ->with('assignee:id,name')
            ->orderBy('position')
            ->get(['id', 'title', 'description', 'status', 'position', 'assigned_to_user_id'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'position' => $task->position,
                'assignee' => $task->assignee?->only(['id', 'name']),
            ]);

        return Inertia::render('Tasks/Index', [
            'date' => $date->toDateString(),
            'today' => $today->toDateString(),
            'boards' => $this->accessibleBoards($user),
            'board' => $board ? $this->serializeBoard($board, $user) : null,
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
     * Null means the Daily board, which is also where an unknown id or a
     * board the user has no access to lands - same forgiving treatment as
     * resolveDate(), since ?board= only ever comes from the board strip
     * (and from a board that may have just been deleted, or unshared, in
     * another tab).
     */
    private function resolveBoard(User $user, mixed $requested): ?TaskBoard
    {
        if (! is_numeric($requested)) {
            return null;
        }

        $board = TaskBoard::query()->find((int) $requested);

        return $board?->isAccessibleBy($user) ? $board : null;
    }

    /**
     * The board strip: the ones the user owns first, then the ones shared
     * with them.
     *
     * @return Collection<int, array{id: int, name: string, is_shared: bool}>
     */
    private function accessibleBoards(User $user): Collection
    {
        return $user->taskBoards()->orderBy('position')->get()
            ->concat($user->sharedTaskBoards()->orderBy('name')->get())
            ->map(fn (TaskBoard $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'is_shared' => $board->user_id !== $user->id,
            ]);
    }

    /**
     * The selected board, with everyone working on it - the list behind
     * both the sharing panel and the assignee picker.
     *
     * @return array<string, mixed>
     */
    private function serializeBoard(TaskBoard $board, User $user): array
    {
        $board->load(['user:id,name,email', 'members:id,name,email']);

        return [
            'id' => $board->id,
            'name' => $board->name,
            'is_owner' => $board->user_id === $user->id,
            'people' => $board->people()->map(fn (User $person) => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'is_owner' => $person->id === $board->user_id,
            ])->all(),
        ];
    }

    /**
     * New tasks always start in the "todo" column, appended to its end -
     * either on the day the Daily board is currently showing (today or a
     * future day planned ahead of time, past days being read-only, see
     * move()/destroy()) or, when a board is given, inside that board and
     * with no day at all.
     */
    public function store(TaskStoreRequest $request): RedirectResponse
    {
        $boardId = $request->validated('task_board_id');

        $taskDate = $boardId
            ? null
            : ($request->validated('task_date')
                ? Carbon::parse($request->validated('task_date'))->startOfDay()
                : Carbon::today());

        $nextPosition = 1 + (Task::query()
            ->when(
                $boardId,
                fn ($query) => $query->where('task_board_id', $boardId),
                fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('task_board_id')->whereDate('task_date', $taskDate),
            )
            ->where('status', 'todo')
            ->max('position') ?? -1);

        $task = $request->user()->tasks()->create([
            'task_board_id' => $boardId,
            'assigned_to_user_id' => $boardId ? $request->validated('assigned_to_user_id') : null,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
            'status' => 'todo',
            'task_date' => $taskDate,
            'position' => $nextPosition,
        ]);

        $this->announce($task, $request->user(), "ha aggiunto il task «{$task->title}»");

        return back();
    }

    /**
     * Only tasks on the Daily board can go read-only, and only once their
     * day is over: a task on a user-created board has no day to be past.
     */
    private function isReadOnly(Task $task): bool
    {
        return $task->task_date !== null && $task->task_date->lt(Carbon::today());
    }

    /**
     * Tells the rest of the board what just happened to one of its tasks.
     * The Daily board is personal - nobody else is there to tell - so a
     * task without a board is silently skipped.
     */
    private function announce(Task $task, User $actor, string $action): void
    {
        if ($task->task_board_id === null) {
            return;
        }

        $board = $task->board;

        if ($board) {
            SharedResource::forTaskBoard($board)->announce($actor, $action);
        }
    }

    /**
     * Editing a task's own content (title/description) - same "past days
     * are read-only" rule as move()/destroy(), since rewriting what a task
     * said after its day is over would break the historical record.
     */
    public function update(TaskUpdateRequest $request, Task $task): RedirectResponse
    {
        abort_if($this->isReadOnly($task), 403, 'Non è possibile modificare i task dei giorni passati.');

        $task->update($request->validated());

        $this->announce($task, $request->user(), "ha modificato il task «{$task->title}»");

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
     *
     * Days are a Daily-board concept, so a task sitting on a user-created
     * board has nothing to be rescheduled to (the UI hides the action).
     */
    public function reschedule(TaskRescheduleRequest $request, Task $task): RedirectResponse
    {
        abort_unless($task->task_board_id === null, 403, 'I task di una board non hanno una data.');

        $newDate = Carbon::parse($request->validated('task_date'))->startOfDay();

        $nextPosition = 1 + (Task::query()
            ->where('user_id', $task->user_id)
            ->whereNull('task_board_id')
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
     * dense, gapless sequence. A task never leaves its board (or its day)
     * this way - only its column and its place in it change.
     */
    public function move(TaskMoveRequest $request, Task $task): RedirectResponse
    {
        abort_if($this->isReadOnly($task), 403, 'Non è possibile modificare i task dei giorni passati.');

        $status = $request->validated('status');
        $position = $request->validated('position');

        $columnTasks = Task::query()
            ->when(
                $task->task_board_id,
                fn ($query) => $query->where('task_board_id', $task->task_board_id),
                fn ($query) => $query->where('user_id', $task->user_id)->whereNull('task_board_id')->whereDate('task_date', $task->task_date),
            )
            ->where('status', $status)
            ->where('id', '!=', $task->id)
            ->orderBy('position')
            ->get();

        $columnTasks->splice(min($position, $columnTasks->count()), 0, [$task]);

        $previousStatus = $task->status;

        foreach ($columnTasks->values() as $index => $columnTask) {
            if ($columnTask->status !== $status || $columnTask->position !== $index) {
                $columnTask->update(['status' => $status, 'position' => $index]);
            }
        }

        // Reordering inside a column is nobody else's business; changing
        // column is.
        if ($status !== $previousStatus) {
            $this->announce($task, $request->user(), $status === 'done'
                ? "ha completato il task «{$task->title}»"
                : "ha spostato il task «{$task->title}» in ".self::STATUS_LABELS[$status]);
        }

        return back();
    }

    /**
     * Assigns (or changes) the task's calendar time slot - dragged from the
     * Calendario page, not the Task board itself (which never shows or
     * needs scheduled_time). Same past-day rule as move()/destroy(): a task
     * already on a past day can't be touched, and it can't be dragged onto
     * one either. Only Daily tasks reach here at all: the calendar is built
     * out of days, and a user-created board's tasks have none.
     */
    public function schedule(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->isAccessibleBy($request->user()), 403);
        abort_unless($task->task_board_id === null, 403, 'I task di una board non hanno una data.');
        abort_if($this->isReadOnly($task), 403, 'Non è possibile modificare i task dei giorni passati.');

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

    /**
     * Puts the task on one of the board's people (or on nobody, with a null
     * id) - the picker on the card. Boards only: the Daily board is
     * personal, there is nobody else there to hand a task to.
     */
    public function assign(TaskAssignRequest $request, Task $task): RedirectResponse
    {
        abort_if($task->task_board_id === null, 403, 'I task della Daily non si assegnano.');

        $task->update(['assigned_to_user_id' => $request->validated('assigned_to_user_id')]);

        $assignee = $task->assigned_to_user_id ? User::query()->find($task->assigned_to_user_id) : null;
        $board = $task->board;

        if ($board) {
            $resource = SharedResource::forTaskBoard($board);

            if ($assignee) {
                // The person taking it on hears it addressed to them,
                // everyone else hears who it went to.
                $resource->tell([$assignee], $request->user(), "ti ha assegnato il task «{$task->title}»");
                $resource->tell(
                    $resource->people->reject(fn (User $person) => $person->id === $assignee->id),
                    $request->user(),
                    "ha assegnato il task «{$task->title}» a {$assignee->name}",
                );
            } else {
                $resource->announce($request->user(), "ha tolto l'assegnatario dal task «{$task->title}»");
            }
        }

        return back();
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->isAccessibleBy($request->user()), 403);
        abort_if($this->isReadOnly($task), 403, 'Non è possibile modificare i task dei giorni passati.');

        $task->delete();

        $this->announce($task, $request->user(), "ha eliminato il task «{$task->title}»");

        return back();
    }
}
