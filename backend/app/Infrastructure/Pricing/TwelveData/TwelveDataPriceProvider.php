<?php

namespace App\Infrastructure\Pricing\TwelveData;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Infrastructure\Pricing\OpenFigi\OpenFigiInstrumentResolver;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Twelve Data API — stocks, ETFs, ETNs.
 *
 * Free tier: 800 requests/day, 8 requests/minute.
 * Endpoint: GET /price?symbol={symbol}&apikey={key}&exchange={exchange}&outputsize=1
 *
 * Symbol convention: investment.symbol holds the ticker (e.g. "AAPL", "MSFP.PAR").
 * Exchange suffix: use Twelve Data exchange codes (e.g. "PAR" for Euronext Paris)
 * encoded as "TICKER.EXCHANGE" in the symbol field.
 *
 * Prices are returned in the instrument's native currency (USD for US stocks,
 * EUR for Euronext, etc.). FX conversion to the investment's target currency
 * is handled by the pricing service if needed (P3).
 */
final class TwelveDataPriceProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://api.twelvedata.com';

    private const CACHE_TTL = 300; // 5 min price cache

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly int $rateLimitPerMinute = 8,
        private readonly ?OpenFigiInstrumentResolver $openFigiResolver = null,
    ) {}

    public function code(): string
    {
        return 'twelve_data';
    }

    public function supports(Investment $investment): bool
    {
        $code = $investment->assetType->code;

        return in_array($code, ['stock', 'etf', 'etn_crypto'], true)
            && (filled($investment->symbol) || filled($investment->isin));
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        if (! $this->apiKey) {
            throw new ProviderUnavailableException($this->code(), 'PROVIDER_TWELVEDATA_KEY non configurée.');
        }

        $symbol = $this->resolveInstrument($investment);
        if ($symbol === null) {
            $identifier = $investment->symbol ?: $investment->isin;
            return PriceResult::error(
                $this->code(),
                "Twelve Data: impossible de résoudre [{$identifier}] en instrument coté.",
            );
        }
        $cacheKey = sprintf(
            'twelvedata:price:%s:%s:%s',
            $symbol['ticker'],
            $symbol['exchange'],
            strtoupper($investment->currency),
        );

        $cached = cache()->get($cacheKey);
        if ($cached !== null) {
            return PriceResult::success(
                price: (float) $cached['price'],
                currency: $cached['currency'],
                source: $this->code(),
                rawPayload: $cached,
            );
        }

        $params = [
            'symbol' => $symbol['ticker'],
            'apikey' => $this->apiKey,
            'outputsize' => 1,
        ];
        if ($symbol['exchange']) {
            $params['exchange'] = $symbol['exchange'];
        }

        $this->acquireRateLimit();

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/price', $params);
        } catch (\Throwable $e) {
            Log::warning('Twelve Data HTTP error', ['symbol' => $investment->symbol, 'error' => $e->getMessage()]);
            throw new ProviderUnavailableException($this->code(), $e->getMessage());
        }

        if (! $response->successful()) {
            Log::warning('Twelve Data non-2xx', ['status' => $response->status(), 'body' => $response->body()]);
            throw new ProviderUnavailableException($this->code(), "HTTP {$response->status()}: {$response->body()}");
        }

        $data = $response->json();

        // Twelve Data returns {"status": "error", "message": "..."} on failure.
        if (isset($data['status']) && $data['status'] === 'error') {
            $msg = $data['message'] ?? 'Unknown error';
            Log::warning('Twelve Data API error', ['symbol' => $investment->symbol, 'message' => $msg]);

            return PriceResult::error($this->code(), "Twelve Data: {$msg}");
        }

        $price = $data['price'] ?? null;
        if ($price === null) {
            return PriceResult::error($this->code(), "Twelve Data: prix non trouvé pour [{$investment->symbol}].");
        }

        // Twelve Data doesn't return currency in /price endpoint.
        // We infer it from the exchange or default to USD.
        $currency = $this->inferCurrency($symbol['exchange'], $investment->currency);

        $payload = [
            'price' => $price,
            'currency' => $currency,
            'symbol' => $investment->symbol,
            'exchange' => $symbol['exchange'],
        ];

        cache()->put($cacheKey, $payload, self::CACHE_TTL);

        return PriceResult::success(
            price: (float) $price,
            currency: $currency,
            source: $this->code(),
            rawPayload: $data,
        );
    }

    private function acquireRateLimit(): void
    {
        $key = 'pricing:twelve_data';

        if (RateLimiter::tooManyAttempts($key, $this->rateLimitPerMinute)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new ProviderUnavailableException(
                $this->code(),
                "rate limit atteint, nouvel essai dans {$retryAfter}s.",
            );
        }

        RateLimiter::hit($key, 60);
    }

    /**
     * Parse a symbol that may contain an exchange suffix: "TICKER.EXCHANGE".
     * If no suffix, the investment's symbol is used as-is with the default exchange.
     *
     * @return array{ticker: string, exchange: string}
     */
    private function parseSymbol(?string $symbol): array
    {
        $symbol = trim($symbol ?? '');
        $parts = explode('.', $symbol);

        if (count($parts) >= 2) {
            $exchange = strtoupper(end($parts));
            $ticker = implode('.', array_slice($parts, 0, -1));

            return ['ticker' => $ticker, 'exchange' => $exchange];
        }

        return ['ticker' => $symbol, 'exchange' => ''];
    }

    private function resolveInstrument(Investment $investment): ?array
    {
        if (filled($investment->symbol)) {
            return $this->parseSymbol($investment->symbol);
        }

        if (filled($investment->isin) && $this->openFigiResolver) {
            $resolved = $this->openFigiResolver->resolveIsin((string) $investment->isin);

            if ($resolved !== null && filled($resolved['ticker'] ?? null)) {
                return [
                    'ticker' => (string) $resolved['ticker'],
                    'exchange' => (string) ($resolved['exchCode'] ?? ''),
                ];
            }
        }

        foreach (array_values(array_filter([
            filled($investment->name) ? trim((string) $investment->name) : null,
            filled($investment->isin) ? strtoupper(trim((string) $investment->isin)) : null,
        ])) as $term) {
            $resolved = $this->resolveFromSearch($term, $investment);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    private function resolveFromSearch(string $query, Investment $investment): ?array
    {
        $cacheKey = 'twelvedata:search:'.md5(mb_strtolower(trim($query)));
        $cached = cache()->get($cacheKey);

        if (is_array($cached)) {
            return $this->pickBestSearchResult($cached, $investment);
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/symbol_search', [
                    'symbol' => $query,
                    'apikey' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Twelve Data search HTTP error', ['query' => $query, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Twelve Data search non-2xx', ['query' => $query, 'status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $payload = $response->json();
        $results = is_array($payload['data'] ?? null) ? array_values($payload['data']) : [];
        cache()->put($cacheKey, $results, self::CACHE_TTL);

        return $this->pickBestSearchResult($results, $investment);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function pickBestSearchResult(array $results, Investment $investment): ?array
    {
        $best = null;
        $bestScore = null;
        $preferred = [
            'Euronext' => 80,
            'XPAR' => 78,
            'XETR' => 74,
            'XMIL' => 72,
            'XAMS' => 70,
            'XLON' => 60,
            'XBUD' => 55,
            'XWBO' => 50,
            'XBRU' => 50,
            'XSWX' => 50,
        ];

        $queryName = mb_strtolower(trim((string) ($investment->name ?? '')));
        $queryIsin = mb_strtolower(trim((string) ($investment->isin ?? '')));
        $queryCurrency = strtoupper(trim((string) $investment->currency));

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $symbol = trim((string) ($row['symbol'] ?? ''));
            $exchange = trim((string) ($row['exchange'] ?? ''));
            $instrumentName = mb_strtolower(trim((string) ($row['instrument_name'] ?? '')));
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));

            if ($symbol === '') {
                continue;
            }

            $score = 0;

            if ($queryName !== '' && $instrumentName === $queryName) {
                $score += 100;
            } elseif ($queryName !== '' && str_contains($instrumentName, $queryName)) {
                $score += 90;
            } elseif ($queryIsin !== '' && str_contains(mb_strtolower($symbol.' '.$instrumentName), $queryIsin)) {
                $score += 70;
            } else {
                $score += 25;
            }

            if ($currency === $queryCurrency) {
                $score += 20;
            }

            if (isset($preferred[$exchange])) {
                $score += $preferred[$exchange];
            } elseif (isset($preferred[(string) ($row['mic_code'] ?? '')])) {
                $score += $preferred[(string) ($row['mic_code'] ?? '')];
            }

            if (str_contains($symbol, '.')) {
                $score += 5;
            }

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'ticker' => $symbol,
                    'exchange' => $exchange ?: (string) ($row['mic_code'] ?? ''),
                ];
            }
        }

        return $best;
    }

    /**
     * Infer the price currency from the exchange code.
     * Falls back to the investment's configured currency.
     */
    private function inferCurrency(string $exchange, string $fallback): string
    {
        return match ($exchange) {
            'PAR', 'XPAR', 'AMS', 'XAMS', 'BRU', 'XBRU', 'LIS', 'XLIS', 'XETR', 'FRA', 'XFRA', 'MIL', 'XMIL', 'MTA', 'XMTA', 'Euronext' => 'EUR',
            'LSE' => 'GBP',
            'TSX' => 'CAD',
            'ASX' => 'AUD',
            'SSE', 'SZSE' => 'CNY',
            'TSE' => 'JPY',
            default => $fallback,
        };
    }
}
