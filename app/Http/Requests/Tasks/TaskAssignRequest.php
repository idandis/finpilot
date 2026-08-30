<?php

namespace App\Http\Requests\Tasks;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaskAssignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('task')->isAccessibleBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Null unassigns. Anyone else has to be someone who actually
            // works on the task's board.
            'assigned_to_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $assignee = User::query()->find((int) $value);

                if (! $assignee || ! $this->route('task')->board?->isAccessibleBy($assignee)) {
                    $fail('La persona scelta non fa parte di questa board.');
                }
            }],
        ];
    }
}
