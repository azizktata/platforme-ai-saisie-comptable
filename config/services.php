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
    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai'),
        'ocr_model' => env('MISTRAL_OCR_MODEL', 'mistral-ocr-latest'),
        'ocr_timeout' => (int) env('MISTRAL_OCR_TIMEOUT', 180),
        'ca_bundle' => env('MISTRAL_CA_BUNDLE'),
    ],
];
