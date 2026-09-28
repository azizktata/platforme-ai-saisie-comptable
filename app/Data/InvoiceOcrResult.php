<?php

namespace App\Data;

final readonly class InvoiceOcrResult
{
    /**
     * @param array<string, mixed> $response Raw provider response, retained for audit.
     * @param array<string, mixed> $invoiceData Validated structured invoice annotation.
     * @param array<string, mixed> $usage Provider usage metadata.
     */
    public function __construct(
        public array $response,
        public array $invoiceData,
        public string $model,
        public array $usage,
    ) {}
}
