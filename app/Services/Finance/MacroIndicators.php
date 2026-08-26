<?php

namespace App\Services\Finance;

/**
 * The fixed catalog of macro indicators tracked by the "Macro" dashboard -
 * same "curated constant list" shape as InvestmentMotivations, just richer
 * per-entry metadata since each one drives a fetch (or a derivation) plus a
 * narrative sentence.
 *
 * Every entry has either a `fetch` (pulled from FRED or the ECB and stored
 * in macro_indicator_observations) or a `derive` (computed at read time from
 * other entries in this same catalog, never stored on its own). Entries with
 * `display => false` are raw feeders for a `derive` entry and are never
 * shown directly on the dashboard. `subject` is the natural-Italian noun
 * phrase (with article) MacroNarrativeService opens its sentence with -
 * kept separate from `label` (the short card heading) because a narrative
 * sentence and a card heading want different phrasing ("L'inflazione USA"
 * vs "Inflazione (CPI)").
 */
class MacroIndicators
{
    public const CATEGORY_LABELS = [
        'inflazione' => 'Inflazione',
        'tassi' => 'Tassi ufficiali',
        'crescita' => 'Crescita (PIL)',
        'lavoro' => 'Lavoro',
        'rendimenti' => 'Rendimenti e curva',
        'produzione_consumi' => 'Produzione e consumi',
        'massa_monetaria' => 'Massa monetaria',
    ];

