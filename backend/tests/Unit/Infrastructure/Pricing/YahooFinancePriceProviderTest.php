<?php

use App\Infrastructure\Pricing\YahooFinance\YahooFinancePriceProvider;
use App\Models\AssetType;
use App\Models\Investment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function yahooInvestment(string $isin, string $name): Investment
{
    $investment = new Investment([
        'isin' => $isin,
        'name' => $name,
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    return $investment;
}

beforeEach(function () {
    Cache::flush();
});

it('resolves an ISIN-only ETF through Yahoo Finance search and chart data', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/v1/finance/search')) {
            $query = $request->data()['q'] ?? '';

            if ($query === 'GB00BLD4ZL17') {
                return Http::response([
                    'quotes' => [
                        [
                            'exchange' => 'EBS',
                            'shortname' => 'CoinShares Bitcoin ETP',
                            'longname' => 'CoinShares Bitcoin ETP',
                            'quoteType' => 'ETF',
                            'symbol' => 'BITC.SW',
                        ],
                    ],
                ]);
            }

            if ($query === 'BITC') {
                return Http::response([
                    'quotes' => [
                        [
                            'exchange' => 'PAR',
                            'shortname' => 'CoinShares Bitcoin ETP',
                            'longname' => 'CoinShares Bitcoin ETP',
                            'quoteType' => 'ETF',
                            'symbol' => 'BITC.PA',
                        ],
                        [
                            'exchange' => 'EBS',
                            'shortname' => 'CoinShares Bitcoin ETP',
                            'longname' => 'CoinShares Bitcoin ETP',
                            'quoteType' => 'ETF',
                            'symbol' => 'BITC.SW',
                        ],
                    ],
                ]);
            }
        }

        if (str_contains($request->url(), '/v8/finance/chart/BITC.PA')) {
            return Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'currency' => 'EUR',
                            'regularMarketPrice' => 52.90,
                            'regularMarketVolume' => 202,
                            'regularMarketOpen' => 52.10,
                            'regularMarketDayHigh' => 53.10,
                            'regularMarketDayLow' => 51.80,
                            'fiftyTwoWeekHigh' => 89.24,
                            'fiftyTwoWeekLow' => 43.58,
                            'chartPreviousClose' => 52.78,
                        ],
                        'indicators' => [
                            'quote' => [[
                                'open' => [51.90, 52.10, 52.40, 52.60, 52.80],
                                'high' => [52.50, 52.60, 52.90, 53.00, 53.10],
                                'low' => [51.80, 51.90, 52.20, 52.40, 52.60],
                                'close' => [52.10, 52.30, 52.70, 52.80, 52.90],
                                'volume' => [1000, 1100, 1200, 1300, 1400],
                            ]],
                        ],
                    ]],
                ],
            ]);
        }

        return Http::response(['error' => 'unexpected request'], 500);
    });

    $result = (new YahooFinancePriceProvider())
        ->fetch(yahooInvestment('GB00BLD4ZL17', 'CoinShares Bitcoin ETP'), 'EUR');

    expect($result->isError())->toBeFalse()
        ->and($result->price)->toBe(52.90)
        ->and($result->currency)->toBe('EUR')
        ->and($result->rawPayload['resolved']['symbol'])->toBe('BITC.PA')
        ->and($result->rawPayload['metrics']['volume'])->toBe(202);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/finance/search')
        && ($request->data()['q'] ?? null) === 'GB00BLD4ZL17');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/finance/search')
        && ($request->data()['q'] ?? null) === 'BITC');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v8/finance/chart/BITC.PA'));
});

it('converts Yahoo Finance prices to the target currency when needed', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/v8/finance/chart/BITC.SW')) {
            return Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'currency' => 'CHF',
                            'regularMarketPrice' => 48.82,
                            'regularMarketVolume' => 202,
                            'regularMarketOpen' => 48.20,
                            'regularMarketDayHigh' => 48.90,
                            'regularMarketDayLow' => 48.10,
                            'fiftyTwoWeekHigh' => 89.24,
                            'fiftyTwoWeekLow' => 43.58,
                            'chartPreviousClose' => 48.50,
                        ],
                        'indicators' => [
                            'quote' => [[
                                'open' => [48.00, 48.10, 48.20, 48.40, 48.82],
                                'high' => [48.20, 48.30, 48.50, 48.70, 48.90],
                                'low' => [47.90, 48.00, 48.10, 48.20, 48.30],
                                'close' => [48.10, 48.20, 48.30, 48.40, 48.82],
                                'volume' => [100, 120, 140, 160, 202],
                            ]],
                        ],
                    ]],
                ],
            ]);
        }

        if (str_contains($request->url(), '/v8/finance/chart/EURCHF%3DX')) {
            return Http::response([
                'chart' => [
                    'result' => [[
                        'meta' => [
                            'currency' => 'CHF',
                            'regularMarketPrice' => 0.9252,
                        ],
                    ]],
                ],
            ]);
        }

        return Http::response(['error' => 'unexpected request'], 500);
    });

    $investment = new Investment([
        'symbol' => 'BITC.SW',
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    $result = (new YahooFinancePriceProvider())
        ->fetch($investment, 'EUR');

    expect($result->isError())->toBeFalse()
        ->and($result->currency)->toBe('EUR')
        ->and($result->price)->toBeGreaterThan(52.7)
        ->and($result->price)->toBeLessThan(52.8)
        ->and($result->rawPayload['source_currency'])->toBe('CHF')
        ->and($result->rawPayload['target_currency'])->toBe('EUR');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v8/finance/chart/EURCHF%3DX'));
});
