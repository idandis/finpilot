<?php

namespace App\Models;

use Database\Factories\InstrumentPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $isin
 * @property string|null $code
 * @property string|null $exchange
 * @property bool $resolution_failed
 * @property string|null $last_price
 * @property string|null $currency
 * @property Carbon|null $price_date
 * @property Carbon|null $fetched_at
 * @property Carbon|null $history_backfilled_at
 * @property Carbon|null $news_fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['isin', 'code', 'exchange', 'resolution_failed', 'last_price', 'currency', 'price_date', 'fetched_at', 'history_backfilled_at', 'news_fetched_at'])]
class InstrumentPrice extends Model
{
    /** @use HasFactory<InstrumentPriceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolution_failed' => 'boolean',
            'last_price' => 'decimal:6',
            'price_date' => 'date',
            'fetched_at' => 'datetime',
            'history_backfilled_at' => 'datetime',
            'news_fetched_at' => 'datetime',
        ];
    }
}