    public const ALL = [
        'us_cpi_yoy' => [
            'region' => 'usa',
            'category' => 'inflazione',
            'label' => 'Inflazione (CPI)',
            'subject' => "L'inflazione USA",
            'unit' => '%',
            'good_direction' => 'down',
            'target' => 2.0,
            'target_institution' => 'della Federal Reserve',
            'fetch' => ['source' => 'fred', 'series' => 'CPIAUCSL', 'units' => 'pc1'],
        ],
        'us_fed_funds' => [
            'region' => 'usa',
            'category' => 'tassi',
            'label' => 'Tasso Fed Funds',
            'subject' => 'Il tasso Fed Funds',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'FEDFUNDS'],
        ],
        'us_unemployment' => [
            'region' => 'usa',
            'category' => 'lavoro',
            'label' => 'Disoccupazione',
            'subject' => 'La disoccupazione USA',
            'unit' => '%',
            'good_direction' => 'down',
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'UNRATE'],
        ],
        'us_gdp_growth' => [
            'region' => 'usa',
            'category' => 'crescita',
            'label' => 'Crescita PIL (trim. annualizzato)',
            'subject' => 'La crescita del PIL USA',
            'unit' => '%',
            'good_direction' => 'up',
            'target' => null,
            'period_note' => 'variazione trimestrale annualizzata',
            'fetch' => ['source' => 'fred', 'series' => 'A191RL1Q225SBEA'],
        ],
        'us_industrial_production' => [
            'region' => 'usa',
            'category' => 'produzione_consumi',
            'label' => 'Produzione industriale',
            'subject' => 'La produzione industriale USA',
            'unit' => '%',
            'good_direction' => 'up',
            'target' => null,
            'period_note' => 'variazione annua',
            'fetch' => ['source' => 'fred', 'series' => 'INDPRO', 'units' => 'pc1'],
        ],
        'us_retail_sales' => [
            'region' => 'usa',
            'category' => 'produzione_consumi',
            'label' => 'Vendite al dettaglio',
            'subject' => 'Le vendite al dettaglio USA',
            'unit' => '%',
            'good_direction' => 'up',
            'target' => null,
            'period_note' => 'variazione annua',
            'fetch' => ['source' => 'fred', 'series' => 'RSAFS', 'units' => 'pc1'],
        ],
        'us_m2' => [
            'region' => 'usa',
            'category' => 'massa_monetaria',
            'label' => 'Massa monetaria (M2)',
            'subject' => 'La massa monetaria USA (M2)',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'period_note' => 'variazione annua',
            'fetch' => ['source' => 'fred', 'series' => 'M2SL', 'units' => 'pc1'],
        ],
        'us_yield_3m' => [
            'region' => 'usa',
            'category' => 'rendimenti',
            'label' => 'Rendimento Treasury 3 mesi',
            'subject' => 'Il rendimento del Treasury a 3 mesi',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'DGS3MO'],
        ],
        'us_yield_2y' => [
            'region' => 'usa',
            'category' => 'rendimenti',
            'label' => 'Rendimento Treasury 2 anni',
            'subject' => 'Il rendimento del Treasury a 2 anni',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'DGS2'],
        ],
        'us_yield_10y' => [
            'region' => 'usa',
            'category' => 'rendimenti',
            'label' => 'Rendimento Treasury 10 anni',
            'subject' => 'Il rendimento del Treasury a 10 anni',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'DGS10'],
        ],
        'us_yield_30y' => [
            'region' => 'usa',
            'category' => 'rendimenti',
            'label' => 'Rendimento Treasury 30 anni',
            'subject' => 'Il rendimento del Treasury a 30 anni',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'fred', 'series' => 'DGS30'],
        ],
        'us_yield_curve_10y_2y' => [
            'region' => 'usa',
            'category' => 'rendimenti',
            'label' => 'Curva dei rendimenti (10Y − 2Y)',
            'subject' => 'La curva dei rendimenti USA (10Y − 2Y)',
            'unit' => 'p.p.',
            'good_direction' => 'up',
            'target' => 0.0,
            'derive' => ['type' => 'spread', 'minuend' => 'us_yield_10y', 'subtrahend' => 'us_yield_2y'],
        ],
        'ea_hicp_yoy' => [
            'region' => 'eurozone',
            'category' => 'inflazione',
            'label' => 'Inflazione Eurozona (HICP)',
            'subject' => "L'inflazione dell'Eurozona",
            'unit' => '%',
            'good_direction' => 'down',
            'target' => 2.0,
            'target_institution' => 'della BCE',
            'fetch' => ['source' => 'ecb', 'series' => 'ICP/M.U2.N.000000.4.ANR'],
        ],
        'ea_deposit_rate' => [
            'region' => 'eurozone',
            'category' => 'tassi',
            'label' => 'Tasso di deposito BCE',
            'subject' => 'Il tasso di deposito BCE',
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'fetch' => ['source' => 'ecb', 'series' => 'FM/D.U2.EUR.4F.KR.DFR.LEV'],
        ],
        'ea_unemployment' => [
            'region' => 'eurozone',
            'category' => 'lavoro',
            'label' => 'Disoccupazione Eurozona',
            'subject' => "La disoccupazione nell'Eurozona",
            'unit' => '%',
            'good_direction' => 'down',
            'target' => null,
            'fetch' => ['source' => 'ecb', 'series' => 'LFSI/M.I9.S.UNEHRT.TOTAL0.15_74.T'],
        ],
        'ea_gdp_growth' => [
            'region' => 'eurozone',
            'category' => 'crescita',
            'label' => 'Crescita PIL Eurozona (annuale)',
            'subject' => "La crescita del PIL dell'Eurozona",
            'unit' => '%',
            'good_direction' => 'up',
            'target' => null,
            'period_note' => 'variazione annua',
            'fetch' => ['source' => 'ecb', 'series' => 'MNA/Q.Y.I9.W2.S1.S1.B.B1GQ._Z._Z._Z.EUR.LR.GY'],
        ],
        'ea_industrial_production_index' => [
            'region' => 'eurozone',
            'category' => 'produzione_consumi',
            'label' => 'Produzione industriale Eurozona (indice)',
            'subject' => "La produzione industriale dell'Eurozona",
            'unit' => 'indice',
            'good_direction' => null,
            'target' => null,
            'display' => false,
            'fetch' => ['source' => 'ecb', 'series' => 'STS/M.I9.Y.PROD.NS0020.4.000'],
        ],
        'ea_industrial_production' => [
            'region' => 'eurozone',
            'category' => 'produzione_consumi',
            'label' => 'Produzione industriale Eurozona',
            'subject' => "La produzione industriale dell'Eurozona",
            'unit' => '%',
            'good_direction' => 'up',
            'target' => null,
            'period_note' => 'variazione annua',
            'derive' => ['type' => 'yoy', 'from' => 'ea_industrial_production_index'],
        ],
        'ea_m3' => [
            'region' => 'eurozone',
            'category' => 'massa_monetaria',
            'label' => 'Massa monetaria Eurozona (M3)',
            'subject' => "La massa monetaria dell'Eurozona (M3)",
            'unit' => '%',
            'good_direction' => null,
            'target' => null,
            'period_note' => 'variazione annua',
            'fetch' => ['source' => 'ecb', 'series' => 'BSI/M.U2.Y.V.M30.X.I.U2.2300.Z01.A'],
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fetchable(): array
    {
        return array_filter(self::ALL, fn (array $meta) => isset($meta['fetch']));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function derived(): array
    {
        return array_filter(self::ALL, fn (array $meta) => isset($meta['derive']));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function displayable(): array
    {
        return array_filter(self::ALL, fn (array $meta) => ($meta['display'] ?? true) === true);
    }
}
