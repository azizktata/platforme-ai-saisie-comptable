<?php

namespace App\Jobs;

use App\Exceptions\AiProviderException;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Invoices\InvoiceDataExtractionService;
use App\Services\Invoices\InvoiceDataPersistence;
use App\Services\Invoices\InvoiceProcessingErrorMessage;
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

class ExtractInvoiceData implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $invoiceId,
        public int $companyId,
    ) {
        $this->onConnection('database')->onQueue('ocr');
    }

    public function uniqueId(): string
    {
        return "invoice-extraction:{$this->companyId}:{$this->invoiceId}";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        InvoiceDataExtractionService $extractor,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceDataPersistence $persistence,
        InvoiceTotalsConsistencyChecker $totalsChecker,
        InvoiceProcessingErrorMessage $errorMessages,
    ): void {
        $invoice = $this->claimInvoice();

        if ($invoice === null) {
            return;
        }

        try {
            $result = $extractor->extract($invoice);
        } catch (AiProviderException $exception) {
            if (! $exception->retryable) {
                $this->markFailed($exception->errorCode, $exception->rawResponse, $errorMessages);
                Log::warning('Invoice data extraction stopped after a non-retryable OpenRouter error.', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'error_code' => $exception->errorCode,
                    'transport_error' => $exception->diagnostic,
                ]);

                return;
            }

            Log::warning('Invoice data extraction attempt failed and will be retried.', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'error_code' => $exception->errorCode,
                'transport_error' => $exception->diagnostic,
            ]);

            throw $exception;
        }

        $shouldAnalyze = DB::transaction(function () use ($result, $completenessChecker, $persistence, $totalsChecker): bool {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== 'data_extraction') {
                return false;
            }

            $missingFields = $completenessChecker->missingFields($result->invoiceData);
            $warnings = [
                ...$totalsChecker->warnings($result->invoiceData),
                ...$missingFields,
            ];

            $persistence->store($invoice, $result->invoiceData);
            $invoice->forceFill([
                'extraction_response' => $result->response,
                'extraction_model' => $result->model,
                'extraction_usage' => $result->usage,
                'ocr_warnings' => array_values(array_unique($warnings)),
                'ocr_error_code' => $missingFields === [] ? null : 'invoice_data_incomplete',
                'ocr_error_message' => $missingFields === [] ? null : 'Les données obligatoires de la facture sont incomplètes. Corrigez les champs signalés avant l’analyse comptable.',
                'status' => $missingFields === [] ? 'accounting_analysis' : 'invoice_incomplete',
            ])->save();

            return $missingFields === [];
        });

        if ($shouldAnalyze) {
            try {
                AnalyzeAccountingProposal::dispatch($this->invoiceId, $this->companyId);
            } catch (Throwable) {
                $this->markAnalysisDispatchFailure();
                Log::warning('Accounting proposal analysis could not be queued after invoice extraction.', [
                    'invoice_id' => $this->invoiceId,
                    'company_id' => $this->companyId,
                ]);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = Invoice::query()->where('company_id', $this->companyId)->find($this->invoiceId);

        if ($invoice === null || $invoice->status !== 'data_extraction') {
            return;
        }

        $errorCode = $exception instanceof AiProviderException
            ? $exception->errorCode
            : 'processing_attempts_exhausted';

        $this->markFailed($errorCode, $exception instanceof AiProviderException ? $exception->rawResponse : null, app(InvoiceProcessingErrorMessage::class));

        Log::error('Invoice data-extraction job exhausted its attempts.', [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'error_code' => $errorCode,
        ]);
    }

    private function claimInvoice(): ?Invoice
    {
        return DB::transaction(function (): ?Invoice {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || ! in_array($invoice->status, ['data_extraction', 'data_extraction_failed'], true)) {
                return null;
            }

            $invoice->forceFill([
                'status' => 'data_extraction',
                'ocr_error_code' => null,
                'ocr_error_message' => null,
                'ocr_failed_at' => null,
            ])->save();

            return $invoice;
        });
    }

    private function markAnalysisDispatchFailure(): void
    {
        DB::transaction(function (): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== 'accounting_analysis') {
                return;
            }

            $invoice->forceFill([
                'status' => 'accounting_analysis_failed',
                'ocr_failed_at' => now(),
                'ocr_error_code' => 'queue_unavailable',
                'ocr_error_message' => 'L’analyse comptable n’a pas pu être planifiée. Vous pouvez la relancer.',
            ])->save();
        });
    }

    private function markFailed(string $errorCode, ?array $rawResponse, InvoiceProcessingErrorMessage $errorMessages): void
    {
        DB::transaction(function () use ($errorCode, $rawResponse, $errorMessages): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== 'data_extraction') {
                return;
            }

            $invoice->forceFill([
                'status' => 'data_extraction_failed',
                'ocr_failed_at' => now(),
                'ocr_error_code' => $errorCode,
                'ocr_error_message' => $errorMessages->for($errorCode),
                ...($rawResponse === null ? [] : ['extraction_response' => $rawResponse]),
            ])->save();
        });
    }
}
