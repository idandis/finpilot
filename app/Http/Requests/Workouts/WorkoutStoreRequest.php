<?php

namespace App\Http\Requests\Workouts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkoutStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Used both to create a brand new day's workout and to add more
     * exercises to one that already exists, so at least one exercise row is
     * always required.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'workout_date' => ['required', 'date'],
            'title' => ['nullable', 'string', 'max:255'],
            'exercises' => ['required', 'array', 'min:1'],
            'exercises.*.exercise_id' => [
                'required',
                Rule::exists('exercises', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
            ],
            'exercises.*.sets_count' => ['required', 'integer', 'min:1', 'max:20'],
            'exercises.*.reps_count' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
