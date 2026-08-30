<?php

namespace App\Http\Requests\Meals;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MealAssignRequest extends FormRequest
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
            // Null means nobody is cooking it yet. Anyone else has to be
            // someone on the meal's own plan.
            'assigned_to_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $cook = User::query()->find((int) $value);

                if (! $cook || ! $this->route('meal')->user->mealPlanIsAccessibleBy($cook)) {
                    $fail('La persona scelta non fa parte di questa pianificazione.');
                }
            }],
        ];
    }
}
