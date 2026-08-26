<?php

namespace App\Services\Finance;

readonly class MacroIndicatorRefreshResult
{
    /**
     * @param  array<int, string>  $failedIndicators
     */
    public function __construct(
        public int $indicatorsRefreshed = 0,
        public int $observationsWritten = 0,
        public array $failedIndicators = [],
    ) {}
}
