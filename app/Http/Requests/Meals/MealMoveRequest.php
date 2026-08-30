<?php

namespace App\Http\Requests\Meals;

use App\Models\Meal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealMoveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('meal')->user->mealPlanIsAccessibleBy($this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meal_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(Meal::MEAL_TYPES)],
        ];
    }
}
