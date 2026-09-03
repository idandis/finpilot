<?php

namespace App\Http\Requests\Budget;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetMemberStoreRequest extends FormRequest
{
    /**
     * Si condivide sempre il proprio budget: non c'è un id da controllare, e
     * ricondividere quello di un altro semplicemente non esiste.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                // Solo persone già registrate: non c'è un flusso di invito,
                // il budget compare direttamente nel loro selettore.
                Rule::exists('users', 'email'),
                Rule::notIn([$this->user()->email]),
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($this->user()->budgetMembers()->where('email', $value)->exists()) {
                        $fail('Questa persona vede già il tuo budget.');
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
            'email.not_in' => 'Questo budget è già tuo.',
        ];
    }
}
