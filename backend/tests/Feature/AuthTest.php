<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'jp@example.com',
        'password' => Hash::make('SuperSecret123!'),
    ]);
    // Pretend the SPA is the origin so Sanctum treats requests as stateful
    // (session + CSRF middleware only apply when Origin/Referer matches stateful domains).
    $this->withHeaders(['Origin' => 'http://localhost:3000']);
});

it('logs in a user with valid credentials and returns the user', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'jp@example.com',
        'password' => 'SuperSecret123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'base_currency']])
        ->assertJsonPath('data.email', 'jp@example.com');

    $this->assertAuthenticated('web');
});

it('rejects invalid credentials with 422', function () {
    $this->postJson('/api/v1/auth/login', [
        'email' => 'jp@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->assertGuest('web');
});

it('validates login payload', function () {
    $this->postJson('/api/v1/auth/login', [])->assertJsonValidationErrors(['email', 'password']);
});

it('returns the current user when authenticated', function () {
    $this->actingAs($this->user, 'web')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $this->user->id);
});

it('rejects me endpoint when unauthenticated', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('logs out and invalidates the session', function () {
    $this->actingAs($this->user, 'web')
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertGuest('web');
});

it('registers a new user and logs them in', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'VeryStrongPass123!',
        'password_confirmation' => 'VeryStrongPass123!',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    $this->assertAuthenticated('web');
});

it('refuses bypass auto-login when AUTH_BYPASS is true outside local/testing', function () {
    $this->app['env'] = 'production';
    config()->set('auth.bypass', true);

    $this->getJson('/api/v1/auth/me')
        ->assertStatus(500);

    $this->app['env'] = 'testing';
    config()->set('auth.bypass', false);
});

it('serves a CSRF cookie for the SPA', function () {
    $this->get('/sanctum/csrf-cookie')->assertNoContent();
});
