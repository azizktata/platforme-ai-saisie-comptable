<?php

namespace App\Jobs;

use App\Contracts\OcrProvider;
use App\Exceptions\OcrProviderException;
use App\Models\Invoice;
use App\Services\Ocr\InvoiceTotalsConsistencyChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessInvoiceOcr implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 210;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $invoiceId,
        public int $companyId,
    ) {
        $this->onQueue('ocr');
    }

    public function uniqueId(): string
    {
        return "invoice-ocr:{$this->companyId}:{$this->invoiceId}";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(OcrProvider $provider, InvoiceTotalsConsistencyChecker $totalsChecker): void
    {
        $invoice = DB::transaction(function (): ?Invoice {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || ! in_array($invoice->status, ['ocr_queued', 'ocr_processing'], true)) {
                return null;
            }

            $invoice->forceFill([
                'status' => 'ocr_processing',
                'ocr_attempts' => ((int) $invoice->ocr_attempts) + 1,
                'ocr_started_at' => now(),
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();

            return $invoice;
        });

        if ($invoice === null) {
            return;
        }

        try {
            $result = $provider->extract($invoice);
        } catch (OcrProviderException $exception) {
            if (! $exception->retryable) {
                $this->markFailed($invoice->id, $exception->errorCode, $exception->rawResponse);
                Log::warning('Invoice OCR stopped after a non-retryable provider error.', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'error_code' => $exception->errorCode,
                    'attempt' => $invoice->ocr_attempts,
                ]);

                return;
            }

            Log::warning('Invoice OCR provider attempt failed and will be retried.', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'error_code' => $exception->errorCode,
                'attempt' => $invoice->ocr_attempts,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($result, $totalsChecker): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status === 'ocr_completed') {
                return;
            }

            $invoiceData = $result->invoiceData;
            $lines = $invoiceData['lines'];
            unset($invoiceData['lines']);

            $invoice->forceFill([
                ...$invoiceData,
                'ocr_response' => $result->response,
                'ocr_data' => $result->invoiceData,
                'ocr_usage' => $result->usage,
                'ocr_warnings' => $totalsChecker->warnings($result->invoiceData),
                'ocr_model' => $result->model,
                'ocr_completed_at' => now(),
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
                'status' => 'ocr_completed',
            ])->save();

            $invoice->lines()->delete();

            foreach ($lines as $index => $line) {
                $invoice->lines()->create([
                    ...$line,
                    'line_number' => $index + 1,
                ]);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = Invoice::query()->where('company_id', $this->companyId)->find($this->invoiceId);

        if ($invoice === null || $invoice->status === 'ocr_completed') {
            return;
        }

        $errorCode = $exception instanceof OcrProviderException
            ? $exception->errorCode
            : 'processing_attempts_exhausted';

        $this->markFailed($invoice->id, $errorCode);

        Log::error('Invoice OCR job exhausted its attempts.', [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'error_code' => $errorCode,
            'attempts' => $invoice->ocr_attempts,
        ]);
    }

    private function markFailed(int $invoiceId, string $errorCode, ?array $rawResponse = null): void
    {
        DB::transaction(function () use ($invoiceId, $errorCode, $rawResponse): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status === 'ocr_completed') {
                return;
            }

            $attributes = [
                'status' => 'ocr_failed',
                'ocr_failed_at' => now(),
                'ocr_error_code' => $errorCode,
                'ocr_error_message' => $this->userFacingError($errorCode),
            ];

            if ($rawResponse !== null) {
                $attributes['ocr_response'] = $rawResponse;
            }

            $invoice->forceFill($attributes)->save();
        });
    }

    private function userFacingError(string $errorCode): string
    {
        return match ($errorCode) {
            'configuration_missing' => 'La clé Mistral OCR n’est pas configurée sur le serveur.',
            'provider_authentication_failed' => 'Mistral OCR a refusé l’authentification. Vérifiez la configuration du serveur.',
            'provider_rejected_request', 'invalid_provider_response', 'invalid_structured_annotation' => 'La réponse OCR n’a pas pu être validée. Le document peut être vérifié ou relancé.',
            'source_file_missing', 'source_file_unreadable', 'source_file_invalid_path' => 'Le document privé n’a pas pu être lu pour le traitement OCR.',
            'unsupported_document_type', 'unsupported_storage_disk' => 'Le format ou le stockage du document ne peut pas être traité par OCR.',
            'provider_rate_limited', 'provider_unavailable', 'provider_unreachable', 'processing_attempts_exhausted' => 'Le service OCR n’a pas abouti après plusieurs tentatives. Vous pouvez relancer le traitement.',
            default => 'Le traitement OCR a échoué. Vous pouvez relancer le traitement.',
        };
    }
}
