<?php

namespace App\Models;

use Database\Factories\PasswordEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $password_group_id
 * @property string $platform_name
 * @property string|null $username
 * @property string $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['platform_name', 'username', 'password'])]
class PasswordEntry extends Model
{
    /** @use HasFactory<PasswordEntryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Transparently encrypted/decrypted with the app's APP_KEY -
            // the database column only ever holds ciphertext.
            'password' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<PasswordGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(PasswordGroup::class, 'password_group_id');
    }
}
