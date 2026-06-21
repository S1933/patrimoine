<?php

use App\Models\AssetType;
use App\Models\Investment;
use App\Models\InvestmentStrategyAllocation;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'web');
    $this->withHeaders(['Origin' => 'http://localhost:3000']);

    $this->realEstateType = AssetType::firstOrCreate(
        ['code' => 'real_estate'],
        ['label' => 'Immobilier', 'default_unit' => 'euros', 'is_priced_externally' => false],
    );
    $this->cashType = AssetType::firstOrCreate(
        ['code' => 'cash'],
        ['label' => 'Cash', 'default_unit' => 'euros', 'is_priced_externally' => false],
    );
    $this->cryptoType = AssetType::firstOrCreate(
        ['code' => 'crypto'],
        ['label' => 'Cryptomonnaie', 'default_unit' => 'unit', 'is_priced_externally' => true],
    );
});

it('returns summary with correct totals', function () {
    $inv1 = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'name' => 'Appart',
        'quantity' => 1,
        'manual_value' => 200000,
        'purchase_price' => 150000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    $inv2 = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->cashType->id,
        'name' => 'Livret A',
        'quantity' => 1,
        'manual_value' => 50000,
        'purchase_price' => 50000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.total_value', fn ($v) => floatval($v) === 250000.0)
        ->assertJsonPath('data.total_cost', fn ($v) => floatval($v) === 200000.0)
        ->assertJsonPath('data.active_count', 2);
});

it('applies fixed yield to livret A and LDDS balances', function () {
    Carbon::setTestNow('2026-01-31 12:00:00');

    try {
        Investment::factory()->for($this->user)->create([
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

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total_value', fn ($v) => floatval($v) === 10012.33)
            ->assertJsonPath('data.pnl_absolute', fn ($v) => floatval($v) === 12.33);
    } finally {
        Carbon::setTestNow();
    }
});

it('returns allocation by asset class', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id, 'manual_value' => 200000, 'currency' => 'EUR', 'status' => 'active',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->cashType->id, 'manual_value' => 50000, 'currency' => 'EUR', 'status' => 'active',
    ]);

    $this->getJson('/api/v1/dashboard/allocation')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'real_estate')
        ->assertJsonPath('data.0.value', fn ($v) => floatval($v) === 200000.0)
        ->assertJsonPath('data.0.percent', fn ($v) => floatval($v) === 80.0)
        ->assertJsonPath('data.1.code', 'cash')
        ->assertJsonPath('data.1.percent', fn ($v) => floatval($v) === 20.0);
});

it('compares actual allocation with targeted and missing asset classes', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->cryptoType->id,
        'manual_value' => 10000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->cashType->id,
        'manual_value' => 90000,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    InvestmentStrategyAllocation::query()->insert([
        [
            'user_id' => $this->user->id,
            'asset_type_id' => $this->cryptoType->id,
            'target_percent' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $this->user->id,
            'asset_type_id' => $this->realEstateType->id,
            'target_percent' => 80,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'user_id' => $this->user->id,
            'asset_type_id' => $this->cashType->id,
            'target_percent' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->getJson('/api/v1/dashboard/allocation')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment([
            'code' => 'crypto',
            'percent' => 10,
            'target_percent' => 5,
            'deviation_points' => 5,
        ])
        ->assertJsonFragment([
            'code' => 'real_estate',
            'value' => 0,
            'percent' => 0,
            'count' => 0,
            'target_percent' => 80,
            'deviation_points' => -80,
        ])
        ->assertJsonFragment([
            'code' => 'cash',
            'percent' => 90,
            'target_percent' => 15,
            'deviation_points' => 75,
        ]);
});

it('returns per-investment breakdown', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'name' => 'Appart',
        'manual_value' => 200000,
        'purchase_price' => 150000,
        'quantity' => 1,
        'currency' => 'EUR',
        'status' => 'active',
    ]);

    $this->getJson('/api/v1/dashboard/breakdown')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Appart')
        ->assertJsonPath('data.0.current_value', fn ($v) => floatval($v) === 200000.0)
        ->assertJsonPath('data.0.purchase_value', fn ($v) => floatval($v) === 150000.0)
        ->assertJsonPath('data.0.pnl_absolute', fn ($v) => floatval($v) === 50000.0)
        ->assertJsonPath('data.0.weight', fn ($v) => floatval($v) === 100.0);
});

it('returns empty dashboard when no active investments', function () {
    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.total_value', 0)
        ->assertJsonPath('data.active_count', 0);
});

it('excludes sold and archived investments from dashboard', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id, 'manual_value' => 200000, 'status' => 'active',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id, 'manual_value' => 999999, 'status' => 'sold',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id, 'manual_value' => 999999, 'status' => 'archived',
    ]);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.total_value', fn ($v) => floatval($v) === 200000.0)
        ->assertJsonPath('data.active_count', 1);
});

it('returns performance series', function () {
    PortfolioSnapshot::create([
        'user_id' => $this->user->id,
        'snapshot_date' => now()->subDays(2)->toDateString(),
        'total_value' => 100000,
        'total_cost' => 90000,
        'currency' => 'EUR',
        'active_count' => 1,
    ]);
    PortfolioSnapshot::create([
        'user_id' => $this->user->id,
        'snapshot_date' => now()->subDay()->toDateString(),
        'total_value' => 105000,
        'total_cost' => 90000,
        'currency' => 'EUR',
        'active_count' => 1,
    ]);

    $this->getJson('/api/v1/dashboard/performance')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.total_value', fn ($v) => floatval($v) === 100000.0)
        ->assertJsonPath('data.1.total_value', fn ($v) => floatval($v) === 105000.0);
});

it('only shows the authenticated user dashboard', function () {
    Investment::factory()->for(User::factory()->create())->create([
        'asset_type_id' => $this->realEstateType->id, 'manual_value' => 999999,
    ]);

    $this->getJson('/api/v1/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.active_count', 0);
});
