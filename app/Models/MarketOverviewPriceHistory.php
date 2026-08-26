<?php

namespace App\Models;

use Database\Factories\MarketOverviewPriceHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $instrument_key
 * @property Carbon $price_date
 * @property string $close_price
 * @property string|null $open_price
 * @property string|null $high_price
 * @property string|null $low_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['instrument_key', 'price_date', 'close_price', 'open_price', 'high_price', 'low_price'])]
class MarketOverviewPriceHistory extends Model
{
    /** @use HasFactory<MarketOverviewPriceHistoryFactory> */
    use HasFactory;

    protected $table = 'market_overview_price_history';

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
            'open_price' => 'decimal:6',
            'high_price' => 'decimal:6',
            'low_price' => 'decimal:6',
        ];
    }
}
