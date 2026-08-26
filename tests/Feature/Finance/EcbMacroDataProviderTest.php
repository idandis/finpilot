<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\EcbMacroDataProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EcbMacroDataProviderTest extends TestCase
{
    public function test_it_parses_monthly_observations_from_csv()
    {
        $csv = "FREQ,REF_AREA,TIME_PERIOD,OBS_VALUE\nM,U2,2026-05,2.3\nM,U2,2026-06,2.1\n";

        Http::fake(['data-api.ecb.europa.eu/*' => Http::response($csv)]);

        $provider = new EcbMacroDataProvider;
        $observations = $provider->fetchObservations('ICP/M.U2.N.000000.4.ANR');

        $this->assertNotNull($observations);
        $this->assertCount(2, $observations);
        $this->assertSame(2.3, $observations[0]->value);
        $this->assertSame('2026-05-31', $observations[0]->date->format('Y-m-d'));
        $this->assertSame(2.1, $observations[1]->value);
        $this->assertSame('2026-06-30', $observations[1]->date->format('Y-m-d'));
    }

    public function test_it_parses_quarterly_periods_to_the_end_of_the_quarter()
    {
        $csv = "TIME_PERIOD,OBS_VALUE\n2026-Q2,1.3\n";

        Http::fake(['data-api.ecb.europa.eu/*' => Http::response($csv)]);

        $provider = new EcbMacroDataProvider;
        $observations = $provider->fetchObservations('MNA/Q.Y.I9.W2.S1.S1.B.B1GQ._Z._Z._Z.EUR.LR.GY');

        $this->assertSame('2026-06-30', $observations[0]->date->format('Y-m-d'));
    }

    public function test_it_parses_annual_periods_to_the_end_of_the_year()
    {
        $csv = "TIME_PERIOD,OBS_VALUE\n2026,3.5\n";

        Http::fake(['data-api.ecb.europa.eu/*' => Http::response($csv)]);

        $provider = new EcbMacroDataProvider;
        $observations = $provider->fetchObservations('BSI/M.U2.Y.V.M30.X.I.U2.2300.Z01.A');

        $this->assertSame('2026-12-31', $observations[0]->date->format('Y-m-d'));
    }

    public function test_it_skips_rows_with_a_blank_value()
    {
        $csv = "TIME_PERIOD,OBS_VALUE\n2026-05,\n2026-06,2.1\n";

        Http::fake(['data-api.ecb.europa.eu/*' => Http::response($csv)]);

        $provider = new EcbMacroDataProvider;
        $observations = $provider->fetchObservations('ICP/M.U2.N.000000.4.ANR');

        $this->assertCount(1, $observations);
        $this->assertSame(2.1, $observations[0]->value);
    }

    public function test_it_returns_null_when_the_request_fails()
    {
        Http::fake(['data-api.ecb.europa.eu/*' => Http::response(null, 500)]);

        $provider = new EcbMacroDataProvider;

        $this->assertNull($provider->fetchObservations('ICP/M.U2.N.000000.4.ANR'));
    }

    public function test_it_returns_null_when_the_body_is_empty()
    {
        Http::fake(['data-api.ecb.europa.eu/*' => Http::response('')]);

        $provider = new EcbMacroDataProvider;

        $this->assertNull($provider->fetchObservations('ICP/M.U2.N.000000.4.ANR'));
    }
}
