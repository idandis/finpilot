<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InvestmentLinkAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('investment')->user_id === $this->user()->id;
    }

    /**
     * Either an existing analysis id (already owned by the user, checked in
     * the controller) or a brand new symbol/name pair to create one on the
     * fly - never both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_analysis_id' => ['required_without:symbol', 'nullable', 'integer'],
            'symbol' => ['required_without:company_analysis_id', 'nullable', 'string', 'max:20', 'regex:/^[A-Z0-9.]+$/i'],
            'name' => ['required_with:symbol', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('company_analysis_id') && $this->filled('symbol')) {
                $validator->errors()->add('symbol', 'Specificare un\'analisi esistente oppure un nuovo simbolo, non entrambi.');
            }
        });
    }
}
