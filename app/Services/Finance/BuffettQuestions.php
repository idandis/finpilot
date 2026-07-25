<?php

namespace App\Services\Finance;

/**
 * The fixed set of questions to answer before buying a company, as a
 * checklist that persists per company analysis. The list itself never
 * changes per-user - only the answers do (stored in
 * CompanyAnalysis::$buffett_answers).
 */
class BuffettQuestions
{
    public const ALL = [
        ['key' => 'understand_business', 'label' => 'Capisco perfettamente come guadagna?'],
        ['key' => 'exists_in_20_years', 'label' => 'Continuerà ad esistere tra 20 anni?'],
        ['key' => 'durable_moat', 'label' => 'Ha un vantaggio competitivo duraturo?'],
        ['key' => 'revenue_growing', 'label' => 'I ricavi crescono?'],
        ['key' => 'earnings_growing', 'label' => 'Gli utili crescono?'],
        ['key' => 'generates_cash', 'label' => 'Genera tanto cash?'],
        ['key' => 'low_debt', 'label' => 'Ha poco debito?'],
        ['key' => 'excellent_management', 'label' => 'Il management è eccellente?'],
        ['key' => 'reasonable_price', 'label' => 'Il prezzo è ragionevole?'],
        ['key' => 'happy_to_hold_10_years', 'label' => 'Sarei felice di tenerla per 10 anni?'],
    ];

    public static function keys(): array
    {
        return array_column(self::ALL, 'key');
    }
}
