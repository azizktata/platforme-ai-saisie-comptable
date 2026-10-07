<?php

namespace App\Services\Ocr;

use App\Contracts\OcrProvider;
use App\Data\InvoiceOcrResult;
use App\Exceptions\AiProviderException;
use App\Exceptions\OcrProviderException;
use App\Models\Invoice;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OpenRouterOcrProvider implements OcrProvider
{
    // private ?string $model = null;

    public function __construct(
        private readonly InvoiceOcrSchema $schema,
        private readonly OpenRouterClient $client,
    ) {
    }

    /** Override the model for one instance (tinker, tests, A/B comparisons). */
    // public function withModel(string $model): static
    // {
    //     $clone = clone $this;
    //     $clone->model = $model;

    //     return $clone;
    // }

    public function extract(Invoice $invoice): InvoiceOcrResult
    {
        $dataUrl = $this->imageDataUrl($invoice->mime_type, $this->readSource($invoice));
        $models = config('services.openrouter.ocr_models', []);

        $messages = [

            [
                'role' => 'user',
                // text first, then image (OpenRouter's recommended order)
                'content' => [
                   ['type' => 'text', 'text' => '
                        from this supplier invoice image Extract the invoice directly into the required JSON structure. 

                        First, understand the invoice naturally: identify the supplier, customer, invoice
                        number, dates, amounts, taxes, fees, payment information, and all invoice line
                        items. Do not assume that the invoice layout matches the target schema.

                        Once you have identified the information from the image, map the extracted data
                        to the required JSON structure.

                        Return only the final JSON matching the provided schema.
                        Do not invent values. If a value is not visible or cannot be determined, use null.
                        Preserve the values exactly as shown on the invoice whenever possible.
                    '],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                ],
            ],
        ];

        try {
            $result = $this->client->completeJson(
                $messages,
                $this->schema->responseFormat(),
                maxTokens: 4000, // reasoning models spend tokens thinking before answering
                models: $models,
            );
        } catch (AiProviderException $e) {
            // ASSUMPTION: adjust property/getter names to your exception class
            throw new OcrProviderException(
                $e->errorCode,
                $e->retryable,
                $e->getMessage(),
                $e->rawResponse,
                diagnostic: $e->diagnostic,
            );
        }

        try {
            $invoiceData = $this->schema->validate($result->data);
        } catch (\InvalidArgumentException) {
            throw new OcrProviderException(
                'invalid_structured_annotation',
                false,
                'Les données structurées OCR ne respectent pas le schéma de facture attendu.',
                $result->response,
            );
        }

        return new InvoiceOcrResult(
            response: $result->response,
            invoiceData: $invoiceData,
            model: $result->model,
            usage: $result->usage,
            text: null, // no OCR text in this approach
        );
    }

    private function readSource(Invoice $invoice): string
    {
        if ($invoice->storage_disk !== 'local') {
            throw new OcrProviderException('unsupported_storage_disk', false,
                'Le document source n’est pas disponible sur le stockage privé attendu.');
        }

        if (! str_starts_with($invoice->file_path, "companies/{$invoice->company_id}/invoices/")) {
            throw new OcrProviderException('source_file_invalid_path', false,
                'Le chemin du document source ne correspond pas à sa société.');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($invoice->file_path)) {
            throw new OcrProviderException('source_file_missing', false,
                'Le document source privé est introuvable.');
        }

        try {
            $contents = $disk->get($invoice->file_path);
        } catch (Throwable) {
            $contents = null;
        }

        if (! is_string($contents) || $contents === '') {
            throw new OcrProviderException('source_file_unreadable', false,
                'Le document source privé n’a pas pu être lu.');
        }

        return $contents;
    }

    private function imageDataUrl(string $mimeType, string $contents): string
    {
        if (! in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
            // PDFs are not handled here (see notes below)
            throw new OcrProviderException('unsupported_document_type', false,
                'Ce fournisseur n’accepte que les images JPEG ou PNG.');
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}