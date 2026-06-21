<?php

namespace App\Providers;

use App\Domain\AI\AIProvider;
use App\Infrastructure\AI\OpenCode\OpenCodeAIProvider;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AIProvider::class, fn () => new OpenCodeAIProvider(
            baseUrl: config('services.opencode.base_url', 'https://opencode.ai/zen/v1'),
        ));
    }
}
