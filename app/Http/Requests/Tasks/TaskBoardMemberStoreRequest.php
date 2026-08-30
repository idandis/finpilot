<?php

namespace App\Http\Requests\Tasks;

use App\Models\TaskBoard;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskBoardMemberStoreRequest extends FormRequest
{
    /**
     * Sharing a board is the owner's call alone - members can work on the
     * board's tasks, not decide who else gets in.
     */
    public function authorize(): bool
    {
        return $this->route('taskBoard')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var TaskBoard $board */
        $board = $this->route('taskBoard');

        return [
            'email' => [
                'required',
                'email',
                // Only people already on the platform: there is no invite
                // flow, the board simply appears in their own board strip.
                Rule::exists('users', 'email'),
                Rule::notIn([$board->user->email]),
                function (string $attribute, mixed $value, \Closure $fail) use ($board) {
                    if ($board->members()->where('email', $value)->exists()) {
                        $fail('Questa persona fa già parte della board.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'Nessun utente registrato con questa email.',
            'email.not_in' => 'Questa board è già tua.',
        ];
    }
}
