<?php

namespace App\Console\Commands;

use App\Services\Finance\MarketOverviewRefreshService;
use Illuminate\Console\Command;

class RefreshMarketOverview extends Command
{
    /**
     * @var string
     */
    protected $signature = 'market-overview:refresh';

    /**
     * @var string
     */
    protected $description = 'Refresh the curated market watchlist (indices, VIX, commodities, EUR/USD, Bitcoin) via EODHD';

    public function handle(MarketOverviewRefreshService $service): int
    {
        $result = $service->refresh();

        $this->info("Aggiornati {$result->instrumentsRefreshed} strumenti, {$result->pricesWritten} prezzi salvati.");

        if ($result->failedInstruments !== []) {
            $this->warn('Strumenti non aggiornati: '.implode(', ', $result->failedInstruments));
        }

        return self::SUCCESS;
    }
}
