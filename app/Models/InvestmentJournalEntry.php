<?php

namespace App\Models;

use Database\Factories\InvestmentJournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A manually written note on the Investment's timeline (Journal tab),
 * optionally pinned to the specific buy/sell Transaction it explains. The
 * timeline itself also surfaces buy/increase/reduce/sell events derived
 * directly from Transaction rows (see JournalTimelineBuilder) - those are
 * never stored here, only the user's own annotations are.
 *
 * @property int $id
 * @property int $investment_id
 * @property int|null $transaction_id
 * @property string $entry_type
 * @property string $note
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['transaction_id', 'entry_type', 'note', 'occurred_at'])]
class InvestmentJournalEntry extends Model
{
    /** @use HasFactory<InvestmentJournalEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
