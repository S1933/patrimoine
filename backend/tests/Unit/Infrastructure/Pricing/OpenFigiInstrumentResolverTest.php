<?php

use App\Infrastructure\Pricing\OpenFigi\OpenFigiInstrumentResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

it('resolves an ISIN to a market instrument', function () {
    Http::fake([
        'api.openfigi.com/*' => Http::response([
            [
                'data' => [[
                    'figi' => 'BBG000BLNNH6',
                    'ticker' => 'IBM',
                    'name' => 'INTL BUSINESS MACHINES CORP',
                    'exchCode' => 'US',
                    'marketSector' => 'Equity',
                    'securityType' => 'Common Stock',
                    'securityDescription' => 'IBM',
                ]],
            ],
        ]),
    ]);

    $resolved = (new OpenFigiInstrumentResolver())->resolveIsin('FR001400RWK6');

    expect($resolved)->not->toBeNull()
        ->and($resolved['ticker'])->toBe('IBM')
        ->and($resolved['isin'])->toBe('FR001400RWK6');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.openfigi.com/v3/mapping'
        && $request->data()[0]['idType'] === 'ID_ISIN'
        && $request->data()[0]['idValue'] === 'FR001400RWK6');
});
