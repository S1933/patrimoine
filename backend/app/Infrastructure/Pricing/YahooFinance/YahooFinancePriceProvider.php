<?php

namespace App\Infrastructure\Pricing\YahooFinance;

use App\Domain\Pricing\PriceProvider as PriceProviderInterface;
use App\Domain\Pricing\PriceResult;
use App\Domain\Pricing\ProviderUnavailableException;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class YahooFinancePriceProvider implements PriceProviderInterface
{
    private const BASE_URL = 'https://query1.finance.yahoo.com';

    private const SEARCH_TTL = 86400;

    private const CHART_TTL = 300;

    public function code(): string
    {
        return 'yahoo_finance';
    }

    public function supports(Investment $investment): bool
    {
        return in_array($investment->assetType->code, ['stock', 'etf', 'etn_crypto'], true)
            && (filled($investment->symbol) || filled($investment->isin) || filled($investment->name));
    }

    public function fetch(Investment $investment, string $targetCurrency): PriceResult
    {
        $resolved = $this->resolveInstrument($investment);
        if ($resolved === null) {
            $identifier = $investment->symbol ?: $investment->isin ?: $investment->name;

            return PriceResult::error(
                $this->code(),
                "Yahoo Finance: impossible de résoudre [{$identifier}] en instrument coté.",
            );
        }

        $symbol = $resolved['symbol'];
        $chart = $this->fetchChart($symbol);
        if ($chart === null) {
            return PriceResult::error(
                $this->code(),
                "Yahoo Finance: données de marché indisponibles pour [{$symbol}].",
            );
        }

        $result = $chart['chart']['result'][0] ?? null;
        $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
        $quote = is_array($result['indicators']['quote'][0] ?? null) ? $result['indicators']['quote'][0] : [];
        $closes = is_array($quote['close'] ?? null) ? array_values(array_filter($quote['close'], fn ($v) => $v !== null)) : [];

        $price = $meta['regularMarketPrice'] ?? ($closes ? end($closes) : null);
        if ($price === null) {
            return PriceResult::error($this->code(), "Yahoo Finance: prix non trouvé pour [{$symbol}].");
        }

        $sourceCurrency = $meta['currency'] ?? $resolved['currency'] ?? $targetCurrency;
        $currency = $sourceCurrency;
        $previousClose = $meta['chartPreviousClose'] ?? (count($closes) >= 2 ? $closes[count($closes) - 2] : null);
        $dayChange = $previousClose !== null ? (float) $price - (float) $previousClose : ($meta['regularMarketChange'] ?? null);
        $dayChangePercent = ($previousClose !== null && (float) $previousClose !== 0.0)
            ? ((float) $price - (float) $previousClose) / (float) $previousClose * 100
            : ($meta['regularMarketChangePercent'] ?? null);

        $metrics = [
            'volume' => $meta['regularMarketVolume'] ?? $this->lastValue($quote['volume'] ?? null),
            'day_change' => $dayChange,
            'day_change_percent' => $dayChangePercent,
            'previous_close' => $previousClose,
            'open' => $meta['regularMarketOpen'] ?? $this->lastValue($quote['open'] ?? null),
            'high' => $meta['regularMarketDayHigh'] ?? $this->lastValue($quote['high'] ?? null),
            'low' => $meta['regularMarketDayLow'] ?? $this->lastValue($quote['low'] ?? null),
            'high_52w' => $meta['fiftyTwoWeekHigh'] ?? null,
            'low_52w' => $meta['fiftyTwoWeekLow'] ?? null,
            'performance' => $this->buildPerformance($closes),
        ];

        $fxRate = null;
        if ($currency !== $targetCurrency) {
            $fxRate = $this->fetchFxRate($currency, $targetCurrency);
            if ($fxRate !== null && $fxRate > 0) {
                $price = (float) $price / $fxRate;
                $metrics = $this->convertMetrics($metrics, $fxRate);
                $currency = $targetCurrency;
            }
        }

        return PriceResult::success(
            price: (float) $price,
            currency: $currency,
            source: $this->code(),
            rawPayload: [
                'resolved' => $resolved,
                'chart' => $chart,
                'meta' => $meta,
                'quote' => $quote,
                'metrics' => $metrics,
                'fx_rate' => $fxRate,
                'source_currency' => $sourceCurrency,
                'target_currency' => $targetCurrency,
            ],
        );
    }

    private function resolveInstrument(Investment $investment): ?array
    {
        if (filled($investment->symbol)) {
            $symbol = trim((string) $investment->symbol);

            return [
                'source' => 'symbol',
                'symbol' => $symbol,
                'currency' => $this->inferCurrencyFromSymbol($symbol, $investment->currency),
            ];
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
        $cacheKey = 'yahoo:search:'.md5(mb_strtolower(trim($query)));
        $cached = cache()->get($cacheKey);
        $results = is_array($cached) ? $cached : null;

        if ($results === null) {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 1000)
                    ->get(self::BASE_URL.'/v1/finance/search', [
                        'q' => $query,
                        'quotesCount' => 10,
                        'newsCount' => 0,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Yahoo Finance search HTTP error', ['query' => $query, 'error' => $e->getMessage()]);

                return null;
            }

            if (! $response->successful()) {
                Log::warning('Yahoo Finance search non-2xx', ['query' => $query, 'status' => $response->status(), 'body' => $response->body()]);

                return null;
            }

            $payload = $response->json();
            $results = is_array($payload['quotes'] ?? null) ? array_values($payload['quotes']) : [];
            cache()->put($cacheKey, $results, self::SEARCH_TTL);
        }

        $candidates = $this->expandSearchResults($results);

        return $this->pickBestSearchResult($candidates, $investment);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function expandSearchResults(array $results): array
    {
        $queue = $results;
        $seen = [];
        $out = [];

        while ($queue) {
            $row = array_shift($queue);
            if (! is_array($row)) {
                continue;
            }

            $symbol = trim((string) ($row['symbol'] ?? ''));
            if ($symbol === '' || isset($seen[$symbol])) {
                continue;
            }

            $seen[$symbol] = true;
            $out[] = $row;

            $root = $this->rootSymbol($symbol);
            if ($root !== '' && ! isset($seen[$root])) {
                $seen[$root] = true;
                foreach ($this->searchOnce($root) as $child) {
                    $queue[] = $child;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchOnce(string $query): array
    {
        $cacheKey = 'yahoo:search:'.md5(mb_strtolower(trim($query)));
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/v1/finance/search', [
                    'q' => $query,
                    'quotesCount' => 10,
                    'newsCount' => 0,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Yahoo Finance search HTTP error', ['query' => $query, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $payload = $response->json();
        $results = is_array($payload['quotes'] ?? null) ? array_values($payload['quotes']) : [];
        cache()->put($cacheKey, $results, self::SEARCH_TTL);

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
            $shortname = mb_strtolower(trim((string) ($row['shortname'] ?? '')));
            $longname = mb_strtolower(trim((string) ($row['longname'] ?? '')));
            $exchange = strtoupper(trim((string) ($row['exchange'] ?? '')));
            $quoteType = strtoupper(trim((string) ($row['quoteType'] ?? '')));

            if ($symbol === '') {
                continue;
            }

            $score = 0;

            if ($queryName !== '' && ($shortname === $queryName || $longname === $queryName)) {
                $score += 100;
            } elseif ($queryName !== '' && (str_contains($shortname, $queryName) || str_contains($longname, $queryName))) {
                $score += 90;
            } elseif ($queryIsin !== '' && str_contains(mb_strtolower($symbol.' '.$shortname.' '.$longname), $queryIsin)) {
                $score += 70;
            } else {
                $score += 25;
            }

            if (in_array($quoteType, ['ETF', 'ETP', 'ETN', 'MUTUALFUND'], true)) {
                $score += 10;
            }

            foreach ($preferredSuffixes as $suffix => $bonus) {
                if (str_ends_with($symbol, $suffix)) {
                    $score += $bonus;
                    break;
                }
            }

            if ($bestScore === null || $score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'source' => 'search',
                    'symbol' => $symbol,
                    'exchange' => $exchange ?: null,
                    'currency' => $this->inferCurrencyFromSymbol($symbol, $targetCurrency ?: 'USD'),
                    'shortname' => $row['shortname'] ?? null,
                    'longname' => $row['longname'] ?? null,
                    'quoteType' => $quoteType ?: null,
                ];
            }
        }

        return $best;
    }

    private function fetchChart(string $symbol): ?array
    {
        $cacheKey = 'yahoo:chart:'.md5($symbol);
        $cached = cache()->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->retry(2, 1000)
                ->get(self::BASE_URL.'/v8/finance/chart/'.rawurlencode($symbol), [
                    'interval' => '1d',
                    'range' => '1y',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Yahoo Finance chart HTTP error', ['symbol' => $symbol, 'error' => $e->getMessage()]);

            throw new ProviderUnavailableException($this->code(), $e->getMessage());
        }

        if (! $response->successful()) {
            throw new ProviderUnavailableException($this->code(), "HTTP {$response->status()}: {$response->body()}");
        }

        $payload = $response->json();
        if (! is_array($payload['chart']['result'][0] ?? null)) {
            $error = $payload['chart']['error']['description'] ?? 'résultat absent';
            return null;
        }

        cache()->put($cacheKey, $payload, self::CHART_TTL);

        return $payload;
    }

    private function fetchFxRate(string $from, string $to): ?float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));
        if ($from === '' || $to === '' || $from === $to) {
            return 1.0;
        }

        $pair = $to.$from.'=X';
        $cacheKey = 'yahoo:fx:'.md5($pair);
        $cached = cache()->get($cacheKey);
        if (is_float($cached) || is_int($cached)) {
            return (float) $cached;
        }

        $chart = $this->fetchChart($pair);
        $rate = (float) ($chart['chart']['result'][0]['meta']['regularMarketPrice'] ?? 0);
        if ($rate > 0) {
            cache()->put($cacheKey, $rate, self::CHART_TTL);

            return $rate;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    private function convertMetrics(array $metrics, float $fxRate): array
    {
        $convert = fn ($value) => is_numeric($value) ? ((float) $value / $fxRate) : $value;

        foreach (['volume', 'day_change', 'previous_close', 'open', 'high', 'low', 'high_52w', 'low_52w'] as $key) {
            if (array_key_exists($key, $metrics)) {
                $metrics[$key] = $convert($metrics[$key]);
            }
        }

        return $metrics;
    }

    private function rootSymbol(string $symbol): string
    {
        $parts = explode('.', trim($symbol));

        return count($parts) >= 2 ? $parts[0] : trim($symbol);
    }

    /**
     * @param array<int, float|int|string|null>|null $values
     * @return float|int|string|null
     */
    private function lastValue(?array $values): float|int|string|null
    {
        if (! is_array($values) || $values === []) {
            return null;
        }

        return $values[array_key_last($values)] ?? null;
    }

    private function inferCurrencyFromSymbol(string $symbol, string $fallback): string
    {
        $suffix = strtoupper((string) ($this->exchangeFromSymbol($symbol) ?? ''));

        return match ($suffix) {
            'PA', 'PAR', 'DE', 'XETR', 'MI', 'LIS', 'AM' => 'EUR',
            'L' => 'GBP',
            'SW', 'SWX' => 'CHF',
            default => $fallback,
        };
    }

    private function exchangeFromSymbol(string $symbol): ?string
    {
        $parts = explode('.', trim($symbol));

        return count($parts) >= 2 ? end($parts) : null;
    }

    /**
     * @param array<int, float|int|string|null> $closes
     * @return array<string, float>
     */
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
