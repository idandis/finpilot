<?php

namespace App\Services\Finance;

readonly class MarketOverviewRefreshResult
{
    /**
     * @param  array<int, string>  $failedInstruments
     */
    public function __construct(
        public int $instrumentsRefreshed = 0,
        public int $pricesWritten = 0,
        public array $failedInstruments = [],
    ) {}
}
