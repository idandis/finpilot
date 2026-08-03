<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentJournalEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('investment')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:2000'],
            'occurred_at' => ['required', 'date'],
            'transaction_id' => [
                'nullable',
                'integer',
                Rule::exists('transactions', 'id')->where('isin', $this->route('investment')->isin),
            ],
        ];
    }
}
