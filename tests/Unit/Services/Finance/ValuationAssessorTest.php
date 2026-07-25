<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\ValuationAssessor;
use Tests\TestCase;

class ValuationAssessorTest extends TestCase
{
    public function test_it_returns_all_nulls_when_fair_value_is_missing()
    {
        $result = ValuationAssessor::assess(532.0, null);

        $this->assertNull($result['deviation_percent']);
        $this->assertNull($result['verdict']);
        $this->assertNull($result['recommended_action']);
        $this->assertNull($result['entry_price']);
        $this->assertNull($result['accumulate_price']);
    }

    public function test_it_still_computes_price_thresholds_without_a_current_price()
    {
        $result = ValuationAssessor::assess(null, 515.0);

        $this->assertNull($result['deviation_percent']);
        $this->assertNull($result['verdict']);
        $this->assertSame(489.25, $result['entry_price']);
        $this->assertSame(437.75, $result['accumulate_price']);
    }

    public function test_a_price_far_below_fair_value_is_undervalued()
    {
        $result = ValuationAssessor::assess(400.0, 515.0);

        $this->assertSame('undervalued', $result['verdict']);
        $this->assertSame('Compra con decisione', $result['recommended_action']);
    }

    public function test_a_price_close_to_fair_value_is_fairly_valued()
    {
        $result = ValuationAssessor::assess(532.0, 515.0);

        $this->assertSame(3.3, $result['deviation_percent']);
        $this->assertSame('fair', $result['verdict']);
        $this->assertSame('Accumula gradualmente', $result['recommended_action']);
    }

    public function test_a_price_well_above_fair_value_is_expensive()
    {
        $result = ValuationAssessor::assess(600.0, 515.0);

        $this->assertSame('expensive', $result['verdict']);
        $this->assertSame('Aspetta una correzione', $result['recommended_action']);
    }

    public function test_a_price_far_above_fair_value_is_very_expensive()
    {
        $result = ValuationAssessor::assess(750.0, 515.0);

        $this->assertSame('very_expensive', $result['verdict']);
        $this->assertSame('Non comprare ora', $result['recommended_action']);
    }
}
