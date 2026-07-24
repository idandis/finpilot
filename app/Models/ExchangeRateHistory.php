<?php

namespace App\Models;

use Database\Factories\ExchangeRateHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $currency
 * @property Carbon $rate_date
 * @property string $rate_to_eur
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['currency', 'rate_date', 'rate_to_eur'])]
class ExchangeRateHistory extends Model
{
    /** @use HasFactory<ExchangeRateHistoryFactory> */
    use HasFactory;

    protected $table = 'exchange_rate_history';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate_to_eur' => 'decimal:8',
        ];
    }
}
