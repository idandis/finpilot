<?php

namespace Tests\Unit\Services\Finance;

use App\Services\Finance\TradeDescription;
use Tests\TestCase;

class TradeDescriptionCryptoTest extends TestCase
{
    public function test_is_crypto_recognizes_the_xf_pseudo_isin_prefix()
    {
        $this->assertTrue(TradeDescription::isCrypto('XF000BTC0017'));
        $this->assertFalse(TradeDescription::isCrypto('IE00BK5BQT80'));
        $this->assertFalse(TradeDescription::isCrypto('US0378331005'));
    }

    public function test_crypto_ticker_extracts_the_embedded_ticker()
    {
        $this->assertSame('BTC', TradeDescription::cryptoTicker('XF000BTC0017'));
        $this->assertSame('ETH', TradeDescription::cryptoTicker('XF000ETH0019'));
    }

    public function test_crypto_ticker_returns_null_for_a_non_crypto_isin()
    {
        $this->assertNull(TradeDescription::cryptoTicker('IE00BK5BQT80'));
    }

    public function test_crypto_ticker_returns_null_for_a_malformed_pseudo_isin()
    {
        $this->assertNull(TradeDescription::cryptoTicker('XF12345678901'));
    }
}
