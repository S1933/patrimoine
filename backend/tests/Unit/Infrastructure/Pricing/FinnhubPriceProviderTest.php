<?php

use App\Domain\Pricing\ProviderUnavailableException;
use App\Infrastructure\Pricing\Finnhub\FinnhubPriceProvider;
use App\Infrastructure\Pricing\OpenFigi\OpenFigiInstrumentResolver;
use App\Models\AssetType;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;

function finnhubInvestment(string $symbol = 'AAPL'): Investment
{
    $investment = new Investment([
        'symbol' => $symbol,
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    return $investment;
}

it('fetches a Finnhub quote as the stock fallback', function () {
    Http::fake([
        'finnhub.io/*' => Http::response([
            'c' => 198.42,
            'h' => 201.15,
            'l' => 196.80,
        ]),
    ]);

    $result = (new FinnhubPriceProvider('test-key'))
        ->fetch(finnhubInvestment(), 'EUR');

    expect($result->price)->toBe(198.42)
        ->and($result->currency)->toBe('USD')
        ->and($result->source)->toBe('finnhub');

    Http::assertSent(fn ($request) => ($request->data()['symbol'] ?? null) === 'AAPL'
        && ($request->data()['token'] ?? null) === 'test-key');
});

it('resolves an ISIN before fetching a Finnhub quote and candles', function () {
    Http::fake([
        'api.openfigi.com/*' => Http::response([
            [
                'data' => [[
                    'ticker' => 'CW8',
                    'name' => 'AMUNDI MSCI WORLD UCITS ETF',
                    'exchCode' => 'FR',
                    'marketSector' => 'Equity',
                    'securityType' => 'ETF',
                ]],
            ],
        ]),
        'finnhub.io/*/quote*' => Http::response([
            'c' => 512.34,
            'h' => 515.0,
            'l' => 500.0,
            'o' => 505.0,
            'pc' => 501.0,
            'd' => 11.34,
            'dp' => 2.26,
            'v' => 123456,
        ]),
        'finnhub.io/*/stock/candle*' => Http::response([
            's' => 'ok',
            'c' => [480, 490, 500, 510, 512.34],
            'h' => [485, 495, 505, 515, 515],
            'l' => [475, 485, 495, 500, 500],
            'o' => [476, 488, 498, 503, 505],
            't' => [1, 2, 3, 4, 5],
            'v' => [1000, 1200, 1400, 1600, 1800],
        ]),
    ]);

    $investment = new Investment([
        'isin' => 'FR001400RWK6',
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    $result = (new FinnhubPriceProvider('test-key', new OpenFigiInstrumentResolver()))
        ->fetch($investment, 'EUR');

    expect($result->price)->toBe(512.34)
        ->and($result->rawPayload['resolved']['source'])->toBe('isin')
        ->and($result->rawPayload['metrics']['volume'])->toBe(1800.0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'openfigi.com/v3/mapping'));
    Http::assertSent(fn ($request) => ($request->data()['symbol'] ?? null) === 'CW8' && ($request->data()['token'] ?? null) === 'test-key');
});

it('falls back to Finnhub search and prefers the local EUR listing for an ISIN-only ETF', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/search')) {
            $query = $request->data()['q'] ?? '';

            if ($query === 'GB00BLD4ZL17') {
                return Http::response([
                    'count' => 1,
                    'result' => [[
                        'description' => 'CoinShares Bitcoin ETP',
                        'displaySymbol' => 'BITC.SW',
                        'symbol' => 'BITC.SW',
                        'type' => 'ETP',
                    ]],
                ]);
            }

            if ($query === 'BITC') {
                return Http::response([
                    'count' => 2,
                    'result' => [
                        [
                            'description' => 'CoinShares Bitcoin ETP',
                            'displaySymbol' => 'BITC.PA',
                            'symbol' => 'BITC.PA',
                            'type' => 'ETP',
                        ],
                        [
                            'description' => 'CoinShares Bitcoin ETP',
                            'displaySymbol' => 'BITC.SW',
                            'symbol' => 'BITC.SW',
                            'type' => 'ETP',
                        ],
                    ],
                ]);
            }
        }

        if (str_contains($request->url(), '/quote')) {
            return Http::response([
                'c' => 52.90,
                'h' => 53.10,
                'l' => 51.80,
                'o' => 52.10,
                'pc' => 52.78,
                'd' => 0.12,
                'dp' => 0.23,
                'v' => 1234,
            ]);
        }

        if (str_contains($request->url(), '/stock/candle')) {
            return Http::response([
                's' => 'ok',
                'c' => [52.10, 52.30, 52.70, 52.80, 52.90],
                'h' => [52.50, 52.60, 52.90, 53.00, 53.10],
                'l' => [51.80, 51.90, 52.20, 52.40, 52.60],
                'o' => [51.90, 52.10, 52.40, 52.60, 52.80],
                't' => [1, 2, 3, 4, 5],
                'v' => [1000, 1100, 1200, 1300, 1400],
            ]);
        }

        return Http::response(['error' => 'unexpected request'], 500);
    });

    $investment = new Investment([
        'isin' => 'GB00BLD4ZL17',
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    $result = (new FinnhubPriceProvider('test-key'))
        ->fetch($investment, 'EUR');

    expect($result->isError())->toBeFalse()
        ->and($result->price)->toBe(52.90)
        ->and($result->currency)->toBe('EUR')
        ->and($result->rawPayload['resolved']['ticker'])->toBe('BITC.PA');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
        && ($request->data()['q'] ?? null) === 'GB00BLD4ZL17');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/search')
        && ($request->data()['q'] ?? null) === 'BITC');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/quote')
        && ($request->data()['symbol'] ?? null) === 'BITC.PA');
});

it('strips the Twelve Data exchange suffix before calling Finnhub', function () {
    Http::fake([
        'finnhub.io/*' => Http::response(['c' => 12.5]),
    ]);

    (new FinnhubPriceProvider('test-key'))
        ->fetch(finnhubInvestment('AIR.PAR'), 'EUR');

    Http::assertSent(fn ($request) => ($request->data()['symbol'] ?? null) === 'AIR');
});

it('returns an error result when Finnhub has no current price', function () {
    Http::fake([
        'finnhub.io/*' => Http::response(['c' => 0]),
    ]);

    $result = (new FinnhubPriceProvider('test-key'))
        ->fetch(finnhubInvestment('UNKNOWN'), 'EUR');

    expect($result->isError())->toBeTrue()
        ->and($result->errorMessage)->toContain('prix non trouvé');
});

it('is unavailable without a Finnhub API key', function () {
    expect(fn () => (new FinnhubPriceProvider)->fetch(finnhubInvestment(), 'EUR'))
        ->toThrow(ProviderUnavailableException::class, 'PROVIDER_FINNHUB_KEY');
});
