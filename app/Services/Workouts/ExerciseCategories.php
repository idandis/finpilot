<?php

namespace App\Services\Workouts;

/**
 * The fixed set of muscle-group categories every exercise is classified
 * into - shared across all of a user's exercises, so they always group the
 * same way in the exercise library.
 */
class ExerciseCategories
{
    /**
     * @var array<string, string>
     */
    public const ALL = [
        'braccia' => 'Braccia',
        'gambe' => 'Gambe',
        'addome' => 'Addome',
        'schiena' => 'Schiena',
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
