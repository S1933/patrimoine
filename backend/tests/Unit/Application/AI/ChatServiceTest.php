<?php

use App\Application\AI\ChatService;

it('uses the provider-specific OpenCode base URL', function () {
    config()->set('services.opencode.base_url', 'https://opencode.ai/zen/v1');
    config()->set('services.opencode.providers.go.base_url', 'https://opencode.ai/zen/go/v1');

    $service = (new ReflectionClass(ChatService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ChatService::class, 'baseUrlFor');

    expect($method->invoke($service, 'go'))
        ->toBe('https://opencode.ai/zen/go/v1');
});
