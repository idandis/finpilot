<?php

namespace Tests\Feature\Finance;

use App\Services\Finance\MacroNarrativeService;
use Tests\TestCase;

class MacroNarrativeServiceTest extends TestCase
{
    public function test_it_explains_inflation_improving_but_still_above_target()
    {
        $narrative = (new MacroNarrativeService)->explain('us_cpi_yoy', current: 2.8, previous: 3.0);

        $this->assertSame(
            "L'inflazione USA è scesa da 3,0% a 2,8%. Il trend è in miglioramento. Il livello rimane sopra l'obiettivo del 2% della Federal Reserve.",
            $narrative,
        );
    }

    public function test_it_explains_unemployment_worsening()
    {
        $narrative = (new MacroNarrativeService)->explain('us_unemployment', current: 4.3, previous: 4.1);

        $this->assertSame(
            'La disoccupazione USA è salita da 4,1% a 4,3%. Il trend è in peggioramento.',
            $narrative,
        );
    }

    public function test_it_explains_gdp_growth_slowing_with_its_period_note()
    {
        $narrative = (new MacroNarrativeService)->explain('us_gdp_growth', current: 2.5, previous: 3.1);

        $this->assertSame(
            'La crescita del PIL USA è scesa da 3,1% a 2,5% (variazione trimestrale annualizzata). Il trend è in peggioramento.',
            $narrative,
        );
    }

    public function test_it_reports_no_trend_clause_for_an_indicator_without_a_good_direction()
    {
        $narrative = (new MacroNarrativeService)->explain('us_fed_funds', current: 4.5, previous: 4.25);

        $this->assertSame('Il tasso Fed Funds è salito da 4,3% a 4,5%.', $narrative);
    }

    public function test_it_reports_stable_when_the_move_is_below_the_threshold()
    {
        $narrative = (new MacroNarrativeService)->explain('us_fed_funds', current: 4.25, previous: 4.24);

        $this->assertSame('Il tasso Fed Funds è rimasto stabile 4,3%.', $narrative);
    }

    public function test_it_handles_a_missing_current_value()
    {
        $narrative = (new MacroNarrativeService)->explain('us_cpi_yoy', current: null, previous: 3.0);

        $this->assertSame('Dato non ancora disponibile.', $narrative);
    }

    public function test_it_handles_a_missing_previous_value()
    {
        $narrative = (new MacroNarrativeService)->explain('us_cpi_yoy', current: 2.8, previous: null);

        $this->assertSame("L'inflazione USA è 2,8%.", $narrative);
    }

    public function test_trend_is_improving_when_the_move_matches_the_good_direction()
    {
        $trend = (new MacroNarrativeService)->trend('us_cpi_yoy', current: 2.8, previous: 3.0);

        $this->assertSame('improving', $trend);
    }

    public function test_trend_is_worsening_when_the_move_opposes_the_good_direction()
    {
        $trend = (new MacroNarrativeService)->trend('us_unemployment', current: 4.3, previous: 4.1);

        $this->assertSame('worsening', $trend);
    }

    public function test_trend_is_neutral_for_an_indicator_without_a_good_direction()
    {
        $trend = (new MacroNarrativeService)->trend('us_fed_funds', current: 4.5, previous: 4.25);

        $this->assertSame('neutral', $trend);
    }

    public function test_trend_is_stable_below_the_threshold()
    {
        $trend = (new MacroNarrativeService)->trend('us_cpi_yoy', current: 2.8, previous: 2.79);

        $this->assertSame('stable', $trend);
    }
}
