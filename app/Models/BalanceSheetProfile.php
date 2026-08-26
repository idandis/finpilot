<?php

namespace App\Models;

use Database\Factories\BalanceSheetProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $cash_balance
 * @property int $closed_months
 * @property string $base_monthly_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['cash_balance', 'closed_months', 'base_monthly_hours'])]
class BalanceSheetProfile extends Model
{
    /** @use HasFactory<BalanceSheetProfileFactory> */
    use HasFactory;

    public const DEFAULT_BASE_MONTHLY_HOURS = 176;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cash_balance' => 'decimal:2',
            'base_monthly_hours' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return $user->balanceSheetProfile()->firstOrCreate([], [
            'cash_balance' => 0,
            'closed_months' => 0,
            'base_monthly_hours' => self::DEFAULT_BASE_MONTHLY_HOURS,
        ]);
    }
}
