<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'opencode' => [
        'default_provider' => env('OPENCODE_PROVIDER', 'zen'),
        'api_key' => env('OPENCODE_API_KEY'),
        'base_url' => env('OPENCODE_BASE_URL', env('OPENCODE_ZEN_BASE_URL', 'https://opencode.ai/zen/v1')),
        'timeout' => (int) env('OPENCODE_TIMEOUT', 90),
        'providers' => [
            'zen' => [
                'label' => 'Zen',
                'base_url' => env('OPENCODE_ZEN_BASE_URL', 'https://opencode.ai/zen/v1'),
                'default_model' => 'gpt-5.2',
                'models' => [
                    'gpt-5.2' => ['label' => 'GPT 5.2', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'claude-sonnet-4.5' => ['label' => 'Claude Sonnet 4.5', 'group' => 'recommandés', 'endpoint' => 'messages'],
                    'gemini-3.5-flash' => ['label' => 'Gemini 3.5 Flash', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'glm-5.1' => ['label' => 'GLM 5.1', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'kimi-k2.5' => ['label' => 'Kimi K2.5', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'deepseek-v4-flash' => ['label' => 'DeepSeek V4 Flash', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'big-pickle' => ['label' => 'Big Pickle', 'group' => 'gratuits', 'endpoint' => 'chat/completions'],
                    'deepseek-v4-flash-free' => ['label' => 'DeepSeek V4 Flash Free', 'group' => 'gratuits', 'endpoint' => 'chat/completions'],
                ],
            ],
            'go' => [
                'label' => 'Go',
                'base_url' => env('OPENCODE_GO_BASE_URL', 'https://opencode.ai/zen/go/v1'),
                'default_model' => 'glm-5.2',
                'models' => [
                    'glm-5.2' => ['label' => 'GLM 5.2', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'qwen3.7-max' => ['label' => 'Qwen 3.7 Max', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'kimi-k2.7-code' => ['label' => 'Kimi K2.7 Code', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'mimo-v2.5-pro' => ['label' => 'MiMo V2.5 Pro', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'deepseek-v4-pro' => ['label' => 'DeepSeek V4 Pro', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'qwen3.7-plus' => ['label' => 'Qwen 3.7 Plus', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'minimax-m3' => ['label' => 'MiniMax M3', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'mimo-v2.5' => ['label' => 'MiMo V2.5', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'deepseek-v4-flash' => ['label' => 'DeepSeek V4 Flash', 'group' => 'recommandés', 'endpoint' => 'chat/completions'],
                    'big-pickle' => ['label' => 'Big Pickle', 'group' => 'gratuits', 'endpoint' => 'chat/completions'],
                ],
            ],
        ],
    ],

    'pricing' => [
        'coingecko' => [
            'key' => env('PROVIDER_COINGECKO_KEY') ?: null,
        ],
        'goldapi' => [
            'key' => env('PROVIDER_GOLDAPI_KEY') ?: null,
        ],
        'twelve_data' => [
            'key' => env('PROVIDER_TWELVEDATA_KEY') ?: null,
            'rate_limit_per_min' => 8,
        ],
        'finnhub' => [
            'key' => env('PROVIDER_FINNHUB_KEY') ?: null,
        ],
        'openfigi' => [
            'key' => env('PROVIDER_OPENFIGI_KEY') ?: null,
        ],
    ],

];
