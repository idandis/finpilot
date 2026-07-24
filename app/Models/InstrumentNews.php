<?php

namespace App\Models;

use Database\Factories\InstrumentNewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $isin
 * @property Carbon $published_at
 * @property string $title
 * @property string|null $content
 * @property string|null $url
 * @property string|null $sentiment_polarity
 * @property array<int, string>|null $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['isin', 'published_at', 'title', 'content', 'url', 'sentiment_polarity', 'tags'])]
class InstrumentNews extends Model
{
    /** @use HasFactory<InstrumentNewsFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'sentiment_polarity' => 'decimal:4',
            'tags' => 'array',
        ];
    }
}
