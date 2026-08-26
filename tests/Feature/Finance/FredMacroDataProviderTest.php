<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\FredMacroDataProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FredMacroDataProviderTest extends TestCase
{
    public function test_it_fetches_and_maps_observations()
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response([
                'observations' => [
                    ['date' => '2026-05-01', 'value' => '2.9', 'realtime_start' => '2026-06-11'],
                    ['date' => '2026-06-01', 'value' => '2.8', 'realtime_start' => '2026-07-11'],
                ],
            ]),
        ]);

        $provider = new FredMacroDataProvider('fake-token');
        $observations = $provider->fetchObservations('CPIAUCSL|pc1');

        $this->assertNotNull($observations);
        $this->assertCount(2, $observations);
        $this->assertSame(2.9, $observations[0]->value);
        $this->assertSame('2026-05-01', $observations[0]->date->format('Y-m-d'));
        $this->assertSame('2026-06-11', $observations[0]->publishedAt->format('Y-m-d'));
        $this->assertSame(2.8, $observations[1]->value);

        Http::assertSent(function ($request) {
            return $request['series_id'] === 'CPIAUCSL'
                && $request['units'] === 'pc1'
                && $request['api_key'] === 'fake-token';
        });
    }

    public function test_it_defaults_to_lin_units_when_none_are_encoded_in_the_series_id()
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response(['observations' => []]),
        ]);

        $provider = new FredMacroDataProvider('fake-token');
        $provider->fetchObservations('UNRATE');

        Http::assertSent(fn ($request) => $request['series_id'] === 'UNRATE' && $request['units'] === 'lin');
    }

    /**
     * FRED reports the literal string "." for a missing value instead of
     * omitting the row - naively casting that to float would silently
     * store a bogus 0.
     */
    public function test_it_filters_out_missing_values()
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response([
                'observations' => [
                    ['date' => '2026-05-01', 'value' => '.'],
                    ['date' => '2026-06-01', 'value' => '2.8'],
                ],
            ]),
        ]);

        $provider = new FredMacroDataProvider('fake-token');
        $observations = $provider->fetchObservations('CPIAUCSL');

        $this->assertCount(1, $observations);
        $this->assertSame(2.8, $observations[0]->value);
    }

    public function test_it_returns_null_without_an_api_key()
    {
        Http::fake();

        $provider = new FredMacroDataProvider(null);

        $this->assertNull($provider->fetchObservations('CPIAUCSL'));
        Http::assertNothingSent();
    }

    public function test_it_returns_null_when_the_request_fails()
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response(null, 500),
        ]);

        $provider = new FredMacroDataProvider('fake-token');

        $this->assertNull($provider->fetchObservations('CPIAUCSL'));
    }

    public function test_it_passes_observation_start_when_from_is_given()
    {
        Http::fake([
            'api.stlouisfed.org/*' => Http::response(['observations' => []]),
        ]);

        $provider = new FredMacroDataProvider('fake-token');
        $provider->fetchObservations('CPIAUCSL', Carbon::parse('2026-01-01'));

        Http::assertSent(fn ($request) => $request['observation_start'] === '2026-01-01');
    }
}
