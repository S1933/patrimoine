<?php

namespace App\Providers;

use App\Infrastructure\Pricing\CoinGecko\CoinGeckoPriceProvider;
use App\Infrastructure\Pricing\Finnhub\FinnhubPriceProvider;
use App\Infrastructure\Pricing\GoldApi\GoldApiPriceProvider;
use App\Infrastructure\Pricing\Manual\ManualPriceProvider;
use App\Infrastructure\Pricing\OpenFigi\OpenFigiInstrumentResolver;
use App\Infrastructure\Pricing\PriceProviderFactory;
use App\Infrastructure\Pricing\TwelveData\TwelveDataPriceProvider;
use App\Infrastructure\Pricing\YahooFinance\YahooFinancePriceProvider;
use Illuminate\Support\ServiceProvider;

class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenFigiInstrumentResolver::class, fn () => new OpenFigiInstrumentResolver(
            apiKey: config('services.pricing.openfigi.key'),
        ));

        $this->app->singleton(CoinGeckoPriceProvider::class, fn () => new CoinGeckoPriceProvider(
            apiKey: config('services.pricing.coingecko.key'),
        ));

        $this->app->singleton(GoldApiPriceProvider::class);

        $this->app->singleton(TwelveDataPriceProvider::class, fn () => new TwelveDataPriceProvider(
            apiKey: config('services.pricing.twelve_data.key'),
            rateLimitPerMinute: config('services.pricing.twelve_data.rate_limit_per_min', 8),
            openFigiResolver: $this->app->make(OpenFigiInstrumentResolver::class),
        ));

        $this->app->singleton(FinnhubPriceProvider::class, fn () => new FinnhubPriceProvider(
            apiKey: config('services.pricing.finnhub.key'),
            openFigiResolver: $this->app->make(OpenFigiInstrumentResolver::class),
        ));

        $this->app->singleton(YahooFinancePriceProvider::class);

        $this->app->singleton(ManualPriceProvider::class);

        $this->app->singleton(PriceProviderFactory::class);
    }
}
