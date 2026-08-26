<?php

namespace App\Http\Requests\BalanceSheet;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AssetUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('asset')->user_id === $this->user()->id;
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
            'category' => ['nullable', 'string', 'max:255'],
            'asset_value' => ['required', 'numeric', 'min:0'],
            'monthly_income' => ['nullable', 'numeric', 'min:0'],
            'cash_used' => ['nullable', 'numeric', 'min:0'],
            'mortgage_total' => ['nullable', 'numeric', 'min:0'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0'],
            'hours_per_month' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
