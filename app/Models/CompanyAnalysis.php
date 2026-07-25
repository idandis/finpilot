<?php

namespace App\Models;

use Database\Factories\CompanyAnalysisFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $symbol
 * @property string|null $current_price
 * @property string|null $market_cap
 * @property string|null $revenue_growth
 * @property string|null $eps_growth
 * @property string|null $revenue_cagr_5y
 * @property string|null $eps_cagr_5y
 * @property string|null $free_cash_flow
 * @property string|null $fcf_margin
 * @property string|null $operating_margin
 * @property string|null $net_margin
 * @property string|null $gross_margin
 * @property string|null $roe
 * @property string|null $roic
 * @property string|null $debt_to_ebitda
 * @property string|null $interest_coverage
 * @property string|null $current_ratio
 * @property string|null $pe_ratio
 * @property string|null $ev_to_ebitda
 * @property string|null $ev_to_fcf
 * @property string|null $price_to_sales
 * @property string|null $peg_ratio
 * @property string|null $fcf_yield
 * @property string|null $fair_value
 * @property string|null $historical_comparison
 * @property string|null $competitor_comparison
 * @property string|null $indicators_currency
 * @property Carbon|null $indicators_fetched_at
 * @property Carbon|null $price_history_fetched_at
 * @property array<int, array{key: string, answer: bool|null, notes: string|null}>|null $buffett_answers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'symbol', 'current_price', 'market_cap',
    'revenue_growth', 'eps_growth', 'revenue_cagr_5y', 'eps_cagr_5y',
    'free_cash_flow', 'fcf_margin', 'operating_margin', 'net_margin', 'gross_margin', 'roe', 'roic',
    'debt_to_ebitda', 'interest_coverage', 'current_ratio',
    'pe_ratio', 'ev_to_ebitda', 'ev_to_fcf', 'price_to_sales', 'peg_ratio', 'fcf_yield', 'fair_value',
    'historical_comparison', 'competitor_comparison', 'indicators_currency', 'indicators_fetched_at',
    'price_history_fetched_at', 'buffett_answers',
])]
class CompanyAnalysis extends Model
{
    /** @use HasFactory<CompanyAnalysisFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_price' => 'decimal:4',
            'market_cap' => 'decimal:2',
            'revenue_growth' => 'decimal:6',
            'eps_growth' => 'decimal:6',
            'revenue_cagr_5y' => 'decimal:6',
            'eps_cagr_5y' => 'decimal:6',
            'free_cash_flow' => 'decimal:2',
            'fcf_margin' => 'decimal:6',
            'operating_margin' => 'decimal:6',
            'net_margin' => 'decimal:6',
            'gross_margin' => 'decimal:6',
            'roe' => 'decimal:6',
            'roic' => 'decimal:6',
            'debt_to_ebitda' => 'decimal:4',
            'interest_coverage' => 'decimal:4',
            'current_ratio' => 'decimal:4',
            'pe_ratio' => 'decimal:4',
            'ev_to_ebitda' => 'decimal:4',
            'ev_to_fcf' => 'decimal:4',
            'price_to_sales' => 'decimal:4',
            'peg_ratio' => 'decimal:4',
            'fcf_yield' => 'decimal:4',
            'fair_value' => 'decimal:2',
            'indicators_fetched_at' => 'datetime',
            'price_history_fetched_at' => 'datetime',
            'buffett_answers' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
