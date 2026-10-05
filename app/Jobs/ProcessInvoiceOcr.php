<?php

namespace App\Jobs;

use App\Contracts\OcrProvider;
use App\Data\InvoiceOcrResult;
use App\Exceptions\OcrProviderException;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
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

class ProcessInvoiceOcr implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

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
        return "invoice-ocr:{$this->companyId}:{$this->invoiceId}";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(
        OcrProvider $provider,
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
            $result = $provider->extract($invoice);
        } catch (OcrProviderException $exception) {
            if (! $exception->retryable) {
                $this->markFailed($exception->errorCode, $exception->rawResponse, $errorMessages);
                Log::warning('Invoice OCR stopped after a non-retryable provider error.', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'error_code' => $exception->errorCode,
                    'transport_error' => $exception->diagnostic,
                    'attempt' => $invoice->ocr_attempts,
                ]);

                return;
            }

            Log::warning('Invoice OCR provider attempt failed and will be retried.', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'error_code' => $exception->errorCode,
                'transport_error' => $exception->diagnostic,
                'attempt' => $invoice->ocr_attempts,
            ]);

            throw $exception;
        }

        $nextStage = $this->storeOcrResult($result, $completenessChecker, $persistence, $totalsChecker);

        if ($nextStage === 'data_extraction') {
            try {
                ExtractInvoiceData::dispatch($this->invoiceId, $this->companyId);
            } catch (Throwable) {
                $this->markDownstreamDispatchFailure('data_extraction');
            }
        } elseif ($nextStage === 'accounting_analysis') {
            try {
                AnalyzeAccountingProposal::dispatch($this->invoiceId, $this->companyId);
            } catch (Throwable) {
                $this->markDownstreamDispatchFailure('accounting_analysis');
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = Invoice::query()->where('company_id', $this->companyId)->find($this->invoiceId);

        if ($invoice === null || $invoice->status !== 'ocr_processing') {
            return;
        }

        $errorCode = $exception instanceof OcrProviderException
            ? $exception->errorCode
            : 'processing_attempts_exhausted';

        $this->markFailed($errorCode, $exception instanceof OcrProviderException ? $exception->rawResponse : null, app(InvoiceProcessingErrorMessage::class));

        Log::error('Invoice OCR job exhausted its attempts.', [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'error_code' => $errorCode,
            'attempts' => $invoice->ocr_attempts,
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

            if ($invoice === null || ! in_array($invoice->status, ['ocr_queued', 'ocr_processing'], true)) {
                return null;
            }

            $invoice->accountingProposal()->delete();
            $invoice->lines()->delete();
            $invoice->forceFill([
                'status' => 'ocr_processing',
                'ocr_attempts' => ((int) $invoice->ocr_attempts) + 1,
                'ocr_started_at' => now(),
                'ocr_completed_at' => null,
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
                'ocr_response' => null,
                'ocr_text' => null,
                'ocr_data' => null,
                'ocr_usage' => null,
                'ocr_warnings' => [],
                'ocr_model' => null,
                'extraction_response' => null,
                'extraction_model' => null,
                'extraction_usage' => null,
                'supplier_name' => null,
                'supplier_tax_identifier' => null,
                'supplier_address' => null,
                'supplier_phone' => null,
                'supplier_mobile' => null,
                'supplier_email' => null,
                'customer_name' => null,
                'customer_tax_identifier' => null,
                'customer_reference' => null,
                'customer_address' => null,
                'customer_phone' => null,
                'invoice_number' => null,
                'purchase_order_reference' => null,
                'invoice_date' => null,
                'due_date' => null,
                'currency' => null,
                'vat_rate' => null,
                'fodec_rate' => null,
                'subtotal' => null,
                'total_discount_amount' => null,
                'vat_amount' => null,
                'fodec_amount' => null,
                'other_tax_amount' => null,
                'stamp_amount' => null,
                'withholding_rate' => null,
                'withholding_amount' => null,
                'total_amount' => null,
                'net_to_pay_amount' => null,
                'payment_method' => null,
                'payment_terms' => null,
                'bank_name' => null,
                'bank_account_reference' => null,
                'description' => null,
            ])->save();

            return $invoice;
        });
    }

    private function storeOcrResult(
        InvoiceOcrResult $result,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceDataPersistence $persistence,
        InvoiceTotalsConsistencyChecker $totalsChecker,
    ): ?string {
        return DB::transaction(function () use ($result, $completenessChecker, $persistence, $totalsChecker): ?string {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== 'ocr_processing') {
                return null;
            }

            $recognizedText = is_string($result->text) ? trim($result->text) : '';

            if (! $result->hasStructuredData && $recognizedText !== '') {
                $invoice->forceFill([
                    'ocr_response' => $result->response,
                    'ocr_text' => $recognizedText,
                    'ocr_usage' => $result->usage,
                    'ocr_warnings' => [],
                    'ocr_model' => $result->model,
                    'ocr_completed_at' => now(),
                    'status' => 'data_extraction',
                ])->save();

                return 'data_extraction';
            }

            $missingFields = $completenessChecker->missingFields($result->invoiceData);
            $warnings = [
                ...$totalsChecker->warnings($result->invoiceData),
                ...$completenessChecker->warnings($result->invoiceData),
            ];

            $persistence->store($invoice, $result->invoiceData);
            $invoice->forceFill([
                'ocr_response' => $result->response,
                'ocr_text' => $recognizedText === '' ? null : $recognizedText,
                'ocr_usage' => $result->usage,
                'ocr_warnings' => array_values(array_unique($warnings)),
                'ocr_model' => $result->model,
                'ocr_completed_at' => now(),
                'ocr_failed_at' => null,
                'ocr_error_code' => $missingFields === [] ? null : 'invoice_data_incomplete',
                'ocr_error_message' => $missingFields === [] ? null : 'Les données obligatoires de la facture sont incomplètes. Corrigez les champs signalés avant l’analyse comptable.',
                'status' => $missingFields === [] ? 'accounting_analysis' : 'invoice_incomplete',
            ])->save();

            return $missingFields === [] ? 'accounting_analysis' : null;
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

            if ($invoice === null || $invoice->status !== 'ocr_processing') {
                return;
            }

            $invoice->forceFill([
                'status' => 'ocr_failed',
                'ocr_failed_at' => now(),
                'ocr_error_code' => $errorCode,
                'ocr_error_message' => $errorMessages->for($errorCode),
                ...($rawResponse === null ? [] : ['ocr_response' => $rawResponse]),
            ])->save();
        });
    }

    private function markDownstreamDispatchFailure(string $stage): void
    {
        $status = $stage === 'data_extraction' ? 'data_extraction_failed' : 'accounting_analysis_failed';
        $message = $stage === 'data_extraction'
            ? 'L’étape d’extraction des données n’a pas pu être planifiée. Vous pouvez la relancer.'
            : 'L’analyse comptable n’a pas pu être planifiée. Vous pouvez la relancer.';

        DB::transaction(function () use ($stage, $status, $message): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== $stage) {
                return;
            }

            $invoice->forceFill([
                'status' => $status,
                'ocr_failed_at' => now(),
                'ocr_error_code' => 'queue_unavailable',
                'ocr_error_message' => $message,
            ])->save();
        });
    }
}
