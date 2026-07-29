<?php

namespace App\Http\Requests\Life;

use App\Services\Life\Moods;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MemoryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('memory')->user_id === $this->user()->id;
    }

    /**
     * An empty mood (the placeholder option left selected) is equivalent to
     * none - normalized to null so `nullable` applies.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('mood') === '') {
            $this->merge(['mood' => null]);
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
            'memory_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'people' => ['nullable', 'string', 'max:255'],
            'mood' => ['nullable', Rule::in(Moods::keys())],
            'photo' => ['nullable', 'image', 'max:5120'],
        ];
    }
}
