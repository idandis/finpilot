<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('investments:refresh-prices')->dailyAt('06:00');
Schedule::command('market-overview:refresh')->dailyAt('06:30');
Schedule::command('macro:refresh-indicators')->dailyAt('07:00');
