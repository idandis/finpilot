<?php

namespace App\Services\Shopping;

/**
 * The fixed set of grocery categories every shopping list item is
 * classified into - shared across all of a user's lists (not
 * per-list/customizable), so items always group the same way regardless of
 * which list they're on.
 */
class GroceryCategories
{
    /**
     * @var array<string, string>
     */
    public const ALL = [
        'frutta' => 'Frutta',
        'verdura' => 'Verdura',
        'carne' => 'Carne',
        'pesce' => 'Pesce',
        'latticini' => 'Latticini e formaggi',
        'salumi' => 'Salumi',
        'panetteria' => 'Pane e pasticceria',
        'pasta_riso_cereali' => 'Pasta, riso e cereali',
        'conserve' => 'Scatolame e conserve',
        'surgelati' => 'Surgelati',
        'bevande' => 'Bevande',
        'snack_dolci' => 'Snack e dolci',
        'condimenti' => 'Condimenti e spezie',
        'casa_pulizia' => 'Casa e pulizia',
        'igiene_persona' => 'Igiene e cura persona',
        'altro' => 'Altro',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
