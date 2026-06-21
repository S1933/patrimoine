<?php

namespace App\Infrastructure\Pricing;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Infrastructure\Pricing\CoinGecko\CoinGeckoPriceProvider;
use App\Infrastructure\Pricing\Fallback\FallbackChainProvider;
use App\Infrastructure\Pricing\Finnhub\FinnhubPriceProvider;
use App\Infrastructure\Pricing\GoldApi\GoldApiPriceProvider;
use App\Infrastructure\Pricing\Manual\ManualPriceProvider;
use App\Infrastructure\Pricing\YahooFinance\YahooFinancePriceProvider;
use App\Infrastructure\Pricing\TwelveData\TwelveDataPriceProvider;
use App\Models\Investment;

/**
 * Builds the appropriate FallbackChainProvider for a given investment
 * based on its asset type and configured providers.
 *
 * Chain order per asset type:
 *  - crypto:       CoinGecko → Manual → last-known
 *  - gold:         GoldAPI → Manual → last-known
 *  - stock/etf/etn_crypto: Twelve Data → Finnhub → Manual → last-known
 *  - real_estate/cash/livret_a/ldds/other: Manual → last-known
 */
final class PriceProviderFactory
{
    public function __construct(
        private readonly CoinGeckoPriceProvider $coinGecko,
        private readonly GoldApiPriceProvider $goldApi,
        private readonly TwelveDataPriceProvider $twelveData,
        private readonly FinnhubPriceProvider $finnhub,
        private readonly YahooFinancePriceProvider $yahooFinance,
        private readonly ManualPriceProvider $manual,
    ) {}

    public function forInvestment(Investment $investment): PriceProviderInterface
    {
        $code = $investment->assetType->code;
        $hasIsin = filled($investment->isin);
        $hasSymbol = filled($investment->symbol);

        return match ($code) {
            'crypto' => new FallbackChainProvider($this->coinGecko, $this->manual),
            'gold' => new FallbackChainProvider($this->goldApi, $this->manual),
            'stock', 'etf', 'etn_crypto' => $hasIsin
                ? new FallbackChainProvider($this->finnhub, $this->twelveData, $this->yahooFinance, $this->manual)
                : new FallbackChainProvider($this->twelveData, $this->finnhub, $this->yahooFinance, $this->manual),
            'real_estate', 'cash', 'livret_a', 'ldds', 'other' => new FallbackChainProvider($this->manual),
            default => new FallbackChainProvider($this->manual),
        };
    }
}
