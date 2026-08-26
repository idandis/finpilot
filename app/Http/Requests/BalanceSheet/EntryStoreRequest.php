<?php

namespace App\Http\Requests\BalanceSheet;

use App\Models\BalanceSheetEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EntryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->route('type');
        $isTime = $type === 'time';
        $isGoal = $type === 'goal';

        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'amount' => [$isGoal ? 'nullable' : 'required', 'numeric', 'min:0'],
            'frequency' => ['nullable', Rule::in(BalanceSheetEntry::FREQUENCIES)],
            'hours_per_month' => [$isTime ? 'required' : 'nullable', 'numeric', 'min:0'],
            'time_kind' => [$isTime ? 'required' : 'nullable', Rule::in(BalanceSheetEntry::TIME_KINDS)],
            'progress_percent' => [$isGoal ? 'required' : 'nullable', 'integer', 'min:0', 'max:100'],
            'linked_asset_id' => [
                'nullable',
                Rule::exists('balance_sheet_entries', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)->where('type', 'asset')
                ),
            ],
            'linked_liability_id' => [
                'nullable',
                Rule::exists('balance_sheet_entries', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)->where('type', 'liability')
                ),
            ],
        ];
    }
}
