<?php

namespace App\Providers;

use App\Contracts\FundamentalDataProvider;
use App\Contracts\MarketPriceProvider;
use App\Services\Ai\AiToolExecutor;
use App\Services\Ai\OpenAiChatService;
use App\Services\Finance\EodhdCallBudget;
use App\Services\Finance\EodhdMarketPriceProvider;
use App\Services\Finance\FmpFundamentalDataProvider;
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

        $this->app->bind(FundamentalDataProvider::class, fn () => new FmpFundamentalDataProvider(
            config('services.fmp.api_key'),
        ));

        $this->app->bind(OpenAiChatService::class, fn ($app) => new OpenAiChatService(
            config('services.openai.api_key'),
            config('services.openai.model', 'gpt-4o-mini'),
            $app->make(AiToolExecutor::class),
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
