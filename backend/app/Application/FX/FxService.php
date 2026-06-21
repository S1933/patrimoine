<?php

namespace App\Application\FX;

use App\Models\FxRate as FxRateModel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tracable FX conversion service.
 *
 * Fetches rates from Yahoo Finance, persists every fetch in fx_rates,
 * and provides conversion to a target currency.
 */
final class FxService
{
    private const CACHE_TTL = 300;

    /**
     * Convert an amount from one currency to another.
     *
     * @return array{amount: float, rate: float|null, source: string|null, from_currency: string, to_currency: string}
     */
    public function convert(float $amount, string $from, string $to): array
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return [
                'amount' => $amount,
                'rate' => 1.0,
                'source' => 'same_currency',
                'from_currency' => $from,
                'to_currency' => $to,
            ];
        }

        $rate = $this->fetchRate($from, $to);
        if ($rate === null) {
            return [
                'amount' => $amount,
                'rate' => null,
                'source' => null,
                'from_currency' => $from,
                'to_currency' => $to,
            ];
        }

        return [
            'amount' => $amount / $rate,
            'rate' => $rate,
            'source' => 'yahoo_finance',
            'from_currency' => $from,
            'to_currency' => $to,
        ];
    }

    public function fetchRate(string $from, string $to): ?float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === '' || $to === '' || $from === $to) {
            return $from === $to ? 1.0 : null;
        }

        $cacheKey = "fx:rate:{$from}:{$to}";
        $cached = cache()->get($cacheKey);
        if (is_float($cached) || is_int($cached)) {
            return (float) $cached;
        }

        $rate = $this->fetchFromYahoo($from, $to);
        if ($rate === null) {
            $rate = $this->fetchLatestPersisted($from, $to);
        }

        if ($rate !== null) {
            cache()->put($cacheKey, $rate, self::CACHE_TTL);
        }

        return $rate;
    }

    private function fetchFromYahoo(string $from, string $to): ?float
    {
        $pair = $to . $from . '=X';

        try {
            $response = Http::timeout(10)
                ->retry(1, 1000)
                ->get('https://query1.finance.yahoo.com/v8/finance/chart/' . rawurlencode($pair), [
                    'interval' => '1d',
                    'range' => '1d',
                ]);
        } catch (\Throwable $e) {
            Log::warning('FX rate fetch error', ['pair' => $pair, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();
        $rate = (float) ($payload['chart']['result'][0]['meta']['regularMarketPrice'] ?? 0);

        if ($rate <= 0) {
            return null;
        }

        FxRateModel::create([
            'from_currency' => $from,
            'to_currency' => $to,
            'rate' => $rate,
            'source' => 'yahoo_finance',
            'fetched_at' => now(),
        ]);

        return $rate;
    }

    private function fetchLatestPersisted(string $from, string $to): ?float
    {
        $row = FxRateModel::where('from_currency', $from)
            ->where('to_currency', $to)
            ->latest('fetched_at')
            ->first();

        return $row !== null ? (float) $row->rate : null;
    }
}
