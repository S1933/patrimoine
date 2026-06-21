<?php

namespace App\Application\AI;

use App\Domain\AI\ChatMessage;
use App\Infrastructure\AI\OpenCode\OpenCodeAIProvider;
use App\Models\User;

final readonly class ChatService
{
    public function __construct(
        private PortfolioContext $portfolio,
    ) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return \Generator<int, string, void, void>
     */
    public function stream(User $user, array $messages, ?string $model = null): \Generator
    {
        $user = $user->fresh();
        $apiKey = $this->userSetting($user, 'opencode_api_key')
            ?: config('services.opencode.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Configure ta clé API OpenCode dans les paramètres.');
        }

        $provider = $this->userSetting($user, 'opencode_provider')
            ?: config('services.opencode.default_provider', 'zen');
        $providerClient = new OpenCodeAIProvider(baseUrl: $this->baseUrlFor($provider));

        $model = $model ?: $this->userSetting($user, 'opencode_model') ?: $providerClient->defaultModel();
        $systemPrompt = $this->portfolio->build($user->id, $user->base_currency);

        $chatMessages = array_map(
            fn (array $m) => ChatMessage::fromArray($m),
            $messages
        );

        yield from $providerClient->stream($apiKey, $model, $chatMessages, $systemPrompt);
    }

    private function baseUrlFor(string $provider): string
    {
        return config(
            "services.opencode.providers.{$provider}.base_url",
            config('services.opencode.base_url', 'https://opencode.ai/zen/v1')
        );
    }

    private function userSetting(User $user, string $key): mixed
    {
        return array_key_exists($key, $user->getAttributes())
            ? $user->getAttribute($key)
            : null;
    }
}
