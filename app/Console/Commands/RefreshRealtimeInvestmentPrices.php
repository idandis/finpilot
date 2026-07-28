<?php

namespace App\Console\Commands;

use App\Services\Finance\InvestmentPriceRefreshService;
use Illuminate\Console\Command;

class RefreshRealtimeInvestmentPrices extends Command
{
    /**
     * @var string
     */
    protected $signature = 'investments:refresh-realtime-prices {--budget=200} {--force}';

    /**
     * @var string
     */
    protected $description = 'Refresh delayed intraday quotes for ISINs held in open investment positions (EODHD, rate-limited)';

    public function handle(InvestmentPriceRefreshService $service): int
    {
        $result = $service->refreshRealtime((int) $this->option('budget'), (bool) $this->option('force'));

        if (! $result->hadOpenPositions) {
            $this->info('No open positions to refresh.');

            return self::SUCCESS;
        }

        if ($result->budgetExhaustedUpfront) {
            $this->info('Daily EODHD call budget already exhausted today.');

            return self::SUCCESS;
        }

        $this->info("Refreshed {$result->instrumentsRefreshed} instrument(s), {$result->callsRemaining} call(s) of budget left today.");

        return self::SUCCESS;
    }
}
