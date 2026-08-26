<?php

namespace App\Services\Finance;

/**
 * The fixed watchlist behind "Investimenti > Macro > Mercati" - a curated
 * set of indices/commodities/currencies/crypto, not a trading terminal.
 * Same "static catalog" shape as MacroIndicators::ALL. `code`/`exchange`
 * are handed straight to MarketPriceProvider::fetchHistory() (bound to
 * EodhdMarketPriceProvider) exactly like an already-resolved portfolio
 * ISIN would be - these tickers are fixed and well-known, so there's no
 * resolveSymbol() step to run first.
 *
 * Every entry below was confirmed against a live EODHD response (not just
 * assumed from documented conventions - .INDX and .CC checked out, but
 * .COMM 404s on this plan; commodities are quoted as synthetic FX pairs
 * instead, e.g. XAUUSD.FOREX for gold). A wrong ticker only ever shows up
 * as a failed key from MarketOverviewRefreshService::refresh(), never a
 * crash, so this is still safe to re-verify if EODHD changes coverage.
 */
class MarketOverviewInstruments
{
    public const CATEGORY_LABELS = [
        'indici' => 'Indici azionari',
        'settori' => 'Settori (S&P 500, USA)',
        'volatilita' => 'Volatilità',
        'commodity' => 'Materie prime',
        'valute' => 'Valute',
        'crypto' => 'Criptovalute',
    ];

    public const ALL = [
        'sp500' => [
            'category' => 'indici',
            'label' => 'S&P 500',
            'region' => 'usa',
            'unit' => 'pt',
            'code' => 'GSPC',
            'exchange' => 'INDX',
        ],
        'nasdaq' => [
            'category' => 'indici',
            'label' => 'Nasdaq Composite',
            'region' => 'usa',
            'unit' => 'pt',
            'code' => 'IXIC',
            'exchange' => 'INDX',
        ],
        'euro_stoxx_50' => [
            'category' => 'indici',
            'label' => 'Euro Stoxx 50',
            'region' => 'eurozone',
            'unit' => 'pt',
            'code' => 'STOXX50E',
            'exchange' => 'INDX',
        ],
        'ftse_mib' => [
            'category' => 'indici',
            'label' => 'Italia (MSCI Italy)',
            'region' => 'italia',
            'unit' => 'USD',
            // FTSE MIB isn't covered on this EODHD plan (FTSEMIB.INDX
            // resolves but returns no data) - EWI (iShares MSCI Italy ETF,
            // US-listed) is the proxy, same pattern as emerging_markets.
            'code' => 'EWI',
            'exchange' => 'US',
        ],
        'emerging_markets' => [
            'category' => 'indici',
            'label' => 'Mercati emergenti (MSCI EM)',
            'region' => 'globale',
            'unit' => 'USD',
            // No raw EM index ticker confirmed on EODHD - EEM (iShares MSCI
            // Emerging Markets ETF, US-listed) is the standard proxy.
            'code' => 'EEM',
            'exchange' => 'US',
        ],
        // The 11 SPDR Select Sector ETFs - the standard US market proxy for
        // sector performance (same role EEM/EWI play for a region above).
        'sector_technology' => [
            'category' => 'settori',
            'label' => 'Tecnologia',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLK',
            'exchange' => 'US',
        ],
        'sector_financials' => [
            'category' => 'settori',
            'label' => 'Finanziari',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLF',
            'exchange' => 'US',
        ],
        'sector_healthcare' => [
            'category' => 'settori',
            'label' => 'Salute',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLV',
            'exchange' => 'US',
        ],
        'sector_consumer_discretionary' => [
            'category' => 'settori',
            'label' => 'Beni voluttuari',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLY',
            'exchange' => 'US',
        ],
        'sector_consumer_staples' => [
            'category' => 'settori',
            'label' => 'Beni di consumo primari',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLP',
            'exchange' => 'US',
        ],
        'sector_energy' => [
            'category' => 'settori',
            'label' => 'Energia',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLE',
            'exchange' => 'US',
        ],
        'sector_industrials' => [
            'category' => 'settori',
            'label' => 'Industriali',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLI',
            'exchange' => 'US',
        ],
        'sector_materials' => [
            'category' => 'settori',
            'label' => 'Materiali',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLB',
            'exchange' => 'US',
        ],
        'sector_utilities' => [
            'category' => 'settori',
            'label' => 'Utilities',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLU',
            'exchange' => 'US',
        ],
        'sector_real_estate' => [
            'category' => 'settori',
            'label' => 'Immobiliare',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLRE',
            'exchange' => 'US',
        ],
        'sector_communications' => [
            'category' => 'settori',
            'label' => 'Comunicazioni',
            'region' => 'usa',
            'unit' => 'USD',
            'code' => 'XLC',
            'exchange' => 'US',
        ],
        'vix' => [
            'category' => 'volatilita',
            'label' => 'VIX',
            'region' => 'usa',
            'unit' => 'pt',
            'code' => 'VIX',
            'exchange' => 'INDX',
        ],
        'gold' => [
            'category' => 'commodity',
            'label' => 'Oro',
            'region' => 'globale',
            'unit' => 'USD',
            // Verified live against EODHD: ".COMM" isn't a valid exchange
            // on this plan (404 on GC.COMM) - spot metals are quoted as
            // synthetic FX pairs instead (XAU = ISO 4217 gold code).
            'code' => 'XAUUSD',
            'exchange' => 'FOREX',
        ],
        'oil_wti' => [
            'category' => 'commodity',
            'label' => 'Petrolio (WTI)',
            'region' => 'globale',
            'unit' => 'USD',
            // CL.COMM 404s; CL.US returns WTI crude at a plausible price
            // and is what EODHD actually serves this future under.
            'code' => 'CL',
            'exchange' => 'US',
        ],
        'copper' => [
            'category' => 'commodity',
            'label' => 'Rame',
            'region' => 'globale',
            'unit' => 'USD',
            // Same synthetic-FX-pair convention as gold (XCU = ISO 4217
            // copper code) - HG.COMM 404s on this plan.
            'code' => 'XCUUSD',
            'exchange' => 'FOREX',
        ],
        'eur_usd' => [
            'category' => 'valute',
            'label' => 'EUR/USD',
            'region' => 'globale',
            'unit' => '',
            'code' => 'EURUSD',
            'exchange' => 'FOREX',
        ],
        'bitcoin' => [
            'category' => 'crypto',
            'label' => 'Bitcoin',
            'region' => 'globale',
            'unit' => 'USD',
            'code' => 'BTC-USD',
            'exchange' => 'CC',
        ],
    ];
}
