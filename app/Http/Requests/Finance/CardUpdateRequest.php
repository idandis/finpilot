<?php

namespace App\Http\Requests\Finance;

use App\Models\Card;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('card')->user_id === $this->user()->id;
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
            'type' => ['required', 'string', Rule::in(Card::TYPES)],
            'last_four_digits' => ['nullable', 'digits:4'],
            'circuit' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
            'icon' => ['nullable', 'string', Rule::in(Card::ICONS)],
            'owner_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/'],
            'financial_account_id' => [
                'nullable',
                Rule::exists('financial_accounts', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
