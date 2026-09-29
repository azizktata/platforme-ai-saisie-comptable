<?php

return [
    'ocr' => [
        'provider' => env('OCR_PROVIDER', 'ocr_space'),
    ],
    'ocr_space' => [
        'api_key' => env('OCR_SPACE_API_KEY'),
        'endpoint' => env('OCR_SPACE_ENDPOINT', 'https://api.ocr.space/parse/image'),
        'ocr_engine' => (int) env('OCR_SPACE_ENGINE', 3),
        'timeout' => (int) env('OCR_SPACE_TIMEOUT', 180),
        'max_file_size_bytes' => 1024 * 1024,
        'ca_bundle' => env('OCR_SPACE_CA_BUNDLE', env('MISTRAL_CA_BUNDLE')),
    ],
    'invoice_extraction' => [
        'provider' => env('INVOICE_EXTRACTION_PROVIDER', 'openrouter'),
        'model' => env('OPENROUTER_EXTRACTION_MODEL', 'qwen/qwen-2.5-7b-instruct'),
        'max_tokens' => (int) env('OPENROUTER_EXTRACTION_MAX_TOKENS', 2500),
    ],
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'endpoint' => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 120),
        'site_url' => env('OPENROUTER_SITE_URL'),
        'app_name' => env('OPENROUTER_APP_NAME', env('APP_NAME', 'ComptaFlow')),
        'ca_bundle' => env('OPENROUTER_CA_BUNDLE'),
        'max_ocr_chars' => (int) env('OPENROUTER_MAX_OCR_CHARS', 100000),
    ],
    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai'),
        'ocr_model' => env('MISTRAL_OCR_MODEL', 'mistral-ocr-latest'),
        'ocr_timeout' => (int) env('MISTRAL_OCR_TIMEOUT', 180),
        'ca_bundle' => env('MISTRAL_CA_BUNDLE'),
    ],
];
