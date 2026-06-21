<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\AI\ChatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreChatRequest;
use App\Http\Requests\Api\V1\StoreChatSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chat,
    ) {}

    public function models(Request $request): JsonResponse
    {
        $user = $request->user()->fresh();
        $provider = $this->opencodeProvider($user);
        $configured = config("services.opencode.providers.{$provider}.models", []);
        $hasKey = ! empty($this->opencodeApiKey($user));
        $defaultModel = config("services.opencode.providers.{$provider}.default_model", 'gpt-5.2');

        $models = [];
        foreach ($configured as $id => $cfg) {
            $models[] = [
                'id' => $id,
                'label' => $cfg['label'],
                'group' => $cfg['group'],
            ];
        }

        return response()->json([
            'data' => [
                'models' => $models,
                'has_key' => $hasKey,
                'model' => $this->opencodeModel($user) ?? $defaultModel,
                'provider' => $provider,
            ],
        ]);
    }

    public function apiKey(StoreChatSettingsRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('opencode_api_key')) {
            $user->opencode_api_key = $request->input('opencode_api_key');
        }

        if ($request->has('opencode_model')) {
            $user->opencode_model = $request->input('opencode_model');
        }

        if ($request->has('opencode_provider')) {
            $user->opencode_provider = $request->input('opencode_provider');
        }

        $user->save();
        $freshUser = $user->fresh();

        return response()->json([
            'data' => [
                'has_key' => ! empty($this->opencodeApiKey($freshUser)),
                'model' => $this->opencodeModel($freshUser),
                'provider' => $this->opencodeProvider($freshUser),
            ],
        ]);
    }

    public function deleteApiKey(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->opencode_api_key = null;
        $user->save();

        return response()->json([
            'data' => ['has_key' => false],
        ]);
    }

    public function stream(StoreChatRequest $request): StreamedResponse
    {
        $user = $request->user();

        return response()->stream(function () use ($request, $user) {
            ob_implicit_flush(true);
            @ob_end_flush();

            try {
                $gen = $this->chat->stream(
                    $user,
                    $request->input('messages'),
                    $request->input('model')
                );

                foreach ($gen as $chunk) {
                    $payload = json_encode(['content' => $chunk], JSON_UNESCAPED_UNICODE);
                    echo "data: {$payload}\n\n";

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            } catch (\Throwable $e) {
                $payload = json_encode([
                    'error' => $e->getMessage(),
                ], JSON_UNESCAPED_UNICODE);
                echo "data: {$payload}\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function opencodeProvider(User $user): string
    {
        return $this->userSetting($user, 'opencode_provider')
            ?: config('services.opencode.default_provider', 'zen');
    }

    private function opencodeModel(User $user): ?string
    {
        return $this->userSetting($user, 'opencode_model');
    }

    private function opencodeApiKey(User $user): ?string
    {
        return $this->userSetting($user, 'opencode_api_key')
            ?: config('services.opencode.api_key');
    }

    private function userSetting(User $user, string $key): mixed
    {
        return array_key_exists($key, $user->getAttributes())
            ? $user->getAttribute($key)
            : null;
    }
}
