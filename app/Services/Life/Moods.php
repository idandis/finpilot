<?php

namespace App\Services\Life;

/**
 * The fixed set of moods a "ricordo" can be tagged with - shared across all
 * of a user's memories, so the future mood-based week color (a later phase)
 * always groups the same way.
 */
class Moods
{
    /**
     * @var array<string, string>
     */
    public const ALL = [
        'ottimo' => 'Ottimo',
        'buono' => 'Buono',
        'neutro' => 'Neutro',
        'difficile' => 'Difficile',
        'pessimo' => 'Pessimo',
    ];

    /**
     * A 1-5 numeric score per mood, used to average a week's moods into the
     * "mood medio" metric on the year grid.
     *
     * @var array<string, int>
     */
    public const SCORES = [
        'pessimo' => 1,
        'difficile' => 2,
        'neutro' => 3,
        'buono' => 4,
        'ottimo' => 5,
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
