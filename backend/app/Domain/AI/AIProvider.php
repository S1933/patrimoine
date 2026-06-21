<?php

namespace App\Domain\AI;

interface AIProvider
{
    /**
     * @param  list<ChatMessage>  $messages
     * @return \Generator<int, string, void, void>
     */
    public function stream(string $apiKey, string $model, array $messages, string $systemPrompt): \Generator;

    public function defaultModel(): string;
}
