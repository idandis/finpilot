<?php

namespace App\Http\Requests\Finance;

use App\Services\Finance\BuffettQuestions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyAnalysisUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('companyAnalysis')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_price' => ['sometimes', 'nullable', 'numeric'],
            'revenue_growth' => ['sometimes', 'nullable', 'numeric'],
            'eps_growth' => ['sometimes', 'nullable', 'numeric'],
            'revenue_cagr_5y' => ['sometimes', 'nullable', 'numeric'],
            'eps_cagr_5y' => ['sometimes', 'nullable', 'numeric'],
            'free_cash_flow' => ['sometimes', 'nullable', 'numeric'],
            'fcf_margin' => ['sometimes', 'nullable', 'numeric'],
            'operating_margin' => ['sometimes', 'nullable', 'numeric'],
            'net_margin' => ['sometimes', 'nullable', 'numeric'],
            'gross_margin' => ['sometimes', 'nullable', 'numeric'],
            'roe' => ['sometimes', 'nullable', 'numeric'],
            'roic' => ['sometimes', 'nullable', 'numeric'],
            'debt_to_ebitda' => ['sometimes', 'nullable', 'numeric'],
            'interest_coverage' => ['sometimes', 'nullable', 'numeric'],
            'current_ratio' => ['sometimes', 'nullable', 'numeric'],
            'pe_ratio' => ['sometimes', 'nullable', 'numeric'],
            'ev_to_ebitda' => ['sometimes', 'nullable', 'numeric'],
            'ev_to_fcf' => ['sometimes', 'nullable', 'numeric'],
            'price_to_sales' => ['sometimes', 'nullable', 'numeric'],
            'peg_ratio' => ['sometimes', 'nullable', 'numeric'],
            'fcf_yield' => ['sometimes', 'nullable', 'numeric'],
            'fair_value' => ['sometimes', 'nullable', 'numeric'],
            'historical_comparison' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'competitor_comparison' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'indicators_currency' => ['sometimes', 'nullable', 'string', 'max:3'],
            'buffett_answers' => ['sometimes', 'array'],
            'buffett_answers.*.key' => ['required_with:buffett_answers', Rule::in(BuffettQuestions::keys())],
            'buffett_answers.*.answer' => ['nullable', 'boolean'],
            'buffett_answers.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
