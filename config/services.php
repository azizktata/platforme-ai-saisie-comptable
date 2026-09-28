<?php

return [
    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'base_url' => env('MISTRAL_BASE_URL', 'https://api.mistral.ai'),
        'ocr_model' => env('MISTRAL_OCR_MODEL', 'mistral-ocr-latest'),
        'ocr_timeout' => (int) env('MISTRAL_OCR_TIMEOUT', 180),
    ],
];
