<?php

namespace App\Infrastructure\Pricing\GoldApi;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * gold-api.com (free, no API key required) — returns XAU, XAG, XPT, XPD prices
 * in the requested currency.
 * Symbol convention: investment.symbol holds the metal code (XAU, XAG, ...).
 * For "gold" asset type, the unit is grams — the API returns prices per troy ounce (31.1034768 g),
 * so we convert to per-gram.
 */
final class GoldApiPriceProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://api.gold-api.com';

    private const GRAMS_PER_TROY_OUNCE = 31.1034768;

    private const METAL_CODES = ['XAU' => 'Gold', 'XAG' => 'Silver', 'XPT' => 'Platinum', 'XPD' => 'Palladium'];

    public function code(): string
    {
        return 'goldapi';
    }

    public function supports(Investment $investment): bool
    {
        return $investment->assetType->code === 'gold'
            && ! empty($investment->symbol)
            && array_key_exists(strtoupper($investment->symbol), self::METAL_CODES);
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        $metal = strtoupper($investment->symbol);
        $currency = strtoupper($targetCurrency);

        try {
            $response = Http::timeout(15)
                ->retry(2, 2000)
                ->get(self::BASE_URL."/price/{$metal}/{$currency}");
        } catch (\Throwable $e) {
            Log::warning('GoldAPI HTTP error', ['metal' => $metal, 'error' => $e->getMessage()]);
            throw new ProviderUnavailableException($this->code(), $e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning('GoldAPI non-2xx', ['status' => $response->status(), 'body' => $response->body()]);
            throw new ProviderUnavailableException(
                $this->code(),
                "HTTP {$response->status()}: {$response->body()}",
            );
        }

        $data = $response->json();
        $pricePerOunce = $data['price'] ?? null;

        if ($pricePerOunce === null) {
            return PriceResult::error(
                $this->code(),
                "GoldAPI: prix non trouvé pour [{$metal}] en [{$currency}].",
            );
        }

        $unit = strtolower($investment->unit);
        $price = (float) $pricePerOunce;
        if (in_array($unit, ['g', 'gram', 'gramme', 'grams'], true)) {
            $price = $price / self::GRAMS_PER_TROY_OUNCE;
        }

        return PriceResult::success(
            price: $price,
            currency: $currency,
            source: $this->code(),
            rawPayload: $data,
        );
    }
}
