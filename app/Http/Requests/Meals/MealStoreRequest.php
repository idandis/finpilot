<?php

namespace App\Http\Requests\Meals;

use App\Models\Meal;
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
        ];
    }
}
