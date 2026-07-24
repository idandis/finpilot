<?php

namespace App\Providers;

use App\Contracts\MarketPriceProvider;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\EodhdMarketPriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EodhdCallBudget::class, fn () => new EodhdCallBudget(
            (int) config('services.eodhd.daily_call_budget', 18),
        ));

        $this->app->bind(MarketPriceProvider::class, fn ($app) => new EodhdMarketPriceProvider(
            config('services.eodhd.api_key'),
            $app->make(EodhdCallBudget::class),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
