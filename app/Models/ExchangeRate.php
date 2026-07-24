<?php

namespace App\Models;

use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency
 * @property string|null $rate_to_eur
 * @property Carbon|null $rate_date
 * @property Carbon|null $fetched_at
 * @property Carbon|null $history_backfilled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['currency', 'rate_to_eur', 'rate_date', 'fetched_at', 'history_backfilled_at'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_to_eur' => 'decimal:8',
            'rate_date' => 'date',
            'fetched_at' => 'datetime',
            'history_backfilled_at' => 'datetime',
        ];
    }
}
