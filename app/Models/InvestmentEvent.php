<?php

namespace App\Models;

use Database\Factories\InvestmentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An objective, factual company update (earnings, guidance, an Investor Day,
 * an acquisition, a management change...) - deliberately free of any
 * "hold/sell" judgment. An InvestmentReview may optionally point back to the
 * event that triggered it, but an event never carries a decision itself.
 *
 * @property int $id
 * @property int $investment_id
 * @property string $event_type
 * @property string $title
 * @property Carbon $event_date
 * @property array<int, array{label: string, value: string}>|null $metrics
 * @property string|null $summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['event_type', 'title', 'event_date', 'metrics', 'summary'])]
class InvestmentEvent extends Model
{
    /** @use HasFactory<InvestmentEventFactory> */
    use HasFactory;

    public const EVENT_TYPES = [
        'earnings_quarterly', 'earnings_annual', 'guidance', 'investor_day',
        'acquisition', 'management_change', 'regulatory', 'other',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'metrics' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Investment, $this>
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
