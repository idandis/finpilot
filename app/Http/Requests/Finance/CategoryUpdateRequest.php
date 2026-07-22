<?php

namespace App\Http\Requests\Finance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. System
     * categories (shared, user_id null) are never editable by a user.
     */
    public function authorize(): bool
    {
        return $this->route('category')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('transaction_categories', 'name')
                    ->ignore($this->route('category'))
                    ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', $this->user()->id)),
            ],
            'color' => ['sometimes', 'nullable', 'string', 'max:7'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
