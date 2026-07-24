<?php

namespace App\Contracts;

readonly class ResolvedSymbol
{
    public function __construct(
        public string $code,
        public string $exchange,
        public ?string $name = null,
        public ?string $currency = null,
    ) {}
}
