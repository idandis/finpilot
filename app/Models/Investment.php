<?php

namespace App\Models;

use Database\Factories\InvestmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The Decision Journal anchor for a single ISIN: why it was bought, what
 * would change the user's mind, and the conviction behind it - independent
 * of the derived Overview (positions/transactions), which stays keyed by
 * ISIN alone. Optionally linked to a CompanyAnalysis so the Fundamentals tab
 * can reuse its FMP-backed indicators, scoring and valuation verdict instead
 * of fetching anything new.
 *
 * @property int $id
 * @property int $user_id
 * @property string $isin
 * @property int|null $company_analysis_id
 * @property array<int, string>|null $motivation_reasons
 * @property string|null $motivation_note
 * @property string|null $thesis
 * @property string|null $sell_conditions
 * @property string|null $time_horizon
 * @property int|null $initial_confidence
 * @property int|null $current_confidence
 * @property Carbon|null $next_review_date
 * @property string|null $next_review_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'isin', 'company_analysis_id', 'motivation_reasons', 'motivation_note',
    'thesis', 'sell_conditions', 'time_horizon', 'initial_confidence', 'current_confidence',
    'next_review_date', 'next_review_note',
])]
class Investment extends Model
{
    /** @use HasFactory<InvestmentFactory> */
    use HasFactory;

    public const TIME_HORIZONS = ['short', 'medium', 'long'];

    protected function casts(): array
    {
        return [
            'motivation_reasons' => 'array',
            'initial_confidence' => 'integer',
            'current_confidence' => 'integer',
            'next_review_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CompanyAnalysis, $this>
     */
    public function companyAnalysis(): BelongsTo
    {
        return $this->belongsTo(CompanyAnalysis::class);
    }

    /**
     * @return HasMany<InvestmentReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(InvestmentReview::class);
    }

    /**
     * @return HasMany<InvestmentJournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(InvestmentJournalEntry::class);
    }

    /**
     * @return HasMany<InvestmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(InvestmentEvent::class);
    }
}
