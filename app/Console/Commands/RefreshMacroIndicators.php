<?php

namespace App\Console\Commands;

use App\Services\Finance\MacroIndicatorRefreshService;
use Illuminate\Console\Command;

class RefreshMacroIndicators extends Command
{
    /**
     * @var string
     */
    protected $signature = 'macro:refresh-indicators';

    /**
     * @var string
     */
    protected $description = 'Refresh macroeconomic indicators from FRED (USA) and the ECB Data Portal (Eurozone)';

    public function handle(MacroIndicatorRefreshService $service): int
    {
        $result = $service->refresh();

        $this->info("Aggiornati {$result->indicatorsRefreshed} indicatori, {$result->observationsWritten} osservazioni salvate.");

        if ($result->failedIndicators !== []) {
            $this->warn('Indicatori non aggiornati: '.implode(', ', $result->failedIndicators));
        }

        return self::SUCCESS;
    }
}
