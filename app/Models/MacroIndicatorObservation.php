<?php

namespace App\Models;

use Database\Factories\MacroIndicatorObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $indicator_key
 * @property Carbon $observation_date
 * @property string $value
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['indicator_key', 'observation_date', 'value', 'published_at'])]
class MacroIndicatorObservation extends Model
{
    /** @use HasFactory<MacroIndicatorObservationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'observation_date' => 'date',
            'value' => 'decimal:6',
            'published_at' => 'date',
        ];
    }
}
