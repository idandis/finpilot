<?php

namespace App\Http\Requests\Finance;

use App\Models\Investment;
use App\Services\Finance\InvestmentMotivations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentDecisionUpdateRequest extends FormRequest
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
            'motivation_reasons' => ['sometimes', 'nullable', 'array'],
            'motivation_reasons.*' => [Rule::in(InvestmentMotivations::keys())],
            'motivation_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'thesis' => ['sometimes', 'string', 'max:4000'],
            'sell_conditions' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'time_horizon' => ['sometimes', Rule::in(Investment::TIME_HORIZONS)],
            'current_confidence' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'next_review_date' => ['sometimes', 'nullable', 'date'],
            'next_review_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
