<?php

namespace App\Services\Finance;

/**
 * The fixed set of reasons a user can check off when explaining why they
 * bought something, shown as checkboxes on the Decision Journal form. Same
 * "fixed list, free notes alongside it" shape as BuffettQuestions, just
 * multi-select instead of yes/no per item.
 */
class InvestmentMotivations
{
    public const ALL = [
        ['key' => 'undervaluation', 'label' => 'Valutazione interessante / sottovalutata'],
        ['key' => 'growth', 'label' => 'Trend di crescita solido'],
        ['key' => 'quality', 'label' => 'Qualità del business (vantaggio competitivo, management, marginalità)'],
        ['key' => 'dividend', 'label' => 'Dividendo interessante'],
        ['key' => 'diversification', 'label' => 'Diversificazione del portafoglio'],
        ['key' => 'technical', 'label' => 'Momentum / analisi tecnica'],
        ['key' => 'external_view', 'label' => 'Consiglio o analisi esterna'],
        ['key' => 'other', 'label' => 'Altro'],
    ];

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_column(self::ALL, 'key');
    }
}
