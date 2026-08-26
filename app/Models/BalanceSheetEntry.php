<?php

namespace App\Models;

use Database\Factories\BalanceSheetEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $name
 * @property string|null $category
 * @property string|null $amount
 * @property string|null $cash_used
 * @property string|null $hours_per_month
 * @property string|null $time_kind
 * @property string|null $frequency
 * @property int|null $progress_percent
 * @property bool $active
 * @property int|null $linked_asset_id
 * @property int|null $linked_liability_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type', 'name', 'category', 'amount', 'cash_used', 'hours_per_month', 'time_kind',
    'frequency', 'progress_percent', 'active', 'linked_asset_id', 'linked_liability_id',
])]
class BalanceSheetEntry extends Model
{
    /** @use HasFactory<BalanceSheetEntryFactory> */
    use HasFactory;

    public const TYPES = ['income', 'expense', 'asset', 'liability', 'time', 'goal'];

    public const TIME_KINDS = ['consumes', 'frees'];

    public const FREQUENCIES = ['monthly', 'one_time'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_used' => 'decimal:2',
            'hours_per_month' => 'decimal:2',
            'progress_percent' => 'integer',
            'active' => 'boolean',
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
     * The asset this entry was generated for (income, liability, expense or time entries created from an asset purchase).
     *
     * @return BelongsTo<BalanceSheetEntry, $this>
     */
    public function linkedAsset(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_asset_id');
    }

    /**
     * The liability an expense's installment pays down.
     *
     * @return BelongsTo<BalanceSheetEntry, $this>
     */
    public function linkedLiabilityEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_liability_id');
    }

    /**
     * @return HasOne<BalanceSheetEntry, $this>
     */
    public function linkedIncome(): HasOne
    {
        return $this->hasOne(self::class, 'linked_asset_id')->where('type', 'income');
    }

    /**
     * @return HasOne<BalanceSheetEntry, $this>
     */
    public function linkedLiability(): HasOne
    {
        return $this->hasOne(self::class, 'linked_asset_id')->where('type', 'liability');
    }

    /**
     * @return HasOne<BalanceSheetEntry, $this>
     */
    public function linkedExpense(): HasOne
    {
        return $this->hasOne(self::class, 'linked_asset_id')->where('type', 'expense');
    }

    /**
     * @return HasOne<BalanceSheetEntry, $this>
     */
    public function linkedTime(): HasOne
    {
        return $this->hasOne(self::class, 'linked_asset_id')->where('type', 'time');
    }
}
