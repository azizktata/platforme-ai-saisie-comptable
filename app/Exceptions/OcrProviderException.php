<?php

namespace App\Exceptions;

use RuntimeException;

class OcrProviderException extends RuntimeException
{
    /**
     * @param array<string, mixed>|null $rawResponse
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable,
        string $message,
        public readonly ?array $rawResponse = null,
        public readonly ?string $diagnostic = null,
    ) {
        parent::__construct($message);
    }
}
