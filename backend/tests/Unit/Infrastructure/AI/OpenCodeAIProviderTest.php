<?php

use App\Domain\AI\ChatMessage;
use App\Infrastructure\AI\OpenCode\OpenCodeAIProvider;
use Illuminate\Support\Facades\Http;

it('reports insufficient credits separately from an invalid API key', function () {
    Http::fake([
        'opencode.ai/*' => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'CreditsError',
                'message' => 'Insufficient balance.',
            ],
        ], 401),
    ]);

    $stream = (new OpenCodeAIProvider())->stream(
        'sk-test',
        'deepseek-v4-flash',
        [new ChatMessage('user', 'Bonjour')],
        'Tu es un assistant.'
    );

    expect(fn () => iterator_to_array($stream))
        ->toThrow(
            RuntimeException::class,
            'Solde OpenCode insuffisant. Vérifie la facturation de ton workspace OpenCode.'
        );
});

it('still reports an invalid API key for authentication errors', function () {
    Http::fake([
        'opencode.ai/*' => Http::response([
            'type' => 'error',
            'error' => [
                'type' => 'AuthError',
                'message' => 'Invalid API key.',
            ],
        ], 401),
    ]);

    $stream = (new OpenCodeAIProvider())->stream(
        'sk-test',
        'deepseek-v4-flash',
        [new ChatMessage('user', 'Bonjour')],
        'Tu es un assistant.'
    );

    expect(fn () => iterator_to_array($stream))
        ->toThrow(
            RuntimeException::class,
            'Clé API OpenCode invalide. Vérifie tes paramètres.'
        );
});
