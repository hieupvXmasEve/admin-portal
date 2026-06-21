<?php

declare(strict_types=1);

return [
    'default_provider' => 'openai',
    'default_model' => 'gpt-4o-mini',
    'timeout_seconds' => 10,
    'providers' => [
        'openai' => [
            'id' => 'openai',
            'label' => 'OpenAI',
            'sdk_lab' => 'openai',
            'models' => [
                ['id' => 'gpt-4.1', 'label' => 'GPT-4.1'],
                ['id' => 'gpt-4o-mini', 'label' => 'GPT-4o mini'],
                ['id' => 'gpt-4.1-mini', 'label' => 'GPT-4.1 mini'],
                ['id' => 'gpt-4.1-nano', 'label' => 'GPT-4.1 nano'],
                ['id' => 'gpt-4o', 'label' => 'GPT-4o'],
                ['id' => 'o4-mini', 'label' => 'o4-mini'],
                ['id' => 'o3', 'label' => 'o3'],
                ['id' => 'o3-mini', 'label' => 'o3-mini'],
            ],
        ],
        'openrouter' => [
            'id' => 'openrouter',
            'label' => 'OpenRouter',
            'sdk_lab' => 'openrouter',
            'chat_completions_url' => env('OPENROUTER_CHAT_COMPLETIONS_URL', 'https://openrouter.ai/api/v1/chat/completions'),
            'models_source' => 'openrouter_api',
            'models_url' => env('OPENROUTER_MODELS_URL', 'https://openrouter.ai/api/v1/models'),
            'models_cache_ttl_seconds' => env('OPENROUTER_MODELS_CACHE_TTL_SECONDS', 21600),
            'models' => [
                ['id' => 'openrouter/auto', 'label' => 'Auto Router'],
                ['id' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o mini (OpenRouter)'],
                ['id' => 'openrouter/free', 'label' => 'Free Models Router'],
            ],
        ],
        'anthropic' => [
            'id' => 'anthropic',
            'label' => 'Anthropic',
            'sdk_lab' => 'anthropic',
            'models' => [
                ['id' => 'claude-opus-4-1-20250805', 'label' => 'Claude Opus 4.1'],
                ['id' => 'claude-opus-4-20250514', 'label' => 'Claude Opus 4'],
                ['id' => 'claude-sonnet-4-20250514', 'label' => 'Claude Sonnet 4'],
                ['id' => 'claude-3-5-haiku-latest', 'label' => 'Claude 3.5 Haiku'],
                ['id' => 'claude-3-7-sonnet-latest', 'label' => 'Claude 3.7 Sonnet'],
                ['id' => 'claude-3-5-sonnet-latest', 'label' => 'Claude 3.5 Sonnet'],
            ],
        ],
        'gemini' => [
            'id' => 'gemini',
            'label' => 'Google Gemini',
            'sdk_lab' => 'gemini',
            'models' => [
                ['id' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro'],
                ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash'],
                ['id' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash-Lite'],
                ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash'],
                ['id' => 'gemini-2.0-flash-lite', 'label' => 'Gemini 2.0 Flash-Lite'],
            ],
        ],
    ],
];
