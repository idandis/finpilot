<?php

namespace App\Contracts;

use Illuminate\Support\Carbon;

readonly class FetchedPrice
{
    public function __construct(
        public float $price,
        public Carbon $date,
    ) {}
}
