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
    /**
     * Il proprietario richiesto se accessibile, altrimenti quello scelto come
     * predefinito, altrimenti l'utente stesso.
     */
    public static function resolve(User $user, mixed $requested): User
    {
        if (! is_numeric($requested)) {
            return self::preferred($user);
        }

        $owner = User::query()->find((int) $requested);

        return $owner?->budgetIsAccessibleBy($user) ? $owner : $user;
    }

    /**
     * Il budget che si apre senza chiedere niente.
     *
     * Chi ha eletto a predefinito un budget condiviso lo ritrova aprendo
     * l'app. Se nel frattempo l'accesso è finito - l'altra persona l'ha tolto
     * dalla condivisione - si torna al proprio senza dire niente: la scelta
     * resta scritta, e torna utile se lo ricondividono.
     */
    private static function preferred(User $user): User
    {
        $preferred = $user->default_budget_user_id;

        if ($preferred === null || $preferred === $user->id) {
            return $user;
        }

        $owner = User::query()->find($preferred);

        return $owner?->budgetIsAccessibleBy($user) ? $owner : $user;
    }
}
