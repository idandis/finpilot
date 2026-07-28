<?php

namespace App\Http\Requests\Workouts;

use App\Services\Workouts\ExerciseCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciseStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An unchecked checkbox simply isn't submitted at all, so it's
     * normalized to false rather than left missing.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['requires_equipment' => $this->boolean('requires_equipment')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(ExerciseCategories::keys())],
            'requires_equipment' => ['required', 'boolean'],
        ];
    }
}
