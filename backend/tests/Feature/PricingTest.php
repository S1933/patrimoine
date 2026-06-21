<?php

use App\Application\Pricing\FetchInvestmentPrice;
use App\Domain\Pricing\AssetTypeCode;
use App\Models\ApiSyncLog;
use App\Models\AssetPrice;
use App\Models\AssetType;
use App\Models\Investment;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'web');
    $this->withHeaders(['Origin' => 'http://localhost:3000']);

    $this->realEstateType = AssetType::firstOrCreate(
        ['code' => 'real_estate'],
        ['label' => 'Immobilier', 'default_unit' => 'euros', 'is_priced_externally' => false, 'default_provider' => 'manual'],
    );
    $this->cryptoType = AssetType::firstOrCreate(
        ['code' => 'crypto'],
        ['label' => 'Cryptomonnaie', 'default_unit' => 'unit', 'is_priced_externally' => true, 'default_provider' => 'coingecko'],
    );
    $this->goldType = AssetType::firstOrCreate(
        ['code' => 'gold'],
        ['label' => 'Or', 'default_unit' => 'gramme', 'is_priced_externally' => true, 'default_provider' => 'goldapi'],
    );
});

it('fetches a manual price for a real estate investment', function () {
    $investment = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'name' => 'Appartement Paris',
        'quantity' => 1,
        'unit' => 'euros',
        'manual_value' => 250000,
        'currency' => 'EUR',
    ]);

    /** @var FetchInvestmentPrice $service */
    $service = app(FetchInvestmentPrice::class);
    $result = $service->execute($investment);

    expect($result->status)->toBe('success')
        ->and($result->source)->toBe('manual')
        ->and($result->price)->toBe(250000.0);

    $this->assertDatabaseHas('asset_prices', [
        'investment_id' => $investment->id,
        'price' => 250000,
        'source_status' => 'success',
    ]);
    $this->assertDatabaseHas('api_sync_logs', [
        'investment_id' => $investment->id,
        'status' => 'success',
    ]);
});

it('records an error when manual value is missing', function () {
    $investment = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => null,
        'currency' => 'EUR',
    ]);

    $service = app(FetchInvestmentPrice::class);
    $result = $service->execute($investment);

    expect($result->isError())->toBeTrue();

    $this->assertDatabaseMissing('asset_prices', ['investment_id' => $investment->id]);
    $this->assertDatabaseHas('api_sync_logs', [
        'investment_id' => $investment->id,
        'status' => 'error',
    ]);
});

it('falls back to last known price when no provider succeeds', function () {
    $investment = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => null,
        'currency' => 'EUR',
    ]);

    // Seed a previous successful price.
    AssetPrice::create([
        'investment_id' => $investment->id,
        'provider_id' => null,
        'price' => 200000,
        'currency' => 'EUR',
        'fetched_at' => now()->subDay(),
        'source_status' => 'success',
    ]);

    $service = app(FetchInvestmentPrice::class);
    $result = $service->execute($investment);

    expect($result->status)->toBe('fallback')
        ->and($result->price)->toBe(200000.0);
});

it('triggers price refresh via API endpoint for manual investment', function () {
    $investment = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 300000,
        'currency' => 'EUR',
    ]);

    $this->postJson("/api/v1/investments/{$investment->id}/refresh-price")
        ->assertOk()
        ->assertJsonPath('data.name', $investment->name)
        ->assertJsonPath('meta.pricing_status', 'success')
        ->assertJsonPath('meta.pricing_source', 'manual');
});

it('forbids refresh for another user investment', function () {
    $other = Investment::factory()->for(User::factory()->create())->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
    ]);

    $this->postJson("/api/v1/investments/{$other->id}/refresh-price")->assertForbidden();
});

it('handles multiple price fetches and keeps history', function () {
    $investment = Investment::factory()->for($this->user)->create([
        'asset_type_id' => $this->realEstateType->id,
        'manual_value' => 100000,
        'currency' => 'EUR',
    ]);

    $service = app(FetchInvestmentPrice::class);
    $service->execute($investment);

    $investment->update(['manual_value' => 110000]);
    $service->execute($investment);

    expect(AssetPrice::where('investment_id', $investment->id)->count())->toBe(2);
    expect(ApiSyncLog::where('investment_id', $investment->id)->count())->toBe(2);
});

it('has correct asset type code enum', function () {
    expect(AssetTypeCode::Crypto->isExternallyPriced())->toBeTrue();
    expect(AssetTypeCode::Gold->isExternallyPriced())->toBeTrue();
    expect(AssetTypeCode::RealEstate->isExternallyPriced())->toBeFalse();
    expect(AssetTypeCode::LivretA->isExternallyPriced())->toBeFalse();
    expect(AssetTypeCode::Ldds->defaultProviderCode())->toBe('manual');
    expect(AssetTypeCode::Crypto->defaultProviderCode())->toBe('coingecko');
    expect(AssetTypeCode::Gold->defaultProviderCode())->toBe('goldapi');
});
