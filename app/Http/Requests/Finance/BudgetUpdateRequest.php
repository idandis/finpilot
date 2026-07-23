<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. A budget can
     * be set against any category visible to the user: a shared system
     * category, or one of their own.
     */
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category->user_id === null || $category->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'monthly_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'card_id' => [
                'sometimes',
                'nullable',
                Rule::exists('cards', 'id')->where(
                    fn ($query) => $query->whereIn('financial_account_id', $this->user()->financialAccounts()->pluck('id'))
                ),
            ],
        ];
    }
}
