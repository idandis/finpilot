<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un giro di soldi tra i propri conti: dal conto corrente alla carta, o dal
 * conto al portafoglio quando si preleva. Il budget non lo conta come spesa.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $recorded_by_user_id
 * @property int|null $from_financial_account_id
 * @property int|null $to_financial_account_id
 * @property string $amount
 * @property string|null $description
 * @property Carbon $transferred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccountTransfer extends Model
{
    protected $fillable = [
        'user_id',
        'recorded_by_user_id',
        'from_financial_account_id',
        'to_financial_account_id',
        'amount',
        'description',
        'transferred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transferred_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Chi l'ha scritto: in un budget condiviso il trasferimento è del
     * proprietario, ma può averlo segnato chiunque abbia accesso.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'from_financial_account_id');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_financial_account_id');
    }
}
