<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'web');
    $this->withHeaders(['Origin' => 'http://localhost:3000']);
});

it('stores the OpenCode provider with the API key', function () {
    $this->putJson('/api/v1/chat/api-key', [
        'opencode_api_key' => 'sk-test-12345678',
        'opencode_provider' => 'go',
    ])->assertOk()
        ->assertJsonPath('data.has_key', true)
        ->assertJsonPath('data.provider', 'go');

    expect($this->user->refresh()->opencode_api_key)->toBe('sk-test-12345678');
    expect($this->user->refresh()->opencode_provider)->toBe('go');

    $this->getJson('/api/v1/chat/models')
        ->assertOk()
        ->assertJsonPath('data.provider', 'go')
        ->assertJsonPath('data.model', 'glm-5.2');
});

it('rejects an unknown OpenCode provider', function () {
    $this->putJson('/api/v1/chat/api-key', [
        'opencode_api_key' => 'sk-test-12345678',
        'opencode_provider' => 'unknown',
    ])->assertJsonValidationErrors(['opencode_provider']);
});

it('uses the OpenCode API key from env when no user key is stored', function () {
    config()->set('services.opencode.api_key', 'sk-env-12345678');

    $this->getJson('/api/v1/chat/models')
        ->assertOk()
        ->assertJsonPath('data.has_key', true);
});
