<?php

namespace App\Infrastructure\AI\OpenCode;

use App\Domain\AI\AIProvider;
use App\Domain\AI\ChatMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class OpenCodeAIProvider implements AIProvider
{
    private const int TIMEOUT = 90;

    public function __construct(
        private string $baseUrl = 'https://opencode.ai/zen/v1',
    ) {}

    public function defaultModel(): string
    {
        return 'gpt-5.2';
    }

    public function stream(string $apiKey, string $model, array $messages, string $systemPrompt): \Generator
    {
        $endpoint = $this->resolveEndpoint($model);
        $url = rtrim($this->baseUrl, '/').'/'.$endpoint;

        $body = $this->buildBody($endpoint, $model, $messages, $systemPrompt);

        $response = Http::timeout(self::TIMEOUT)
            ->withToken($apiKey)
            ->withOptions(['stream' => true])
            ->withHeaders([
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
            ])
            ->post($url, $body);

        if (! $response->successful()) {
            $status = $response->status();
            $errorBody = $response->body();
            Log::warning('OpenCode AI API error', [
                'status' => $status,
                'base_url' => $this->baseUrl,
                'model' => $model,
                'endpoint' => $endpoint,
                'body' => mb_substr($errorBody, 0, 500),
            ]);

            if ($status === 401) {
                throw new \RuntimeException($this->authenticationErrorMessage($errorBody));
            }

            throw new \RuntimeException(
                match ($status) {
                    429 => 'Limite de taux OpenCode atteinte. Réessaie plus tard.',
                    422 => 'Requête OpenCode invalide. Vérifie le modèle sélectionné.',
                    default => "Erreur API OpenCode ($status)",
                }
            );
        }

        $stream = $response->toPsrResponse()->getBody();

        while (! $stream->eof()) {
            $line = $this->readLine($stream);
            if ($line === null) {
                continue;
            }

            $chunk = $this->parseChunk($endpoint, $line);
            if ($chunk !== null) {
                yield $chunk;
            }

            if ($this->isDone($endpoint, $line)) {
                break;
            }
        }
    }

    private function authenticationErrorMessage(string $body): string
    {
        $errorType = data_get(json_decode($body, true), 'error.type');

        return $errorType === 'CreditsError'
            ? 'Solde OpenCode insuffisant. Vérifie la facturation de ton workspace OpenCode.'
            : 'Clé API OpenCode invalide. Vérifie tes paramètres.';
    }

    private function resolveEndpoint(string $model): string
    {
        $config = config("services.opencode.models.{$model}");

        return match ($config['endpoint'] ?? 'chat/completions') {
            'messages' => 'messages',
            'responses' => 'responses',
            default => 'chat/completions',
        };
    }

    private function buildBody(string $endpoint, string $model, array $messages, string $systemPrompt): array
    {
        $payload = [
            'stream' => true,
        ];

        if ($endpoint === 'messages') {
            $payload['model'] = $model;
            $payload['system'] = $systemPrompt;
            $payload['max_tokens'] = 4096;

            $payload['messages'] = array_map(
                fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content],
                $messages
            );
        } else {
            $payload['model'] = $model;
            $payload['messages'] = array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                array_map(
                    fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content],
                    $messages
                )
            );
        }

        return $payload;
    }

    private function readLine($stream): ?string
    {
        $line = '';
        while (! $stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $line .= $byte;
        }

        return $line === '' ? null : $line;
    }

    private function parseChunk(string $endpoint, string $line): ?string
    {
        if (! str_starts_with($line, 'data: ')) {
            return null;
        }

        $data = substr($line, 6);

        if ($data === '[DONE]') {
            return null;
        }

        $json = json_decode($data, true);
        if ($json === null) {
            return null;
        }

        if ($endpoint === 'messages') {
            return $json['delta']['text'] ?? $json['content_block']['text'] ?? null;
        }

        return $json['choices'][0]['delta']['content']
            ?? $json['choices'][0]['text']
            ?? null;
    }

    private function isDone(string $endpoint, string $line): bool
    {
        if ($endpoint === 'messages') {
            return str_contains($line, '"type":"message_stop"')
                || str_contains($line, '"type":"message_delta"');
        }

        return str_starts_with($line, 'data: [DONE]');
    }
}
