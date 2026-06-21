<?php

use App\Domain\Pricing\ProviderUnavailableException;
use App\Infrastructure\Pricing\TwelveData\TwelveDataPriceProvider;
use App\Models\AssetType;
use App\Models\Investment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

function twelveDataInvestment(string $symbol = 'AIR.PAR', string $currency = 'EUR'): Investment
{
    $investment = new Investment([
        'symbol' => $symbol,
        'currency' => $currency,
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'stock']));

    return $investment;
}

beforeEach(function () {
    Cache::flush();
    RateLimiter::clear('pricing:twelve_data');
});

it('fetches and caches a Twelve Data price with exchange and currency', function () {
    Http::fake([
        'api.twelvedata.com/*' => Http::response(['price' => '142.35']),
    ]);

    $provider = new TwelveDataPriceProvider('test-key');
    $investment = twelveDataInvestment();

    $first = $provider->fetch($investment, 'EUR');
    $second = $provider->fetch($investment, 'EUR');

    expect($first->price)->toBe(142.35)
        ->and($first->currency)->toBe('EUR')
        ->and($first->source)->toBe('twelve_data')
        ->and($second->price)->toBe(142.35);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => ($request->data()['symbol'] ?? null) === 'AIR'
        && ($request->data()['exchange'] ?? null) === 'PAR'
        && ($request->data()['apikey'] ?? null) === 'test-key');
});

it('stops before the HTTP call when the Twelve Data minute limit is exhausted', function () {
    Http::fake();
    RateLimiter::hit('pricing:twelve_data', 60);

    $provider = new TwelveDataPriceProvider('test-key', 1);

    expect(fn () => $provider->fetch(twelveDataInvestment(), 'EUR'))
        ->toThrow(ProviderUnavailableException::class, 'rate limit');

    Http::assertNothingSent();
});

it('returns an error result for a Twelve Data API error payload', function () {
    Http::fake([
        'api.twelvedata.com/*' => Http::response([
            'status' => 'error',
            'message' => 'symbol not found',
        ]),
    ]);

    $result = (new TwelveDataPriceProvider('test-key'))
        ->fetch(twelveDataInvestment('UNKNOWN'), 'EUR');

    expect($result->isError())->toBeTrue()
        ->and($result->errorMessage)->toContain('symbol not found');
});

it('falls back to Twelve Data symbol search when the investment has only an ISIN and name', function () {
    Http::fake(function ($request) {
        if (str_contains($request->url(), '/symbol_search')) {
            return Http::response([
                'data' => [
                    [
                        'symbol' => 'MEUD',
                        'instrument_name' => 'Amundi Core Stoxx Europe 600 UCITS ETF Acc',
                        'exchange' => 'Euronext',
                        'mic_code' => 'XPAR',
                        'instrument_type' => 'ETF',
                        'country' => 'France',
                        'currency' => 'EUR',
                    ],
                    [
                        'symbol' => 'LYP6',
                        'instrument_name' => 'Amundi Core Stoxx Europe 600 UCITS ETF Acc',
                        'exchange' => 'XETR',
                        'mic_code' => 'XETR',
                        'instrument_type' => 'ETF',
                        'country' => 'Germany',
                        'currency' => 'EUR',
                    ],
                ],
            ]);
        }

        return Http::response(['price' => '312.10']);
    });

    $investment = new Investment([
        'name' => 'AMUNDI CORE STOXX EUROPE 600 UCITS ETF ACC',
        'isin' => 'LU0908500753',
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => 'etf']));

    $result = (new TwelveDataPriceProvider('test-key'))
        ->fetch($investment, 'EUR');

    expect($result->isError())->toBeFalse()
        ->and($result->price)->toBe(312.10)
        ->and($result->currency)->toBe('EUR');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/symbol_search')
        && ($request->data()['symbol'] ?? null) === 'AMUNDI CORE STOXX EUROPE 600 UCITS ETF ACC');
    Http::assertSent(fn ($request) => str_contains($request->url(), '/price')
        && ($request->data()['symbol'] ?? null) === 'MEUD'
        && ($request->data()['exchange'] ?? null) === 'Euronext');
});
