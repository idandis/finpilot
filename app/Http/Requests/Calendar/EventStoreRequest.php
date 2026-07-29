<?php

namespace App\Http\Requests\Calendar;

use App\Models\Event;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A "promemoria" is always a single point in time - end_at and all_day
     * are meaningless for it, so they're normalized away here rather than
     * left for the client to remember to omit.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('type') === 'promemoria') {
            $this->merge(['end_at' => null, 'all_day' => false]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Event::TYPES)],
            'start_at' => ['required', 'date'],
            'end_at' => [
                Rule::requiredIf(fn () => $this->input('type') === 'evento' && ! $this->boolean('all_day')),
                'nullable',
                'date',
                'after_or_equal:start_at',
            ],
            'all_day' => ['sometimes', 'boolean'],
            'color' => ['nullable', 'string', 'max:7'],
        ];
    }
}
