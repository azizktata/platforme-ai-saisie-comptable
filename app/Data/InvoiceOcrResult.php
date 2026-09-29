<?php

namespace App\Data;

final readonly class InvoiceOcrResult
{
    /**
     * @param array<string, mixed> $response Raw provider response, retained for audit.
     * @param array<string, mixed> $invoiceData Validated structured invoice annotation.
     * @param array<string, mixed> $usage Provider usage metadata.
     * @param string|null $text Recognized document text when available.
     * @param bool $hasStructuredData Whether invoiceData already came from the OCR provider.
     */
    public function __construct(
        public array $response,
        public array $invoiceData,
        public string $model,
        public array $usage,
        public ?string $text = null,
        public bool $hasStructuredData = true,
    ) {}
}
