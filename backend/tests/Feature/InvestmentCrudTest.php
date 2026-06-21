<?php

use App\Models\AssetType;
use App\Models\Investment;
use App\Models\PriceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->withHeaders(['Origin' => 'http://localhost:3000']);
    $this->actingAs($this->user, 'web');

    config()->set('services.pricing.finnhub.key', 'test-key');
    config()->set('services.pricing.twelve_data.key', 'test-key');

    $this->cryptoType = AssetType::where('code', 'crypto')->first() ?? AssetType::create([
        'code' => 'crypto', 'label' => 'Cryptomonnaie', 'default_unit' => 'unit',
        'is_priced_externally' => true, 'default_provider' => 'coingecko',
    ]);
    $this->etfType = AssetType::where('code', 'etf')->first() ?? AssetType::create([
        'code' => 'etf', 'label' => 'ETF', 'default_unit' => 'part',
        'is_priced_externally' => true, 'default_provider' => 'twelve_data',
    ]);
    $this->realEstateType = AssetType::where('code', 'real_estate')->first() ?? AssetType::create([
        'code' => 'real_estate', 'label' => 'Immobilier', 'default_unit' => 'euros',
        'is_priced_externally' => false, 'default_provider' => 'manual',
    ]);
});

it('lists investments for the authenticated user only', function () {
    Investment::factory()->for($this->user)->create(['name' => 'My BTC']);
    Investment::factory()->for(User::factory()->create())->create(['name' => 'Not mine']);

    $this->getJson('/api/v1/investments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'My BTC');
});

it('filters investments by status and type', function () {
    Investment::factory()->for($this->user)->create(['name' => 'A', 'status' => 'active', 'asset_type_id' => $this->cryptoType->id]);
    Investment::factory()->for($this->user)->create(['name' => 'B', 'status' => 'sold', 'asset_type_id' => $this->realEstateType->id]);

    $this->getJson('/api/v1/investments?status=active')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'A');

    $this->getJson("/api/v1/investments?type={$this->realEstateType->id}")
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'B');
});

