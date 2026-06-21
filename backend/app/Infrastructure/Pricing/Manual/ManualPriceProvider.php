<?php

namespace App\Infrastructure\Pricing\Manual;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Models\Investment;

/**
 * Returns the user-entered manual_value as the current price.
 * Used for real estate, cash, savings accounts and assets that can't be priced externally.
 */
final class ManualPriceProvider implements PriceProviderInterface
{
    public function code(): string
    {
        return 'manual';
    }

    public function supports(Investment $investment): bool
    {
        return $investment->manual_value !== null;
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        $value = (float) $investment->manual_value;

        if ($value <= 0) {
            return PriceResult::error($this->code(), "Aucune valeur manuelle renseignée pour [{$investment->name}].");
        }

        // Manual value is stored as a total value, not a unit price.
        // Convert to unit price so that the pricing service treats all assets uniformly.
        $quantity = (float) $investment->quantity;
        $unitPrice = $quantity > 0 ? $value / $quantity : $value;

        return PriceResult::success(
            price: $unitPrice,
            currency: $investment->currency,
            source: $this->code(),
            rawPayload: ['manual_value' => $value, 'quantity' => $quantity],
        );
    }
}
