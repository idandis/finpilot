<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');
        $ownerId = $transaction->card?->user_id ?? $transaction->financialAccount?->user_id;

        return $ownerId === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('transaction_categories', 'id')->where(
                    fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id)
                ),
            ],
            'description' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
