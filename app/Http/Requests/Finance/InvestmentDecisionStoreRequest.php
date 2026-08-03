<?php

namespace App\Http\Requests\Finance;

use App\Models\Investment;
use App\Services\Finance\InvestmentMotivations;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentDecisionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'motivation_reasons' => ['nullable', 'array'],
            'motivation_reasons.*' => [Rule::in(InvestmentMotivations::keys())],
            'motivation_note' => ['nullable', 'string', 'max:2000'],
            'thesis' => ['required', 'string', 'max:4000'],
            'sell_conditions' => ['nullable', 'string', 'max:2000'],
            'time_horizon' => ['required', Rule::in(Investment::TIME_HORIZONS)],
            'initial_confidence' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    /**
     * The ISIN itself is a route parameter, not request input, so its
     * uniqueness per user can't be expressed as an input validation rule -
     * checked here instead, against the same (user_id, isin) pair the
     * "investments" table's unique index enforces.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $exists = Investment::query()
                ->where('user_id', $this->user()->id)
                ->where('isin', $this->route('isin'))
                ->exists();

            if ($exists) {
                $validator->errors()->add('isin', __('A Decision Journal already exists for this instrument.'));
            }
        });
    }
}
