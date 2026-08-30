<?php

namespace App\Http\Requests\Meals;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MealPlanMemberStoreRequest extends FormRequest
{
    /**
     * A user always shares their own plan - there is no id to check, and
     * sharing someone else's plan further is simply not a thing.
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
        return [
            'email' => [
                'required',
                'email',
                // Only people already on the platform: there is no invite
                // flow, the plan simply appears in their own switcher.
                Rule::exists('users', 'email'),
                Rule::notIn([$this->user()->email]),
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($this->user()->mealPlanMembers()->where('email', $value)->exists()) {
                        $fail('Questa persona vede già la tua pianificazione.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.exists' => 'Nessun utente registrato con questa email.',
            'email.not_in' => 'Questa pianificazione è già tua.',
        ];
    }
}
