<?php

namespace App\Infrastructure\Pricing\Finnhub;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Infrastructure\Pricing\OpenFigi\OpenFigiInstrumentResolver;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finnhub API — fallback for stocks, ETFs, ETNs.
 *
 * Free tier: 60 calls/minute.
 * Endpoint: GET /quote?symbol={symbol}&token={key}
 * Returns: {"c": current_price, "h": high, "l": low, "o": open, "pc": prev_close, ...}
 *
 * Prices returned in USD for US-listed instruments.
 * Symbol convention: standard ticker (AAPL, MSFT, etc.) — no exchange suffix.
 */
final class FinnhubPriceProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://finnhub.io/api/v1';

    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?OpenFigiInstrumentResolver $openFigiResolver = null,
    ) {}

    public function code(): string
    {
        return 'finnhub';
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
            throw new ProviderUnavailableException($this->code(), 'PROVIDER_FINNHUB_KEY non configurée.');
        }

        $resolved = $this->resolveInstrument($investment);
        if ($resolved === null) {
            $identifier = $investment->symbol ?: $investment->isin;
            return PriceResult::error(
                $this->code(),
                "Finnhub: impossible de résoudre [{$identifier}] en instrument coté.",
            );
        }
        $ticker = $resolved['ticker'];
        $currency = $resolved['currency'] ?? $this->inferCurrencyFromSymbol($ticker, 'USD');

        try {
            $quoteResponse = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/quote', [
                    'symbol' => $ticker,
                    'token' => $this->apiKey,
                ]);

            $candleResponse = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/stock/candle', [
                    'symbol' => $ticker,
                    'resolution' => 'D',
                    'from' => now()->subYear()->timestamp,
                    'to' => now()->timestamp,
                    'token' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Finnhub HTTP error', ['symbol' => $ticker, 'error' => $e->getMessage()]);
            throw new ProviderUnavailableException($this->code(), $e->getMessage());
        }

        if (! $quoteResponse->successful()) {
            Log::warning('Finnhub non-2xx', ['status' => $quoteResponse->status(), 'body' => $quoteResponse->body()]);
            throw new ProviderUnavailableException($this->code(), "HTTP {$quoteResponse->status()}: {$quoteResponse->body()}");
        }

        $quote = $quoteResponse->json();
        $price = $quote['c'] ?? null;

        if ($price === null || $price === 0) {
            return PriceResult::error(
                $this->code(),
                "Finnhub: prix non trouvé pour [{$ticker}] (c=0 ou absent).",
            );
        }

        $candle = $candleResponse->successful() ? $candleResponse->json() : null;
        $metrics = $this->buildMetrics($quote, $candle);

        return PriceResult::success(
            price: (float) $price,
            currency: $currency,
            source: $this->code(),
            rawPayload: [
                'resolved' => $resolved,
                'quote' => $quote,
                'candle' => $candle,
                'metrics' => $metrics,
            ],
        );
    }

    private function resolveInstrument(Investment $investment): ?array
    {
        if (filled($investment->symbol)) {
            $symbol = trim((string) $investment->symbol);
            $parts = explode('.', $symbol);
            $ticker = count($parts) >= 2 ? $parts[0] : $symbol;

            return [
                'source' => 'symbol',
                'ticker' => $ticker,
                'exchange' => count($parts) >= 2 ? $parts[1] : null,
                'isin' => $investment->isin,
                'currency' => $this->inferCurrencyFromSymbol($symbol, 'USD'),
            ];
        }

        if (filled($investment->isin) && $this->openFigiResolver) {
            $resolved = $this->openFigiResolver->resolveIsin((string) $investment->isin);

            if ($resolved !== null && filled($resolved['ticker'] ?? null)) {
                return [
                    'source' => 'isin',
                    'ticker' => (string) $resolved['ticker'],
                    'exchange' => $resolved['exchCode'] ?? null,
                    'isin' => $resolved['isin'] ?? $investment->isin,
                    'figi' => $resolved['figi'] ?? null,
                    'name' => $resolved['name'] ?? null,
                    'marketSector' => $resolved['marketSector'] ?? null,
                    'securityType' => $resolved['securityType'] ?? null,
                    'currency' => $this->inferCurrencyFromExchange((string) ($resolved['exchCode'] ?? ''), 'USD'),
                ];
            }
        }

        foreach ($this->searchQueries($investment) as $query) {
            $resolved = $this->resolveFromSearch($query, $investment);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function searchQueries(Investment $investment): array
    {
        $queries = [];

        if (filled($investment->isin)) {
            $queries[] = strtoupper(trim((string) $investment->isin));
        }

        $name = trim((string) ($investment->name ?? ''));
        if ($name !== '') {
            $tokens = preg_split('/\s+/', $name) ?: [];
            $compact = array_slice(array_values(array_filter($tokens, fn ($token) => mb_strlen($token) > 2)), 0, 4);
            if ($compact) {
                $queries[] = implode(' ', $compact);
            }
            $queries[] = $name;
        }

        return array_values(array_unique(array_filter(array_map('trim', $queries))));
    }

    private function resolveFromSearch(string $query, Investment $investment): ?array
    {
        $cacheKey = 'finnhub:search:'.md5(mb_strtolower(trim($query)));
        $cached = cache()->get($cacheKey);
        $results = is_array($cached) ? $cached : null;

        if ($results === null) {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 1000)
                    ->get(self::BASE_URL.'/search', [
                        'q' => $query,
                        'token' => $this->apiKey,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Finnhub search HTTP error', ['query' => $query, 'error' => $e->getMessage()]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('Finnhub search non-2xx', ['query' => $query, 'status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $payload = $response->json();
            $results = is_array($payload['result'] ?? null) ? array_values($payload['result']) : [];
            cache()->put($cacheKey, $results, 86400);
        }

        $candidates = $this->expandSearchResults($results, $investment);

        return $this->pickBestSearchResult($candidates, $investment);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function expandSearchResults(array $results, Investment $investment): array
    {
        $seenQueries = [];
        $pending = [];
        $queue = [];

        foreach ($results as $row) {
            $queue[] = $row;
        }

        while ($queue) {
            $row = array_shift($queue);
            if (! is_array($row)) {
                continue;
            }

            $symbol = trim((string) ($row['symbol'] ?? ''));
            if ($symbol === '' || isset($seenQueries[$symbol])) {
                continue;
            }

            $seenQueries[$symbol] = true;
            $pending[] = $row;

            $root = $this->rootSymbol($symbol);
            if ($root !== '' && ! isset($seenQueries[$root])) {
                $seenQueries[$root] = true;
                $rootResults = $this->searchOnce($root);
                foreach ($rootResults as $childRow) {
                    $queue[] = $childRow;
                }
            }
        }

        return $pending;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchOnce(string $query): array
    {
        $cacheKey = 'finnhub:search:'.md5(mb_strtolower(trim($query)));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/search', [
                    'q' => $query,
                    'token' => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Finnhub search HTTP error', ['query' => $query, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        $results = is_array($payload['result'] ?? null) ? array_values($payload['result']) : [];
        cache()->put($cacheKey, $results, 86400);

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    private function pickBestSearchResult(array $results, Investment $investment): ?array
    {
        $best = null;
        $bestScore = null;
        $targetCurrency = strtoupper(trim((string) $investment->currency));
        $preferredSuffixes = match ($targetCurrency) {
            'EUR' => ['.PA' => 120, '.DE' => 110, '.MI' => 105, '.AS' => 100, '.SW' => 60],
            'GBP' => ['.L' => 120, '.PA' => 70, '.DE' => 70, '.MI' => 70, '.SW' => 50],
            'CHF' => ['.SW' => 120, '.PA' => 70, '.DE' => 70, '.MI' => 70],
            default => ['.PA' => 80, '.DE' => 75, '.MI' => 75, '.L' => 70, '.SW' => 60],
        };
        $queryName = mb_strtolower(trim((string) ($investment->name ?? '')));
        $queryIsin = mb_strtolower(trim((string) ($investment->isin ?? '')));

        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $symbol = trim((string) ($row['symbol'] ?? ''));
            $description = mb_strtolower(trim((string) ($row['description'] ?? '')));
            $displaySymbol = trim((string) ($row['displaySymbol'] ?? $symbol));

            if ($symbol === '') {
                continue;
            }

            $score = 0;

            if ($queryName !== '' && $description === $queryName) {
                $score += 100;
            } elseif ($queryName !== '' && str_contains($description, $queryName)) {
                $score += 90;
            } elseif ($queryIsin !== '' && str_contains(mb_strtolower($description.' '.$symbol), $queryIsin)) {
                $score += 70;
            } else {
                $score += 25;
            }

            foreach ($preferredSuffixes as $suffix => $bonus) {
                if (str_ends_with($displaySymbol, $suffix) || str_ends_with($symbol, $suffix)) {
                    $score += $bonus;
                    break;
                }
            }

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'source' => 'search',
                    'ticker' => $symbol,
                    'exchange' => $this->exchangeFromSymbol($symbol),
                    'currency' => $this->inferCurrencyFromSymbol($symbol, $targetCurrency ?: 'USD'),
                    'name' => $row['description'] ?? null,
                    'displaySymbol' => $displaySymbol,
                ];
            }
        }

        return $best;
    }

    private function rootSymbol(string $symbol): string
    {
        $parts = explode('.', trim($symbol));

        return count($parts) >= 2 ? $parts[0] : trim($symbol);
    }

    private function exchangeFromSymbol(string $symbol): ?string
    {
        $parts = explode('.', trim($symbol));

        return count($parts) >= 2 ? end($parts) : null;
    }

    private function inferCurrencyFromExchange(string $exchange, string $fallback): string
    {
        return match (strtoupper(trim($exchange))) {
            'PA', 'PAR', 'DE', 'XETR', 'MI', 'LIS', 'AM', 'SWX' => 'EUR',
            'L' => 'GBP',
            'SW' => 'CHF',
            default => $fallback,
        };
    }

    private function inferCurrencyFromSymbol(string $symbol, string $fallback): string
    {
        $exchange = (string) ($this->exchangeFromSymbol($symbol) ?? '');
        $resolved = $this->inferCurrencyFromExchange($exchange, $fallback);

        return $resolved;
    }

    private function buildMetrics(array $quote, ?array $candle): array
    {
        $candle = is_array($candle) ? $candle : [];
        $closes = is_array($candle['c'] ?? null) ? array_values($candle['c']) : [];
        $highs = is_array($candle['h'] ?? null) ? array_values($candle['h']) : [];
        $lows = is_array($candle['l'] ?? null) ? array_values($candle['l']) : [];
        $volumes = is_array($candle['v'] ?? null) ? array_values($candle['v']) : [];

        return [
            'volume' => $volumes ? (float) end($volumes) : ($quote['v'] ?? null),
            'day_change' => $quote['d'] ?? null,
            'day_change_percent' => $quote['dp'] ?? null,
            'previous_close' => $quote['pc'] ?? null,
            'open' => $quote['o'] ?? null,
            'high' => $quote['h'] ?? null,
            'low' => $quote['l'] ?? null,
            'high_52w' => $highs ? max($highs) : null,
            'low_52w' => $lows ? min($lows) : null,
            'performance' => $this->buildPerformance($closes),
        ];
    }

    private function buildPerformance(array $closes): array
    {
        $windows = [
            '1w' => 5,
            '1m' => 21,
            '3m' => 63,
            '6m' => 126,
            '1y' => 252,
        ];

        $out = [];
        $count = count($closes);
        if ($count < 2) {
            return $out;
        }

        $current = (float) $closes[$count - 1];
        foreach ($windows as $label => $offset) {
            $index = $count - 1 - $offset;
            if ($index < 0 || ! isset($closes[$index])) {
                continue;
            }

            $base = (float) $closes[$index];
            if ($base <= 0) {
                continue;
            }

            $out[$label] = ($current - $base) / $base * 100;
        }

        return $out;
    }
}
