<?php

namespace App\Services\Finance;

readonly class InvestmentRealtimePriceRefreshResult
{
    public function __construct(
        public bool $hadOpenPositions = true,
        public bool $budgetExhaustedUpfront = false,
        public int $instrumentsRefreshed = 0,
        public int $callsRemaining = 0,
    ) {}

    public function anythingRefreshed(): bool
    {
        return $this->instrumentsRefreshed > 0;
    }
}
