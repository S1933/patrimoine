<?php

namespace App\Http\Resources\Api\V1;

use App\Application\Valuation\InvestmentValuation;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Investment
 * @property \App\Models\AssetPrice|null $latestPrice
 */
class InvestmentResource extends JsonResource
{
    public function toArray($request): array
    {
        $valuation = app(InvestmentValuation::class);
        $latest = $this->whenLoaded('latestPrice', fn () => $this->latestPrice->first());

        $quantity = (float) $this->quantity;
        $currentPrice = $latest?->price !== null ? (float) $latest->price : null;
        $manualValue = $this->manual_value !== null ? (float) $this->manual_value : null;
        $purchasePrice = $this->purchase_price !== null ? (float) $this->purchase_price : null;

        $currentValue = $valuation->currentValue($this->resource, $currentPrice);
        $savingsYield = $valuation->savingsYield($this->resource);
        if ($currentPrice === null && $savingsYield !== null && $quantity > 0) {
            $currentPrice = $currentValue / $quantity;
        }
        $marketData = $this->marketData($latest?->raw_payload);

        $purchaseValue = $purchasePrice !== null ? $quantity * $purchasePrice : null;
        $pnlAbsolute = ($currentValue !== null && $purchaseValue !== null) ? $currentValue - $purchaseValue : null;
        $pnlPercent = ($currentValue !== null && $purchaseValue !== null && $purchaseValue > 0)
            ? ($currentValue - $purchaseValue) / $purchaseValue * 100
            : null;

        return [
            'id' => $this->id,
            'asset_type' => new AssetTypeResource($this->whenLoaded('assetType')),
            'asset_type_id' => $this->asset_type_id,
            'name' => $this->name,
            'isin' => $this->isin,
            'symbol' => $this->symbol,
            'quantity' => $quantity,
            'unit' => $this->unit,
            'geography' => $this->geography,
            'country_allocations' => $this->country_allocations,
            'sector_allocations' => $this->sector_allocations,
            'purchase_price' => $purchasePrice,
            'purchase_currency' => $this->purchase_currency,
            'purchase_date' => $this->purchase_date?->toIso8601String(),
            'manual_value' => $manualValue,
            'manual_value_updated_at' => $this->manual_value_updated_at?->toIso8601String(),
            'currency' => $this->currency,
            'provider_id' => $this->provider_id,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed
            'current_price' => $currentPrice,
            'current_price_fetched_at' => $latest?->fetched_at?->toIso8601String(),
            'current_price_source' => $latest?->source_status,
            'current_price_provider' => $this->whenLoaded('latestPrice.provider', fn () => $latest?->provider?->code),
            'current_value' => $currentValue,
            'purchase_value' => $purchaseValue,
            'pnl_absolute' => $pnlAbsolute,
            'pnl_percent' => $pnlPercent,
            'savings_yield' => $savingsYield,
            'market_data' => $marketData,
        ];
    }

    private function marketData(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $resolved = $payload['resolved'] ?? null;
        $quote = $payload['quote'] ?? null;
        $metrics = $payload['metrics'] ?? null;

        if (! is_array($resolved) && ! is_array($quote) && ! is_array($metrics)) {
            return null;
        }

        return [
            'source' => $resolved['source'] ?? null,
            'isin' => $resolved['isin'] ?? null,
            'ticker' => $resolved['ticker'] ?? null,
            'name' => $resolved['name'] ?? null,
            'exchange' => $resolved['exchange'] ?? ($resolved['exchCode'] ?? null),
            'volume' => $metrics['volume'] ?? ($quote['v'] ?? null),
            'day_change' => $metrics['day_change'] ?? ($quote['d'] ?? null),
            'day_change_percent' => $metrics['day_change_percent'] ?? ($quote['dp'] ?? null),
            'previous_close' => $metrics['previous_close'] ?? ($quote['pc'] ?? null),
            'high_52w' => $metrics['high_52w'] ?? null,
            'low_52w' => $metrics['low_52w'] ?? null,
            'performance' => $metrics['performance'] ?? null,
        ];
    }
}
