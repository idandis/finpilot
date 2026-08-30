<?php

namespace App\Http\Requests\Meals;

use App\Models\User;
use App\Services\Meals\DishCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('meal')->user->mealPlanIsAccessibleBy($this->user());
    }

    /**
     * An empty category (the "Nessuna categoria" option) is equivalent to
     * none - normalized to null so `nullable` applies.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('category') === '') {
            $this->merge(['category' => null]);
        }
    }

    /**
     * Editing a meal only touches what it is (title, description,
     * category) and who cooks it - which day and slot it sits in stays
     * drag-and-drop's business, see MealMoveRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', Rule::in(DishCategories::keys())],
            // Null means nobody is cooking it. Anyone else has to be
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
