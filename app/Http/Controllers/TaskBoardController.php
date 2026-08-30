<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tasks\TaskBoardMemberStoreRequest;
use App\Http\Requests\Tasks\TaskBoardStoreRequest;
use App\Http\Requests\Tasks\TaskBoardUpdateRequest;
use App\Models\Task;
use App\Models\TaskBoard;
use App\Models\User;
use App\Services\Sharing\SharedResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The extra boards a user can add next to the built-in "Daily" one (which
 * has no row here, see TaskController::index()), and the people they are
 * shared with. The boards themselves only ever carry a name - their tasks
 * are managed by TaskController like any other.
 *
 * Everything in this controller is owner-only: members are as powerful as
 * the owner on the board's *contents*, but the board itself - its name, its
 * existence, who else is on it - stays with whoever created it. The one
 * exception is a member removing themselves, i.e. leaving.
 */
class TaskBoardController extends Controller
{
    public function store(TaskBoardStoreRequest $request): RedirectResponse
    {
        $nextPosition = 1 + ($request->user()->taskBoards()->max('position') ?? -1);

        $board = $request->user()->taskBoards()->create([
            'name' => $request->validated('name'),
            'position' => $nextPosition,
        ]);

        return to_route('tasks.index', ['board' => $board->id]);
    }

    public function update(TaskBoardUpdateRequest $request, TaskBoard $taskBoard): RedirectResponse
    {
        $previousName = $taskBoard->name;

        $taskBoard->update($request->validated());

        if ($taskBoard->name !== $previousName) {
            SharedResource::forTaskBoard($taskBoard)
                ->announce($request->user(), "ha rinominato la board «{$previousName}» in «{$taskBoard->name}»");
        }

        return back();
    }

    /**
     * Shares the board with another user of the platform, found by the email
     * they signed up with (an unknown email is a validation error, not an
     * invite: there is nobody to invite yet). From that moment the board
     * shows up in their own board strip, with the same powers over its
     * tasks as the owner.
     */
    public function storeMember(TaskBoardMemberStoreRequest $request, TaskBoard $taskBoard): RedirectResponse
    {
        $member = User::query()->where('email', $request->validated('email'))->sole();

        $taskBoard->members()->syncWithoutDetaching([$member->id]);

        $taskBoard->load('members');

        SharedResource::forTaskBoard($taskBoard)->invite($member, $request->user());

        return back();
    }

    /**
     * Removes someone from the board - the owner removing a member, or a
     * member leaving on their own. Whatever they were assigned to stays on
     * the board, simply unassigned: the work didn't leave with them.
     */
    public function destroyMember(Request $request, TaskBoard $taskBoard, User $user): RedirectResponse
    {
        $isOwner = $taskBoard->user_id === $request->user()->id;
        $isLeaving = $user->id === $request->user()->id;

        abort_unless($isOwner || $isLeaving, 403);
        abort_if($user->id === $taskBoard->user_id, 403, 'Il proprietario non può essere rimosso dalla board.');

        $taskBoard->members()->detach($user->id);

        Task::query()
            ->where('task_board_id', $taskBoard->id)
            ->where('assigned_to_user_id', $user->id)
            ->update(['assigned_to_user_id' => null]);

        return $isLeaving ? to_route('tasks.index') : back();
    }

    /**
     * Deleting a board deletes its tasks with it (cascade) - they only ever
     * existed inside it, there is no other place they could go.
     */
    public function destroy(Request $request, TaskBoard $taskBoard): RedirectResponse
    {
        abort_unless($taskBoard->user_id === $request->user()->id, 403);

        // Read while the board is still there: once deleted there is
        // nobody left to tell.
        $resource = SharedResource::forTaskBoard($taskBoard);

        $taskBoard->delete();

        $resource->announce($request->user(), "ha eliminato la board «{$resource->name}»");

        return to_route('tasks.index');
    }
}
