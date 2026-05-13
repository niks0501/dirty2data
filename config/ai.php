<?php

return [
    'enabled' => env('AI_RECOMMENDATIONS_ENABLED', true),
    'provider' => env('AI_PROVIDER', 'gemini'),
    'model' => env('AI_MODEL', 'gemini-2.5-flash-lite'),
    'timeout' => (int) env('AI_TIMEOUT_SECONDS', 20),
    'max_sample_rows' => (int) env('AI_MAX_SAMPLE_ROWS', 30),
    'max_sample_values_per_column' => (int) env('AI_MAX_SAMPLE_VALUES_PER_COLUMN', 20),

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
    ],
];
