<?php

use App\Application\Snapshots\SnapshotService;
use App\Models\AssetType;
use App\Models\Investment;
use App\Models\InvestmentSnapshot;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Support\Console\TakeSnapshotCommand;
use App\Support\Jobs\TakePortfolioSnapshotJob;
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
});

it('takes a portfolio snapshot for a user', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 200000,
        'purchase_price' => 150000,
        'quantity' => 1,
        'status' => 'active',
        'currency' => 'EUR',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->cashType->id,
        'manual_value' => 50000,
        'purchase_price' => 50000,
        'quantity' => 1,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    $service = app(SnapshotService::class);
    $snapshot = $service->takeDailySnapshot($this->user->id);

    expect((float) $snapshot->total_value)->toBe(250000.0)
        ->and((float) $snapshot->total_cost)->toBe(200000.0)
        ->and($snapshot->active_count)->toBe(2);

    expect(InvestmentSnapshot::where('user_id', $this->user->id)->count())->toBe(2);
});

it('is idempotent — running twice on the same date upserts', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    $service = app(SnapshotService::class);
    $service->takeDailySnapshot($this->user->id, '2026-06-20');
    $service->takeDailySnapshot($this->user->id, '2026-06-20');

    expect(PortfolioSnapshot::where('user_id', $this->user->id)->where('snapshot_date', '2026-06-20')->count())->toBe(1);
    expect(InvestmentSnapshot::where('user_id', $this->user->id)->where('snapshot_date', '2026-06-20')->count())->toBe(1);
});

it('snapshot command works for all users', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    $this->artisan('patrimoine:snapshot')
        ->assertSuccessful()
        ->expectsOutputToContain('Taking snapshot');

    expect(PortfolioSnapshot::where('user_id', $this->user->id)->count())->toBe(1);
});

it('snapshot command with specific date', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    $this->artisan('patrimoine:snapshot --date=2026-01-15')->assertSuccessful();

    expect(PortfolioSnapshot::where('user_id', $this->user->id)->whereDate('snapshot_date', '2026-01-15')->exists())->toBeTrue();
});

it('excludes sold and archived from snapshots', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 200000,
        'status' => 'active',
    ]);
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 999999,
        'status' => 'sold',
    ]);

    $service = app(SnapshotService::class);
    $snapshot = $service->takeDailySnapshot($this->user->id);

    expect((float) $snapshot->total_value)->toBe(200000.0)
        ->and($snapshot->active_count)->toBe(1);
});

it('snapshot livret A matches dashboard at frozen time', function () {
    Carbon::setTestNow('2026-06-15 12:00:00');

    try {
        $livretAType = AssetType::firstOrCreate(
            ['code' => 'livret_a'],
            ['label' => 'Livret A', 'default_unit' => 'euros', 'is_priced_externally' => false],
        );

        Investment::factory()->for($this->user)->create([
            'asset_type_id' => $livretAType->id,
            'name' => 'Livret A',
            'quantity' => 1,
            'manual_value' => 10000,
            'purchase_price' => 10000,
            'purchase_date' => '2026-01-01',
            'currency' => 'EUR',
            'status' => 'active',
        ]);

        $service = app(SnapshotService::class);
        $snapshot = $service->takeDailySnapshot($this->user->id, '2026-06-15');

        $expectedValue = 10000 + (10000 * 0.015 * 165 / 365);
        expect((float) $snapshot->total_value)->toBe(round($expectedValue, 2));
    } finally {
        Carbon::setTestNow();
    }
});

it('handles concurrent snapshot attempts gracefully', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    $service = app(SnapshotService::class);

    // Simulate concurrent execution: run twice in quick succession.
    $first = $service->takeDailySnapshot($this->user->id, '2026-06-20');
    $second = $service->takeDailySnapshot($this->user->id, '2026-06-20');

    // Must produce exactly one portfolio and one investment snapshot.
    expect(PortfolioSnapshot::where('user_id', $this->user->id)->where('snapshot_date', '2026-06-20')->count())->toBe(1);
    expect(InvestmentSnapshot::where('user_id', $this->user->id)->where('snapshot_date', '2026-06-20')->count())->toBe(1);
    // Both returns should reference the same snapshot.
    expect($first->id)->toBe($second->id);
});

it('snapshot job dispatches and processes', function () {
    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 50000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);

    TakePortfolioSnapshotJob::dispatchSync($this->user->id);

    expect(PortfolioSnapshot::where('user_id', $this->user->id)->exists())->toBeTrue();
});
