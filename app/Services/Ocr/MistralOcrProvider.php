<?php

namespace App\Services\Ocr;

use App\Contracts\OcrProvider;
use App\Data\InvoiceOcrResult;
use App\Exceptions\OcrProviderException;
use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class MistralOcrProvider implements OcrProvider
{
    public function __construct(private readonly InvoiceOcrSchema $schema) {}

    public function extract(Invoice $invoice): InvoiceOcrResult
    {
        $apiKey = config('services.mistral.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new OcrProviderException(
                'configuration_missing',
                false,
                'La configuration du fournisseur OCR est absente.',
            );
        }

        if ($invoice->storage_disk !== 'local') {
            throw new OcrProviderException(
                'unsupported_storage_disk',
                false,
                'Le document source n’est pas disponible sur le stockage privé attendu.',
            );
        }

        $expectedPrefix = "companies/{$invoice->company_id}/invoices/";

        if (! str_starts_with($invoice->file_path, $expectedPrefix)) {
            throw new OcrProviderException(
                'source_file_invalid_path',
                false,
                'Le chemin du document source ne correspond pas à sa société.',
            );
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($invoice->file_path)) {
            throw new OcrProviderException(
                'source_file_missing',
                false,
                'Le document source privé est introuvable.',
            );
        }

        try {
            $contents = $disk->get($invoice->file_path);
        } catch (Throwable) {
            throw new OcrProviderException(
                'source_file_unreadable',
                false,
                'Le document source privé n’a pas pu être lu.',
            );
        }

        if (! is_string($contents) || $contents === '') {
            throw new OcrProviderException(
                'source_file_unreadable',
                false,
                'Le document source privé n’a pas pu être lu.',
            );
        }

        $document = $this->documentChunk($invoice->mime_type, $contents);
        $baseUrl = rtrim((string) config('services.mistral.base_url', 'https://api.mistral.ai'), '/');
        $timeout = max(1, (int) config('services.mistral.ocr_timeout', 180));

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->connectTimeout(min(30, $timeout))
                ->timeout($timeout)
                ->post($baseUrl.'/v1/ocr', [
                    'model' => config('services.mistral.ocr_model', 'mistral-ocr-latest'),
                    'document' => $document,
                    'document_annotation_format' => $this->schema->responseFormat(),
                    'document_annotation_prompt' => $this->schema->annotationPrompt(),
                    'include_image_base64' => false,
                    'include_blocks' => false,
                    'table_format' => 'markdown',
                ]);
        } catch (ConnectionException) {
            throw new OcrProviderException(
                'provider_unreachable',
                true,
                'Le service OCR est temporairement injoignable.',
            );
        }

        if (! $response->successful()) {
            $status = $response->status();
            $retryable = $status === 429 || $status >= 500;
            $errorCode = match (true) {
                $status === 401 || $status === 403 => 'provider_authentication_failed',
                $status === 429 => 'provider_rate_limited',
                $status >= 500 => 'provider_unavailable',
                default => 'provider_rejected_request',
            };

            throw new OcrProviderException(
                $errorCode,
                $retryable,
                $retryable
                    ? 'Le service OCR a temporairement refusé la demande.'
                    : 'Le service OCR n’a pas accepté le document ou la requête.',
            );
        }

        $providerResponse = $response->json();

        if (! is_array($providerResponse)
            || ! is_array($providerResponse['pages'] ?? null)
            || $providerResponse['pages'] === []
            || ! is_string($providerResponse['document_annotation'] ?? null)
            || ! is_string($providerResponse['model'] ?? null)
            || strlen($providerResponse['model']) > 100
            || trim($providerResponse['model']) === ''
        ) {
            throw new OcrProviderException(
                'invalid_provider_response',
                false,
                'La réponse OCR ne contient pas les données structurées attendues.',
                is_array($providerResponse) ? $providerResponse : null,
            );
        }

        try {
            $annotation = json_decode(
                $providerResponse['document_annotation'],
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            if (! is_array($annotation)) {
                throw new JsonException('The document annotation is not a JSON object.');
            }

            $invoiceData = $this->schema->validate($annotation);
        } catch (JsonException|\InvalidArgumentException) {
            throw new OcrProviderException(
                'invalid_structured_annotation',
                false,
                'Les données structurées OCR ne respectent pas le schéma de facture attendu.',
                $providerResponse,
            );
        }

        return new InvoiceOcrResult(
            response: $providerResponse,
            invoiceData: $invoiceData,
            model: $providerResponse['model'],
            usage: is_array($providerResponse['usage_info'] ?? null) ? $providerResponse['usage_info'] : [],
        );
    }

    private function documentChunk(string $mimeType, string $contents): array
    {
        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($contents);

        if ($mimeType === 'application/pdf') {
            return [
                'type' => 'document_url',
                'document_url' => $dataUrl,
            ];
        }

        if (in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            return [
                'type' => 'image_url',
                'image_url' => $dataUrl,
            ];
        }

        throw new OcrProviderException(
            'unsupported_document_type',
            false,
            'Le format du document n’est pas pris en charge par le fournisseur OCR.',
        );
    }
}