it('creates an investment with valid payload', function () {
    $payload = [
        'asset_type_id' => $this->cryptoType->id,
        'name' => 'Bitcoin',
        'symbol' => 'BTC',
        'quantity' => 0.5,
        'unit' => 'unit',
        'currency' => 'EUR',
        'purchase_price' => 30000,
        'purchase_currency' => 'EUR',
        'purchase_date' => '2024-01-15',
        'status' => 'active',
    ];

    $this->postJson('/api/v1/investments', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bitcoin')
        ->assertJsonPath('data.quantity', 0.5)
        ->assertJsonPath('data.symbol', 'BTC');

    $this->assertDatabaseHas('investments', ['name' => 'Bitcoin', 'user_id' => $this->user->id]);
});

it('creates an investment with an ISIN and fetches market data automatically', function () {
    Cache::flush();
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

    $payload = [
        'asset_type_id' => $this->etfType->id,
        'name' => 'ETF via ISIN',
        'isin' => 'FR001400RWK6',
        'quantity' => 10,
        'unit' => 'part',
        'currency' => 'EUR',
        'status' => 'active',
    ];

    $this->postJson('/api/v1/investments', $payload)
        ->assertCreated()
        ->assertJsonPath('data.isin', 'FR001400RWK6');

    $investment = Investment::where('name', 'ETF via ISIN')->firstOrFail();

    $this->assertDatabaseHas('asset_prices', [
        'investment_id' => $investment->id,
        'source_status' => 'success',
    ]);

    $this->getJson("/api/v1/investments/{$investment->id}")
        ->assertOk()
        ->assertJsonPath('data.current_price', fn ($v) => floatval($v) === 512.34)
        ->assertJsonPath('data.market_data.source', 'isin')
        ->assertJsonPath('data.market_data.volume', fn ($v) => floatval($v) === 1800.0);
});

it('rejects creating with invalid asset_type', function () {
    $this->postJson('/api/v1/investments', [
        'asset_type_id' => 99999,
        'name' => 'X',
        'quantity' => 1,
        'unit' => 'unit',
        'currency' => 'EUR',
    ])->assertJsonValidationErrors(['asset_type_id']);
});

it('rejects creating with negative quantity', function () {
    $this->postJson('/api/v1/investments', [
        'asset_type_id' => $this->cryptoType->id,
        'name' => 'X',
        'quantity' => -1,
        'unit' => 'unit',
        'currency' => 'EUR',
    ])->assertJsonValidationErrors(['quantity']);
});

it('shows a single investment with computed fields', function () {
    $inv = Investment::factory()->for($this->user)->create([
        'name' => 'Gold bar',
        'quantity' => 20,
        'purchase_price' => 50,
        'purchase_currency' => 'EUR',
        'currency' => 'EUR',
        'manual_value' => 1200,
    ]);

    $this->getJson("/api/v1/investments/{$inv->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Gold bar')
        ->assertJsonPath('data.current_value', fn ($v) => floatval($v) === 1200.0)
        ->assertJsonPath('data.purchase_value', fn ($v) => floatval($v) === 1000.0)
        ->assertJsonPath('data.pnl_absolute', fn ($v) => floatval($v) === 200.0);
});

it('shows accrued yield for Livret A investments', function () {
    Carbon::setTestNow('2026-01-31 12:00:00');

    try {
        $inv = Investment::factory()->for($this->user)->create([
            'asset_type_id' => AssetType::firstOrCreate(
                ['code' => 'livret_a'],
                ['label' => 'Livret A', 'default_unit' => 'euros', 'is_priced_externally' => false],
            )->id,
            'name' => 'Livret A',
            'quantity' => 1,
            'manual_value' => 10000,
            'purchase_price' => 10000,
            'purchase_date' => '2026-01-01',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $this->getJson("/api/v1/investments/{$inv->id}")
            ->assertOk()
            ->assertJsonPath('data.current_value', fn ($v) => floatval($v) === 10012.33)
            ->assertJsonPath('data.current_price', fn ($v) => floatval($v) === 10012.33)
            ->assertJsonPath('data.savings_yield.rate', fn ($v) => floatval($v) === 0.015)
            ->assertJsonPath('data.savings_yield.accrued_interest', fn ($v) => floatval($v) === 12.33);
    } finally {
        Carbon::setTestNow();
    }
});

it('updates an investment', function () {
    $inv = Investment::factory()->for($this->user)->create(['name' => 'Old']);

    $this->putJson("/api/v1/investments/{$inv->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('deletes (soft delete) an investment', function () {
    $inv = Investment::factory()->for($this->user)->create();

    $this->deleteJson("/api/v1/investments/{$inv->id}")->assertNoContent();

    $this->assertSoftDeleted('investments', ['id' => $inv->id]);
});

it('forbids accessing another user investment', function () {
    $other = Investment::factory()->for(User::factory()->create())->create();

    $this->getJson("/api/v1/investments/{$other->id}")->assertForbidden();
    $this->putJson("/api/v1/investments/{$other->id}", ['name' => 'hack'])->assertForbidden();
    $this->deleteJson("/api/v1/investments/{$other->id}")->assertForbidden();
});

it('sets a manual value on an investment', function () {
    $inv = Investment::factory()->for($this->user)->create([
        'manual_value' => null,
        'currency' => 'EUR',
    ]);

    $this->postJson("/api/v1/investments/{$inv->id}/manual-value", ['value' => 250000])
        ->assertOk()
        ->assertJsonPath('data.manual_value', fn ($v) => floatval($v) === 250000.0)
        ->assertJsonPath('data.current_value', fn ($v) => floatval($v) === 250000.0);

    $this->assertNotNull($inv->fresh()->manual_value_updated_at);
});

it('lists asset types, providers and currencies', function () {
    AssetType::firstOrCreate(['code' => 'cash'], ['label' => 'Cash', 'default_unit' => 'euros', 'is_priced_externally' => false]);
    PriceProvider::firstOrCreate(['code' => 'manual'], ['label' => 'Manuel', 'supported_types' => 1, 'is_active' => true, 'priority' => 90]);

    $this->getJson('/api/v1/asset-types')->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'label']]]);
    $this->getJson('/api/v1/price-providers')->assertOk()->assertJsonStructure(['data' => [['id', 'code', 'label']]]);
    $this->getJson('/api/v1/currencies')->assertOk()->assertJsonPath('data.0.code', 'EUR');
});
