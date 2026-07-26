<?php

namespace App\Http\Requests\Meals;

use App\Services\Meals\DishCategories;
use App\Services\Shopping\GroceryCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DishUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('dish')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Ingredient rows are left lenient (nullable), same as on creation: a
     * blank row abandoned mid-edit shouldn't block saving - the controller
     * filters out any row without a name before persisting, and replaces
     * the dish's full ingredient list with whatever remains.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', Rule::in(DishCategories::keys())],
            'ingredients' => ['nullable', 'array'],
            'ingredients.*.name' => ['nullable', 'string', 'max:255'],
            'ingredients.*.category' => ['nullable', Rule::in(GroceryCategories::keys())],
        ];
    }
}
