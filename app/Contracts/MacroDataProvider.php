<?php

namespace App\Contracts;

use Carbon\CarbonInterface;

interface MacroDataProvider
{
    /**
     * Fetch observations for a macro series, optionally only those on or
     * after $from (a full history fetch when null). Returns null when the
     * call itself failed (retry later, don't touch stored data), or an
     * empty array when it succeeded but the series has no observations in
     * range - callers must tell these two cases apart.
     *
     * @return array<int, MacroObservation>|null
     */
    public function fetchObservations(string $seriesId, ?CarbonInterface $from = null): ?array;
}
