<?php

namespace App\Models;

use Database\Factories\TaskBoardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A user-created task board: a plain kanban list with the same three
 * columns as the Daily board but no calendar day attached, so its tasks
 * stay put until they're moved or deleted. The Daily board itself is not a
 * row here (see the add_task_board_to_tasks migration).
 *
 * A board can be shared with other users of the platform: everyone on it
 * works on the same tasks and can be assigned them, while managing the
 * board itself (renaming, deleting, inviting) stays with the owner.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'position'])]
class TaskBoard extends Model
{
    /** @use HasFactory<TaskBoardFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The people the owner invited - the owner themselves is not one of
     * them, see members()/people().
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_board_members')->withTimestamps();
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Everyone who works on this board, owner first - the list behind both
     * the "condivisa con" panel and the assignee picker.
     *
     * @return Collection<int, User>
     */
    public function people(): Collection
    {
        return collect([$this->user])->concat($this->members)->values();
    }

    /**
     * Owner or invited member: the single check behind every board and task
     * action, since members are deliberately as powerful as the owner on
     * the board's contents (only the board itself is owner-only).
     */
    public function isAccessibleBy(User $user): bool
    {
        return $this->user_id === $user->id
            || $this->members()->whereKey($user->id)->exists();
    }
}
