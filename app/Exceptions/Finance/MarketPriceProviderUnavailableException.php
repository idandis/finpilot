<?php

namespace App\Exceptions\Finance;

use RuntimeException;

/**
 * Thrown by MarketPriceProvider::resolveSymbol() when the call could not
 * even be attempted (missing API key, or no budget left) - as opposed to
 * returning null, which means the provider was reached but found no match.
 * Callers must not treat this as a permanent resolution failure: it's
 * transient and the symbol should be retried on a later run.
 */
class MarketPriceProviderUnavailableException extends RuntimeException {}
