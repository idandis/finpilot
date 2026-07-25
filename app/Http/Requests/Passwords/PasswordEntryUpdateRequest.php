<?php

namespace App\Http\Requests\Passwords;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PasswordEntryUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('passwordEntry')->group->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request. The password is
     * optional on update - resubmitting the whole entry just to change the
     * platform name or username shouldn't force re-entering the password.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform_name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'string', 'max:1000'],
        ];
    }
}
