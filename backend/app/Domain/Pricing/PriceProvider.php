<?php

namespace App\Domain\Pricing;

use App\Models\AssetType;
use App\Models\Investment;

/**
 * Port: every external pricing source implements this interface.
 * The FallbackChain composes multiple implementations per asset type.
 */
interface PriceProvider
{
    /**
     * Canonical code matching price_providers.code (coingecko, goldapi, manual, ...).
     */
    public function code(): string;

    /**
     * Whether this provider can fetch a price for the given investment.
     */
    public function supports(Investment $investment): bool;

    /**
     * Fetch the current unit price for the investment, in the requested currency.
     *
     * @throws \App\Domain\Pricing\ProviderUnavailableException
     */
    public function fetch(Investment $investment, string $targetCurrency): PriceResult;
}
