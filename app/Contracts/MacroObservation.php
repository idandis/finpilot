<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

readonly class MacroObservation
{
    public function __construct(
        public Carbon $date,
        public float $value,
        public ?Carbon $publishedAt = null,
    ) {}
}
