<?php

namespace App\Services\Finance;

readonly class InvestmentPriceRefreshResult
{
    public function __construct(
        public bool $hadOpenPositions = true,
        public bool $budgetExhaustedUpfront = false,
        public int $ratesRefreshed = 0,
        public int $instrumentsRefreshed = 0,
        public int $callsRemaining = 0,
    ) {}

    public function anythingRefreshed(): bool
    {
        return $this->ratesRefreshed > 0 || $this->instrumentsRefreshed > 0;
    }
}
