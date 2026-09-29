<?php

namespace App\Data;

final readonly class InvoiceDataExtractionResult
{
    /**
     * @param array<string, mixed> $invoiceData
     * @param array<string, mixed> $response
     * @param array<string, mixed> $usage
     */
    public function __construct(
        public array $invoiceData,
        public array $response,
        public string $model,
        public array $usage,
    ) {}
}
