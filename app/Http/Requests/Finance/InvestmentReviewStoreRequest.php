<?php

namespace App\Http\Requests\Finance;

use App\Models\InvestmentReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentReviewStoreRequest extends FormRequest
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
            'investment_event_id' => [
                'nullable',
                'integer',
                Rule::exists('investment_events', 'id')->where('investment_id', $this->route('investment')->id),
            ],
            'review_date' => ['required', 'date'],
            'decision' => ['required', Rule::in(InvestmentReview::DECISIONS)],
            'thesis_still_valid' => ['nullable', 'boolean'],
            'score_after' => ['required', 'integer', 'min:1', 'max:10'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
