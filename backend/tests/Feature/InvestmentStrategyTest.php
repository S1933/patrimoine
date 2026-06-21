<?php

use App\Models\AssetType;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'web');
    $this->withHeaders(['Origin' => 'http://localhost:3000']);

    $this->cryptoType = AssetType::firstOrCreate(
        ['code' => 'crypto'],
        ['label' => 'Cryptomonnaie', 'default_unit' => 'unit', 'is_priced_externally' => true],
    );
    $this->etfType = AssetType::firstOrCreate(
        ['code' => 'etf'],
        ['label' => 'ETF', 'default_unit' => 'part', 'is_priced_externally' => true],
    );
    $this->cashType = AssetType::firstOrCreate(
        ['code' => 'cash'],
        ['label' => 'Cash', 'default_unit' => 'euros', 'is_priced_externally' => false],
    );
});

it('stores and returns a complete investment strategy', function () {
    $this->putJson('/api/v1/investment-strategy', [
        'allocations' => [
            ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 5],
            ['asset_type_id' => $this->etfType->id, 'target_percent' => 80],
            ['asset_type_id' => $this->cashType->id, 'target_percent' => 15],
        ],
    ])->assertOk()
        ->assertJsonPath('data.total_percent', fn ($value) => (float) $value === 100.0);

    $this->getJson('/api/v1/investment-strategy')
        ->assertOk()
        ->assertJsonPath('data.total_percent', fn ($value) => (float) $value === 100.0)
        ->assertJsonFragment([
            'asset_type_id' => $this->cryptoType->id,
            'code' => 'crypto',
            'label' => 'Cryptomonnaie',
            'target_percent' => 5,
        ])
        ->assertJsonFragment([
            'asset_type_id' => $this->etfType->id,
            'code' => 'etf',
            'label' => 'ETF',
            'target_percent' => 80,
        ])
        ->assertJsonFragment([
            'asset_type_id' => $this->cashType->id,
            'code' => 'cash',
            'label' => 'Cash',
            'target_percent' => 15,
        ]);
});

it('rejects a strategy whose total is not exactly one hundred percent', function () {
    $this->putJson('/api/v1/investment-strategy', [
        'allocations' => [
            ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 5],
            ['asset_type_id' => $this->etfType->id, 'target_percent' => 80],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('allocations');
});

it('replaces only the authenticated user strategy', function () {
    $otherUser = User::factory()->create();

    $this->putJson('/api/v1/investment-strategy', [
        'allocations' => [
            ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 100],
        ],
    ])->assertOk();

    $this->actingAs($otherUser, 'web')
        ->putJson('/api/v1/investment-strategy', [
            'allocations' => [
                ['asset_type_id' => $this->cashType->id, 'target_percent' => 100],
            ],
        ])->assertOk();

    $this->actingAs($this->user, 'web')
        ->putJson('/api/v1/investment-strategy', [
            'allocations' => [
                ['asset_type_id' => $this->etfType->id, 'target_percent' => 100],
            ],
        ])->assertOk();

    $this->getJson('/api/v1/investment-strategy')
        ->assertOk()
        ->assertJsonFragment(['code' => 'etf', 'target_percent' => 100])
        ->assertJsonFragment(['code' => 'crypto', 'target_percent' => 0])
        ->assertJsonFragment(['code' => 'cash', 'target_percent' => 0]);

    $this->actingAs($otherUser, 'web')
        ->getJson('/api/v1/investment-strategy')
        ->assertOk()
        ->assertJsonFragment(['code' => 'cash', 'target_percent' => 100])
        ->assertJsonFragment(['code' => 'etf', 'target_percent' => 0]);
});

it('rejects duplicate, unknown, negative and over-precise allocations', function (array $allocations, array $errors) {
    $this->putJson('/api/v1/investment-strategy', [
        'allocations' => $allocations,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'duplicate type' => fn () => [[
        ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 50],
        ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 50],
    ], ['allocations.1.asset_type_id']],
    'unknown type' => [[
        ['asset_type_id' => 999999, 'target_percent' => 100],
    ], ['allocations.0.asset_type_id']],
    'negative percent' => fn () => [[
        ['asset_type_id' => $this->cryptoType->id, 'target_percent' => -1],
        ['asset_type_id' => $this->etfType->id, 'target_percent' => 101],
    ], ['allocations.0.target_percent', 'allocations.1.target_percent']],
    'more than two decimals' => fn () => [[
        ['asset_type_id' => $this->cryptoType->id, 'target_percent' => 5.555],
        ['asset_type_id' => $this->etfType->id, 'target_percent' => 94.445],
    ], ['allocations.0.target_percent', 'allocations.1.target_percent']],
]);
