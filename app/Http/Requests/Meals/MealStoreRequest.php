<?php

namespace App\Http\Requests\Meals;

use App\Models\Meal;
use App\Models\User;
use App\Services\Meals\DishCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An empty category (the placeholder option left selected) is
     * equivalent to none - normalized to null so `nullable` applies.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('category') === '') {
            $this->merge(['category' => null]);
        }
    }

    /**
     * The plan the meal is being added to: the user's own unless
     * plan_user_id points at one shared with them.
     */
    public function planOwner(): User
    {
        $requested = $this->input('plan_user_id');

        if (! is_numeric($requested)) {
            return $this->user();
        }

        $owner = User::query()->find((int) $requested);

        return $owner?->mealPlanIsAccessibleBy($this->user()) ? $owner : $this->user();
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
            'meal_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(Meal::MEAL_TYPES)],
            'category' => ['nullable', Rule::in(DishCategories::keys())],
            // The dish library stays personal even when planning on someone
            // else's plan, so a dish is always looked up among the user's own.
            'dish_id' => ['nullable', Rule::exists('dishes', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id))],
            'plan_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $owner = User::query()->find((int) $value);

                if (! $owner || ! $owner->mealPlanIsAccessibleBy($this->user())) {
                    $fail('La pianificazione scelta non è disponibile.');
                }
            }],
            // Cooking is a plan thing: a meal can only go to someone on the
            // plan it lands on.
            'assigned_to_user_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) {
                $cook = User::query()->find((int) $value);

                if (! $cook || ! $this->planOwner()->mealPlanIsAccessibleBy($cook)) {
                    $fail('La persona scelta non fa parte di questa pianificazione.');
                }
            }],
        ];
    }
}
