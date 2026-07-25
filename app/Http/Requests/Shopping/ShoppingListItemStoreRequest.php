<?php

namespace App\Http\Requests\Shopping;

use App\Services\Shopping\GroceryCategories;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShoppingListItemStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The item is
     * created under the list given by the route - only its owner may add
     * products to it.
     */
    public function authorize(): bool
    {
        return $this->route('shoppingList')->user_id === $this->user()->id;
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
            'category' => ['required', Rule::in(GroceryCategories::keys())],
        ];
    }
}
