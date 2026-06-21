<?php

namespace App\Infrastructure\Pricing\Fallback;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Models\AssetPrice;
use App\Models\Investment;
use Illuminate\Support\Facades\Log;

/**
 * Composite provider that tries providers in priority order.
 * On failure (exception or error result), falls back to the next provider.
 * Final fallback: the last known price stored in asset_prices.
 */
final class FallbackChainProvider implements PriceProviderInterface
{
    /** @var array<PriceProviderInterface> */
    private array $providers;

    public function __construct(PriceProviderInterface ...$providers)
    {
        $this->providers = $providers;
    }

    public function code(): string
    {
        return 'fallback-chain';
    }

    public function supports(Investment $investment): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($investment)) {
                return true;
            }
        }

        return false;
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        $errors = [];

        foreach ($this->providers as $provider) {
            if (! $provider->supports($investment)) {
                continue;
            }

            try {
                $result = $provider->fetch($investment, $targetCurrency);

                if (! $result->isError()) {
                    return $result;
                }

                $errors[] = "[{$provider->code()}] {$result->errorMessage}";
            } catch (ProviderUnavailableException $e) {
                $errors[] = $e->getMessage();
                Log::info('Provider fallback', ['provider' => $provider->code(), 'reason' => $e->getMessage()]);

                continue;
            }
        }

        // Last resort: last known price from the database.
        $lastPrice = AssetPrice::where('investment_id', $investment->id)
            ->where('source_status', '!=', 'error')
            ->latest('fetched_at')
            ->first();

        if ($lastPrice) {
            return PriceResult::fallback(
                price: (float) $lastPrice->price,
                currency: $lastPrice->currency,
                source: $lastPrice->provider?->code ?? 'last-known',
                errorMessage: implode(' | ', $errors),
            );
        }

        return PriceResult::error(
            'fallback-chain',
            'Tous les providers ont échoué et aucun prix précédent n\'est disponible. '.implode(' | ', $errors),
        );
    }
}
