<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

readonly class FetchedPrice
{
    /**
     * $open/$high/$low are only populated by fetchHistory() (needed for a
     * candlestick chart) - fetchPrice() only ever needs the close, so it
     * leaves them null rather than fetching data nothing asked for.
     */
    public function __construct(
        public float $price,
        public Carbon $date,
        public ?float $open = null,
        public ?float $high = null,
        public ?float $low = null,
    ) {}
}
