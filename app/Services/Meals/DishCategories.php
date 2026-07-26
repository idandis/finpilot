<?php

namespace App\Services\Meals;

/**
 * The fixed set of dish categories every preconfigured dish is classified
 * into - shared across all of a user's dishes, so they always group the
 * same way in the "piatti preconfigurati" library.
 */
class DishCategories
{
    /**
     * @var array<string, string>
     */
    public const ALL = [
        'carne' => 'Carne',
        'pesce' => 'Pesce',
        'pasta_riso' => 'Pasta e riso',
        'verdure' => 'Verdure e contorni',
        'legumi' => 'Legumi',
        'zuppe' => 'Zuppe e minestre',
        'uova_formaggi' => 'Uova e formaggi',
        'dolci' => 'Dolci',
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
