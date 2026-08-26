<?php

namespace Tests\Feature\Finance;

use App\Models\MacroIndicatorObservation;
use App\Services\Finance\EcbMacroDataProvider;
use App\Services\Finance\FredMacroDataProvider;
use App\Services\Finance\MacroIndicatorRefreshService;
use App\Services\Finance\MacroIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MacroIndicatorRefreshServiceTest extends TestCase
{
    use RefreshDatabase;

    private function fakeBothProviders(): void
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response([
                'observations' => [
                    ['date' => '2026-06-01', 'value' => '2.8', 'realtime_start' => '2026-07-11'],
                ],
            ]),
            'data-api.ecb.europa.eu/*' => Http::response("TIME_PERIOD,OBS_VALUE\n2026-06,3.1\n"),
        ]);
    }

    private function service(?string $fredApiKey = 'fake-token'): MacroIndicatorRefreshService
    {
        return new MacroIndicatorRefreshService(
            new FredMacroDataProvider($fredApiKey),
            new EcbMacroDataProvider,
        );
    }

    public function test_it_upserts_observations_for_every_fetchable_indicator()
    {
        $this->fakeBothProviders();

        $result = $this->service()->refresh();

        $this->assertSame(count(MacroIndicators::fetchable()), $result->indicatorsRefreshed);
        $this->assertSame([], $result->failedIndicators);

        $usCpi = MacroIndicatorObservation::where('indicator_key', 'us_cpi_yoy')->first();
        $this->assertNotNull($usCpi);
        $this->assertSame('2026-06-01', $usCpi->observation_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(2.8, (float) $usCpi->value, 0.0001);

        $eaHicp = MacroIndicatorObservation::where('indicator_key', 'ea_hicp_yoy')->first();
        $this->assertNotNull($eaHicp);
        $this->assertSame('2026-06-30', $eaHicp->observation_date->format('Y-m-d'));
        $this->assertEqualsWithDelta(3.1, (float) $eaHicp->value, 0.0001);
    }

    public function test_running_refresh_twice_does_not_create_duplicate_rows()
    {
        $this->fakeBothProviders();

        $this->service()->refresh();
        $this->service()->refresh();

        $this->assertSame(1, MacroIndicatorObservation::where('indicator_key', 'us_cpi_yoy')->count());
    }

    public function test_a_second_run_can_update_an_already_stored_value()
    {
        // A single fake registered once, reading a mutable capture - unlike
        // calling Http::fake() a second time (which only appends another
        // stub behind the first: matching iterates registration order via
        // ->first(), so an earlier stub for the same URL always wins and a
        // later Http::fake() call would silently never be reached).
        $cpiValue = '2.8';

        Http::fake(function ($request) use (&$cpiValue) {
            if (str_contains($request->url(), 'api.stlouisfed.org')) {
                if ($request['series_id'] === 'CPIAUCSL') {
                    return Http::response(['observations' => [['date' => '2026-06-01', 'value' => $cpiValue]]]);
                }

                return Http::response(['observations' => []]);
            }

            return Http::response("TIME_PERIOD,OBS_VALUE\n2026-06,3.1\n");
        });

        $this->service()->refresh();

        $cpiValue = '2.9';
        $this->service()->refresh();

        $usCpi = MacroIndicatorObservation::where('indicator_key', 'us_cpi_yoy')->first();
        $this->assertEqualsWithDelta(2.9, (float) $usCpi->value, 0.0001);
    }

    public function test_it_records_failed_indicators_when_the_fred_api_key_is_missing()
    {
        Http::fake([
            'data-api.ecb.europa.eu/*' => Http::response("TIME_PERIOD,OBS_VALUE\n2026-06,3.1\n"),
        ]);

        $result = $this->service(fredApiKey: null)->refresh();

        $fredIndicatorCount = collect(MacroIndicators::fetchable())
            ->filter(fn (array $meta) => $meta['fetch']['source'] === 'fred')
            ->count();

        $this->assertCount($fredIndicatorCount, $result->failedIndicators);
        $this->assertSame(0, MacroIndicatorObservation::where('indicator_key', 'us_cpi_yoy')->count());
    }
}
