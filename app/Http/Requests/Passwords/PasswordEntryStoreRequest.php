<?php

namespace App\Http\Requests\Passwords;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordEntryStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The entry
     * is created under the group given by the route - only its owner may
     * add accounts to it.
     */
    public function authorize(): bool
    {
        return $this->route('passwordGroup')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1000'],
        ];
    }
}
