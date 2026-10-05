<?php

namespace App\Jobs;

use App\Exceptions\AiProviderException;
use App\Models\AccountingProposal;
use App\Models\Invoice;
use App\Services\Invoices\AccountingProposalService;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Invoices\InvoiceProcessingErrorMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyzeAccountingProposal implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 210;

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
        return "invoice-accounting-analysis:{$this->companyId}:{$this->invoiceId}";
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        AccountingProposalService $proposalService,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceProcessingErrorMessage $errorMessages,
    ): void {
        $invoice = $this->claimInvoice($completenessChecker);

        if ($invoice === null) {
            return;
        }

        try {
            $proposalData = $proposalService->analyze($invoice->load('company'));
        } catch (AiProviderException $exception) {
            if (! $exception->retryable) {
                $this->markFailed($exception->errorCode, $errorMessages);
                Log::warning('Accounting proposal analysis stopped after a non-retryable OpenRouter error.', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $invoice->company_id,
                    'error_code' => $exception->errorCode,
                    'transport_error' => $exception->diagnostic,
                ]);

                return;
            }

            Log::warning('Accounting proposal analysis attempt failed and will be retried.', [
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'error_code' => $exception->errorCode,
                'transport_error' => $exception->diagnostic,
            ]);

            throw $exception;
        }

        DB::transaction(function () use ($proposalData): void {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $invoice->status !== 'accounting_analysis') {
                return;
            }

            AccountingProposal::query()
                ->where('company_id', $invoice->company_id)
                ->where('invoice_id', $invoice->id)
                ->where('status', 'ready')
                ->lockForUpdate()
                ->update(['status' => 'superseded']);

            $proposal = AccountingProposal::query()->create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'journal_id' => $proposalData['journal_id'],
                'invoice_type' => $proposalData['invoice_type'],
                'status' => 'ready',
                'model' => $proposalData['model'],
                'entry_description' => $proposalData['entry_description'],
                'explanation' => $proposalData['explanation'],
                'raw_response' => $proposalData['raw_response'],
                'usage' => $proposalData['usage'],
                'warnings' => $proposalData['warnings'],
            ]);

            foreach ($proposalData['lines'] as $index => $line) {
                $proposal->lines()->create([
                    ...$line,
                    'line_number' => $index + 1,
                ]);
            }

            $invoice->forceFill([
                'status' => 'proposal_ready',
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();
        });
    }

    public function failed(?Throwable $exception): void
    {
        $invoice = Invoice::query()->where('company_id', $this->companyId)->find($this->invoiceId);

        if ($invoice === null || $invoice->status !== 'accounting_analysis') {
            return;
        }

        $errorCode = $exception instanceof AiProviderException
            ? $exception->errorCode
            : 'processing_attempts_exhausted';

        $this->markFailed($errorCode, app(InvoiceProcessingErrorMessage::class));

        Log::error('Accounting proposal job exhausted its attempts.', [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'error_code' => $errorCode,
        ]);
    }

    private function claimInvoice(InvoiceDataCompletenessChecker $completenessChecker): ?Invoice
    {
        return DB::transaction(function () use ($completenessChecker): ?Invoice {
            $invoice = Invoice::query()
                ->where('company_id', $this->companyId)
                ->whereKey($this->invoiceId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || ! in_array($invoice->status, ['accounting_analysis', 'accounting_analysis_failed'], true)) {
                return null;
            }

            $missingFields = $completenessChecker->missingFields($invoice->ocr_data ?? []);

            if ($missingFields !== []) {
                $invoice->forceFill([
                    'status' => 'invoice_incomplete',
                    'ocr_error_code' => 'invoice_data_incomplete',
                    'ocr_error_message' => 'Les données obligatoires de la facture sont incomplètes. Corrigez les champs signalés avant l’analyse comptable.',
                    'ocr_warnings' => array_values(array_unique([...(array) ($invoice->ocr_warnings ?? []), ...$missingFields])),
                ])->save();

                return null;
            }

            $invoice->forceFill([
                'status' => 'accounting_analysis',
                'ocr_error_code' => null,
                'ocr_error_message' => null,
                'ocr_failed_at' => null,
            ])->save();

            return $invoice->load('company');
        });
    }

    private function markFailed(string $errorCode, InvoiceProcessingErrorMessage $errorMessages): void
    {
        DB::transaction(function () use ($errorCode, $errorMessages): void {
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
                'ocr_error_code' => $errorCode,
                'ocr_error_message' => $errorMessages->for($errorCode),
            ])->save();
        });
    }
}
