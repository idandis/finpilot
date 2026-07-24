<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

readonly class FetchedNewsArticle
{
    /**
     * @param  array<int, string>|null  $tags
     */
    public function __construct(
        public string $title,
        public Carbon $publishedAt,
        public ?string $content = null,
        public ?string $url = null,
        public ?float $sentimentPolarity = null,
        public ?array $tags = null,
    ) {}
}
