<?php

namespace App\Http\Requests\Tasks;

use App\Models\TaskBoard;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    private ?TaskBoard $board = null;

    /**
     * The board the task is being created on, if any - resolved once and
     * reused by both the board and the assignee rule.
     */
    private function board(): ?TaskBoard
    {
        $id = $this->input('task_board_id');

        return $this->board ??= is_numeric($id) ? TaskBoard::query()->find((int) $id) : null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // A task lands either on a user-created board or on a day of
            // the Daily board - the controller ignores task_date once a
            // board is given, so the two are never both applied.
            'task_board_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                if (! $this->board()?->isAccessibleBy($this->user())) {
                    $fail('La board selezionata non è disponibile.');
                }
            }],
            'task_date' => ['nullable', 'date', 'after_or_equal:today'],
            // Assigning is a board thing: a task can only go to someone who
            // works on that board (its owner or one of its members).
            'assigned_to_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $assignee = User::query()->find((int) $value);

                if (! $assignee || ! $this->board()?->isAccessibleBy($assignee)) {
                    $fail('La persona scelta non fa parte di questa board.');
                }
            }],
        ];
    }
}
