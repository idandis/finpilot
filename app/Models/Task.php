<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $task_board_id The user-created board this task belongs to, or null when it
 *                                   lives on the "Daily" board - the day-by-day one that was always there. A task
 *                                   has either a board or a task_date, never both.
 * @property int|null $assigned_to_user_id The board member the task is on, if any - boards only,
 *                                         the Daily board being personal has nobody to assign to.
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property Carbon|null $task_date
 * @property string|null $scheduled_time HH:MM:SS - set only when the task has been given a
 *                                       specific time slot on the calendar (see CalendarController); the Task board itself
 *                                       ignores it, tasks are still grouped purely by task_date there.
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['task_board_id', 'assigned_to_user_id', 'title', 'description', 'status', 'task_date', 'scheduled_time', 'position'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    public const STATUSES = ['todo', 'in_progress', 'done'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'task_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<TaskBoard, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(TaskBoard::class, 'task_board_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * A Daily task is the private business of whoever wrote it; a board
     * task belongs to the board, so anyone on that board can work on it -
     * including tasks someone else created.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->task_board_id === null) {
            return $this->user_id === $user->id;
        }

        return $this->board?->isAccessibleBy($user) ?? false;
    }
}
