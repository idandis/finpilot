<?php

namespace App\Services\Budget;

use App\Models\User;

/**
 * Di chi è il budget che si sta guardando o modificando.
 *
 * Un budget non ha una riga propria: è l'insieme di categorie e mesi di un
 * utente. Chi lo apre vede il proprio, a meno che la richiesta indichi il
 * proprietario di uno condiviso con lui - stessa convenzione della
 * pianificazione dei pasti (`?plan=`, `plan_user_id`).
 */
final class BudgetOwner
{
    /** Il proprietario richiesto se accessibile, altrimenti l'utente stesso. */
    public static function resolve(User $user, mixed $requested): User
    {
        if (! is_numeric($requested)) {
            return $user;
        }

        $owner = User::query()->find((int) $requested);

        return $owner?->budgetIsAccessibleBy($user) ? $owner : $user;
    }
}
