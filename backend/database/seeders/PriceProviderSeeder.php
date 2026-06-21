<?php

namespace Database\Seeders;

use App\Models\PriceProvider;
use Illuminate\Database\Seeder;

class PriceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'code' => 'coingecko',
                'label' => 'CoinGecko',
                'supported_types' => 1,
                'base_url' => 'https://api.coingecko.com/api/v3',
                'api_key_env' => 'PROVIDER_COINGECKO_KEY',
                'rate_limit_per_min' => 30,
                'is_active' => true,
                'priority' => 10,
            ],
            [
                'code' => 'goldapi',
                'label' => 'GoldAPI.io',
                'supported_types' => 1,
                'base_url' => 'https://www.goldapi.io/api',
                'api_key_env' => 'PROVIDER_GOLDAPI_KEY',
                'rate_limit_per_min' => 5,
                'is_active' => true,
                'priority' => 20,
            ],
            [
                'code' => 'twelve_data',
                'label' => 'Twelve Data (stocks/ETF/ETN)',
                'supported_types' => 1,
                'base_url' => 'https://api.twelvedata.com',
                'api_key_env' => 'PROVIDER_TWELVEDATA_KEY',
                'rate_limit_per_min' => 8,
                'is_active' => true,
                'priority' => 30,
            ],
            [
                'code' => 'finnhub',
                'label' => 'Finnhub (fallback stocks/ETF/ETN)',
                'supported_types' => 1,
                'base_url' => 'https://finnhub.io/api/v1',
                'api_key_env' => 'PROVIDER_FINNHUB_KEY',
                'rate_limit_per_min' => 60,
                'is_active' => true,
                'priority' => 35,
            ],
            [
                'code' => 'yahoo_finance',
                'label' => 'Yahoo Finance (fallback stocks/ETF/ETN)',
                'supported_types' => 1,
                'base_url' => 'https://query1.finance.yahoo.com',
                'api_key_env' => null,
                'rate_limit_per_min' => 0,
                'is_active' => true,
                'priority' => 40,
            ],
            [
                'code' => 'exchangerate-host',
                'label' => 'exchangerate.host (FX)',
                'supported_types' => 1,
                'base_url' => 'https://api.exchangerate.host',
                'api_key_env' => 'PROVIDER_EXCHANGERATE_KEY',
                'rate_limit_per_min' => 60,
                'is_active' => true,
                'priority' => 50,
            ],
            [
                'code' => 'manual',
                'label' => 'Saisie manuelle',
                'supported_types' => 1,
                'base_url' => null,
                'api_key_env' => null,
                'rate_limit_per_min' => 0,
                'is_active' => true,
                'priority' => 90,
            ],
        ];

        foreach ($providers as $p) {
            PriceProvider::updateOrCreate(['code' => $p['code']], $p);
        }
    }
}
