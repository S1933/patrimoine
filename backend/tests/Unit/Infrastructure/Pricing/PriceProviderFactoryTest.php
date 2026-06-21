<?php

use App\Infrastructure\Pricing\CoinGecko\CoinGeckoPriceProvider;
use App\Infrastructure\Pricing\Finnhub\FinnhubPriceProvider;
use App\Infrastructure\Pricing\GoldApi\GoldApiPriceProvider;
use App\Infrastructure\Pricing\Manual\ManualPriceProvider;
use App\Infrastructure\Pricing\PriceProviderFactory;
use App\Infrastructure\Pricing\TwelveData\TwelveDataPriceProvider;
use App\Infrastructure\Pricing\YahooFinance\YahooFinancePriceProvider;
use App\Models\AssetType;
use App\Models\Investment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

it('falls back from Twelve Data to Finnhub for market investments', function (string $assetType) {
    RateLimiter::clear('pricing:twelve_data');
    Http::fake([
        'api.twelvedata.com/*' => Http::response([
            'status' => 'error',
            'message' => 'temporary failure',
        ]),
        'finnhub.io/*' => Http::response(['c' => 123.45]),
    ]);

    $factory = new PriceProviderFactory(
        new CoinGeckoPriceProvider,
        new GoldApiPriceProvider,
        new TwelveDataPriceProvider('twelve-key'),
        new FinnhubPriceProvider('finnhub-key'),
        new YahooFinancePriceProvider,
        new ManualPriceProvider,
    );

    $investment = new Investment([
        'symbol' => 'AAPL',
        'currency' => 'EUR',
    ]);
    $investment->setRelation('assetType', new AssetType(['code' => $assetType]));

    $result = $factory->forInvestment($investment)->fetch($investment, 'EUR');

    expect($result->source)->toBe('finnhub')
        ->and($result->price)->toBe(123.45);

    Http::assertSentCount(3);
})->with(['stock', 'etf', 'etn_crypto']);
