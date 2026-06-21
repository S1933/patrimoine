<?php

namespace App\Infrastructure\Pricing\CoinGecko;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CoinGecko API v3 — free tier (~30 req/min, no key required).
 * Endpoint: GET /simple/price?ids={id}&vs_currencies={ccy}
 *
 * Symbol mapping: stored in the investment's `symbol` field as a CoinGecko coin ID
 * (e.g. "bitcoin", "ethereum"). For tickers like "BTC", a /coins/list lookup is done
 * on demand and cached.
 */
final class CoinGeckoPriceProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://api.coingecko.com/api/v3';

    private const CACHE_TTL = 300; // 5 min cache for symbol->id mapping

    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function code(): string
    {
        return 'coingecko';
    }

    public function supports(Investment $investment): bool
    {
        return $investment->assetType->code === 'crypto'
            && ! empty($investment->symbol);
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        $coinId = $this->resolveCoinId($investment->symbol);

        if ($coinId === null) {
            return PriceResult::error(
                $this->code(),
                "CoinGecko: impossible de résoudre le symbole [{$investment->symbol}] en coin ID.",
            );
        }

        $currency = strtolower($targetCurrency);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/simple/price', [
                    'ids' => $coinId,
                    'vs_currencies' => $currency,
                    'include_last_updated_at' => 'true',
                ]);
        } catch (\Throwable $e) {
            Log::warning('CoinGecko HTTP error', ['symbol' => $investment->symbol, 'error' => $e->getMessage()]);
            throw new ProviderUnavailableException($this->code(), $e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning('CoinGecko non-2xx', ['status' => $response->status(), 'body' => $response->body()]);
            throw new ProviderUnavailableException(
                $this->code(),
                "HTTP {$response->status()}: {$response->body()}",
            );
        }

        $data = $response->json();

        if (! isset($data[$coinId][$currency])) {
            return PriceResult::error(
                $this->code(),
                "CoinGecko: prix non trouvé pour [{$coinId}] en [{$currency}].",
            );
        }

        return PriceResult::success(
            price: (float) $data[$coinId][$currency],
            currency: strtoupper($currency),
            source: $this->code(),
            rawPayload: [
                'coin_id' => $coinId,
                'price' => (float) $data[$coinId][$currency],
                'currency' => strtoupper($currency),
                'last_updated_at' => $data[$coinId]['last_updated_at'] ?? null,
            ],
        );
    }

    /**
     * Resolve a symbol (could be a CoinGecko coin ID or a ticker like "BTC")
     * to a CoinGecko coin ID.
     */
    private function resolveCoinId(string $symbol): ?string
    {
        $symbol = trim(strtolower($symbol));

        // Common shortcuts: if the symbol is already a known coin ID, use it directly.
        $knownIds = ['bitcoin', 'ethereum', 'litecoin', 'ripple', 'cardano', 'solana', 'polkadot', 'chainlink', 'dogecoin', 'uniswap'];
        if (in_array($symbol, $knownIds, true)) {
            return $symbol;
        }

        // Tickers -> coin ID via /coins/list (cached).
        return cache()->remember("coingecko:coinid:{$symbol}", self::CACHE_TTL, function () use ($symbol) {
            try {
                $response = Http::withHeaders($this->headers())
                    ->timeout(15)
                    ->get(self::BASE_URL.'/coins/list');

                if (! $response->successful()) {
                    return null;
                }

                $coins = $response->json();
                foreach ($coins as $coin) {
                    if (strtolower($coin['symbol'] ?? '') === $symbol) {
                        return $coin['id'];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CoinGecko coins/list error', ['error' => $e->getMessage()]);
            }

            return null;
        });
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($this->apiKey) {
            $headers['X-CG-Demo-API-Key'] = $this->apiKey;
        }

        return $headers;
    }
}
