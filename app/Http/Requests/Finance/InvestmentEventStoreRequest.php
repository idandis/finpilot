<?php

namespace App\Http\Requests\Finance;

use App\Models\InvestmentEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvestmentEventStoreRequest extends FormRequest
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
            'event_type' => ['required', Rule::in(InvestmentEvent::EVENT_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'metrics_raw' => ['nullable', 'string', 'max:4000'],
            'summary' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * Parses the "Etichetta: valore" textarea (one metric per line) into the
     * {label, value} pairs stored in InvestmentEvent::$metrics - kept free-form
     * rather than fixed columns, since which metrics matter (revenue, EPS, a
     * company-specific segment...) varies per company. A line without a colon
     * is kept as a label-only entry rather than silently dropped.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function parsedMetrics(): array
    {
        $raw = $this->validated('metrics_raw');

        if (! $raw) {
            return [];
        }

        return collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->map(function (string $line) {
                [$label, $value] = array_pad(explode(':', $line, 2), 2, '');

                return ['label' => trim($label), 'value' => trim($value)];
            })
            ->values()
            ->all();
    }
}
