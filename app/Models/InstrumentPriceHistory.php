<?php

namespace App\Models;

use Database\Factories\InstrumentPriceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $isin
 * @property Carbon $price_date
 * @property string $close_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['isin', 'price_date', 'close_price'])]
class InstrumentPriceHistory extends Model
{
    /** @use HasFactory<InstrumentPriceHistoryFactory> */
    use HasFactory;

    protected $table = 'instrument_price_history';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_date' => 'date',
            'close_price' => 'decimal:6',
        ];
    }
}
