<?php

namespace App\Data;

final readonly class StructuredAiResult
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $response
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $data,
        public array $response,
        public string $model,
        public array $usage,
    ) {}
}
