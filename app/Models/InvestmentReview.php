<?php

namespace App\Models;

use Database\Factories\InvestmentReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single quarterly (or ad hoc) check-in on an Investment: what was
 * decided, how the conviction score moved, and why - kept as an append-only
 * history rather than overwriting the Investment's own current_confidence
 * in place.
 *
 * @property int $id
 * @property int $investment_id
 * @property int|null $investment_event_id
 * @property Carbon $review_date
 * @property string $decision
 * @property bool|null $thesis_still_valid
 * @property int|null $score_before
 * @property int|null $score_after
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['investment_event_id', 'review_date', 'decision', 'thesis_still_valid', 'score_before', 'score_after', 'note'])]
class InvestmentReview extends Model
{
    /** @use HasFactory<InvestmentReviewFactory> */
    use HasFactory;

    public const DECISIONS = ['hold', 'increase', 'reduce', 'sell', 'watch'];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'score_before' => 'integer',
            'score_after' => 'integer',
            'thesis_still_valid' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Investment, $this>
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    /**
     * @return BelongsTo<InvestmentEvent, $this>
     */
    public function investmentEvent(): BelongsTo
    {
        return $this->belongsTo(InvestmentEvent::class);
    }
}
