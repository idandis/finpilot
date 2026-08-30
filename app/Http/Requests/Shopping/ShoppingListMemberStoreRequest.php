<?php

namespace App\Http\Requests\Shopping;

use App\Models\ShoppingList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShoppingListMemberStoreRequest extends FormRequest
{
    /**
     * Sharing a list is the owner's call alone - members can add and tick
     * off products, not decide who else gets in.
     */
    public function authorize(): bool
    {
        return $this->route('shoppingList')->user_id === $this->user()->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ShoppingList $list */
        $list = $this->route('shoppingList');

        return [
            'email' => [
                'required',
                'email',
                // Only people already on the platform: there is no invite
                // flow, the list simply appears among their own.
                Rule::exists('users', 'email'),
                Rule::notIn([$list->user->email]),
                function (string $attribute, mixed $value, \Closure $fail) use ($list) {
                    if ($list->members()->where('email', $value)->exists()) {
                        $fail('Questa persona fa già parte della lista.');
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
            'email.not_in' => 'Questa lista è già tua.',
        ];
    }
}
