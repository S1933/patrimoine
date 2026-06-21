<?php

use App\Models\AssetType;
use App\Models\Investment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'web');
    $this->withHeaders(['Origin' => 'http://localhost:3000']);

    $this->realEstateType = AssetType::firstOrCreate(
        ['code' => 'real_estate'],
        ['label' => 'Immobilier', 'default_unit' => 'euros', 'is_priced_externally' => false],
    );

    Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'name' => 'Appart',
        'manual_value' => 200000,
        'status' => 'active',
        'currency' => 'EUR',
    ]);
});

it('exports portfolio as JSON', function () {
    $this->getJson('/api/v1/exports/portfolio.json')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['exported_at', 'user', 'summary', 'investments'],
        ])
        ->assertJsonPath('data.user.email', $this->user->email)
        ->assertJsonPath('data.investments.0.name', 'Appart');
});

it('exports portfolio as CSV', function () {
    $response = $this->get('/api/v1/exports/portfolio.csv');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();
    expect($content)->toContain('Name')
        ->and($content)->toContain('Appart')
        ->and($content)->toContain('Immobilier')
        ->and($content)->toContain('200000');
});

it('only exports the authenticated user investments', function () {
    Investment::factory()->for(User::factory()->create())->create([
        'name' => 'Not mine', 'asset_type_id' => $this->realEstateType->id, 'manual_value' => 999999,
    ]);

    $this->getJson('/api/v1/exports/portfolio.json')
        ->assertOk()
        ->assertJsonMissing(['name' => 'Not mine']);
});
