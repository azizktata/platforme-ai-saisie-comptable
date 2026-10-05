<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Http\Requests\SaveAccountingProposalRequest;
use App\Jobs\AnalyzeAccountingProposal;
use App\Models\AccountingProposal;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Invoices\AccountingProposalBalanceChecker;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Ocr\InvoiceTotalsConsistencyChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingProposalController extends Controller
{
    public function update(
        SaveAccountingProposalRequest $request,
        Company $company,
        Invoice $invoice,
        AccountingProposalBalanceChecker $balanceChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        $data = $request->validated();
        $this->validateCompanyReferences($company, $data);
        $modifier = $request->user();

        DB::transaction(function () use ($company, $invoice, $data, $balanceChecker, $modifier): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $proposal = AccountingProposal::query()
                ->where('company_id', $company->id)
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($proposal === null || $proposal->status !== 'ready'
                || $lockedInvoice->status !== 'proposal_ready'
                || $proposal->journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'proposal' => 'Seule une proposition disponible et non validée peut être corrigée.',
                ]);
            }

            $existingLineIds = $proposal->lines()->pluck('id')->mapWithKeys(fn ($id): array => [(int) $id => true])->all();

            foreach ($data['lines'] as $line) {
                if (isset($line['id']) && ! array_key_exists((int) $line['id'], $existingLineIds)) {
                    throw ValidationException::withMessages([
                        'lines' => 'Une ligne sélectionnée n’appartient pas à cette proposition.',
                    ]);
                }
            }

            $proposal->forceFill([
                'journal_id' => (int) $data['journal_id'],
                'invoice_type' => $data['invoice_type'] ?? $proposal->invoice_type,
                'entry_description' => trim($data['entry_description']),
                'modified_by' => $modifier->id,
                'modified_at' => now(),
            ])->save();
            $proposal->lines()->delete();

            foreach ($data['lines'] as $index => $line) {
                $proposal->lines()->create([
                    'line_number' => $index + 1,
                    'chart_account_id' => (int) $line['chart_account_id'],
                    'third_party_id' => isset($line['third_party_id']) ? (int) $line['third_party_id'] : null,
                    'analytical_account_id' => isset($line['analytical_account_id']) ? (int) $line['analytical_account_id'] : null,
                    'description' => $line['description'] === null ? null : trim($line['description']),
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            $proposal->forceFill([
                'warnings' => $balanceChecker->warnings($lockedInvoice, $proposal->lines()->get()),
            ])->save();
        });

        return back()->with('success', 'La proposition comptable a été mise à jour. Vérifiez les avertissements avant validation.');
    }

    public function regenerate(
        Company $company,
        Invoice $invoice,
        InvoiceDataCompletenessChecker $completenessChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        DB::transaction(function () use ($company, $invoice, $completenessChecker): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedInvoice->status, ['ocr_completed', 'proposal_ready', 'proposal_rejected', 'accounting_analysis_failed'], true)
                || $lockedInvoice->journalEntry()->exists()) {
                throw ValidationException::withMessages([
                    'proposal' => 'La proposition ne peut pas être régénérée à cette étape ou une écriture existe déjà.',
                ]);
            }

            if ($completenessChecker->missingFields($lockedInvoice->ocr_data ?? []) !== []) {
                throw ValidationException::withMessages([
                    'proposal' => 'Complétez les informations obligatoires de la facture avant de régénérer la proposition.',
                ]);
            }

            $proposal = AccountingProposal::query()
                ->where('company_id', $company->id)
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($lockedInvoice->status === 'proposal_ready' && ($proposal === null || $proposal->status !== 'ready' || $proposal->journal_entry_id !== null)) {
                throw ValidationException::withMessages([
                    'proposal' => 'La proposition n’est plus disponible pour régénération.',
                ]);
            }

            if ($lockedInvoice->status === 'proposal_rejected' && ($proposal === null || $proposal->status !== 'rejected')) {
                throw ValidationException::withMessages([
                    'proposal' => 'La proposition rejetée n’est plus disponible pour régénération.',
                ]);
            }

            if ($proposal?->status === 'ready') {
                $proposal->forceFill(['status' => 'superseded'])->save();
            }

            $lockedInvoice->forceFill([
                'status' => 'accounting_analysis',
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();
        });

        try {
            AnalyzeAccountingProposal::dispatch($invoice->id, $company->id);
        } catch (\Throwable) {
            DB::transaction(function () use ($company, $invoice): void {
                $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->first();

                if ($lockedInvoice?->status === 'accounting_analysis') {
                    $lockedInvoice->forceFill([
                        'status' => 'accounting_analysis_failed',
                        'ocr_failed_at' => now(),
                        'ocr_error_code' => 'queue_unavailable',
                        'ocr_error_message' => 'La proposition IA n’a pas pu être planifiée. Vous pouvez réessayer.',
                    ])->save();
                }
            });

            return back()->withErrors(['proposal' => 'La proposition IA n’a pas pu être planifiée. Vous pouvez réessayer.']);
        }

        return back()->with('success', 'La régénération de la proposition IA a démarré. La version précédente reste conservée pour audit.');
    }

    public function approve(
        Request $request,
        Company $company,
        Invoice $invoice,
        AccountingProposalBalanceChecker $balanceChecker,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceTotalsConsistencyChecker $totalsChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        /** @var User $reviewer */
        $reviewer = $request->user();

        DB::transaction(function () use ($company, $invoice, $reviewer, $balanceChecker, $completenessChecker, $totalsChecker): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $proposal = AccountingProposal::query()
                ->where('company_id', $company->id)
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('id')
                ->with('lines')
                ->lockForUpdate()
                ->first();

            if ($proposal === null || $proposal->status !== 'ready'
                || $lockedInvoice->status !== 'proposal_ready'
                || $proposal->journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'proposal' => 'Cette proposition n’est plus disponible pour validation.',
                ]);
            }

            if (! is_string($proposal->invoice_type) || InvoiceType::tryFrom($proposal->invoice_type) === null) {
                throw ValidationException::withMessages([
                    'proposal' => 'Choisissez le type de facture avant de valider l’écriture.',
                ]);
            }

            if ($completenessChecker->missingFields($lockedInvoice->ocr_data ?? []) !== []) {
                throw ValidationException::withMessages([
                    'proposal' => 'Complétez les informations obligatoires de la facture avant de valider la proposition.',
                ]);
            }

            if ($company->fiscal_year_start !== null && $company->fiscal_year_end !== null) {
                $invoiceDate = $lockedInvoice->invoice_date?->toDateString();
                if ($invoiceDate === null || $invoiceDate < $company->fiscal_year_start->toDateString() || $invoiceDate > $company->fiscal_year_end->toDateString()) {
                    throw ValidationException::withMessages(['proposal' => 'La date de facture est absente ou hors de l’exercice fiscal configuré pour cette société.']);
                }
            }

            $invoiceTotalWarnings = $totalsChecker->warnings($lockedInvoice->ocr_data ?? []);

            if ($totalsChecker->hasBlockingWarnings($invoiceTotalWarnings)) {
                throw ValidationException::withMessages([
                    'proposal' => 'Les totaux extraits de la facture ne sont pas cohérents. Corrigez les montants avant validation.',
                ]);
            }

            $invoiceCurrency = strtoupper((string) $lockedInvoice->currency);
            $companyCurrency = strtoupper((string) ($company->currency ?: 'TND'));

            if ($invoiceCurrency !== $companyCurrency) {
                throw ValidationException::withMessages([
                    'proposal' => "La facture est en {$invoiceCurrency} alors que la société comptabilise en {$companyCurrency}. Aucun taux de conversion n’est configuré ; la validation comptable est bloquée.",
                ]);
            }

            $this->assertActiveCompanyReferences($company, $proposal);

            $warnings = $balanceChecker->warnings($lockedInvoice, $proposal->lines);

            if ($balanceChecker->hasBlockingWarnings($warnings)) {
                $proposal->forceFill(['warnings' => $warnings])->save();

                throw ValidationException::withMessages([
                    'proposal' => 'La proposition doit être équilibrée et rapprochée du total de la facture avant validation.',
                ]);
            }

            $reference = 'AI-INV-'.$lockedInvoice->id;
            $existingReference = $company->journalEntries()
                ->where('journal_id', $proposal->journal_id)
                ->where('reference', $reference)
                ->exists();

            if ($existingReference || JournalEntry::query()->where('invoice_id', $lockedInvoice->id)->exists()) {
                throw ValidationException::withMessages([
                    'proposal' => 'Une écriture comptable existe déjà pour cette facture. Vérifiez le journal avant de continuer.',
                ]);
            }

            $entry = $company->journalEntries()->create([
                'journal_id' => $proposal->journal_id,
                'invoice_id' => $lockedInvoice->id,
                'reference' => $reference,
                'entry_date' => $lockedInvoice->invoice_date->toDateString(),
                'description' => $proposal->entry_description,
                'source' => 'ai_proposal',
            ]);

            foreach ($proposal->lines as $index => $line) {
                $entry->lines()->create([
                    'line_number' => $index + 1,
                    'chart_account_id' => $line->chart_account_id,
                    'third_party_id' => $line->third_party_id,
                    'analytical_account_id' => $line->analytical_account_id,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ]);
            }

            $proposal->forceFill([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'journal_entry_id' => $entry->id,
                'warnings' => $warnings,
            ])->save();

            $lockedInvoice->forceFill([
                'status' => 'accounting_validated',
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();
        });

        return back()->with('success', 'La proposition a été validée et l’écriture comptable a été créée.');
    }

    public function restore(
        Request $request,
        Company $company,
        Invoice $invoice,
        AccountingProposalBalanceChecker $balanceChecker,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceTotalsConsistencyChecker $totalsChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        DB::transaction(function () use ($company, $invoice, $balanceChecker, $completenessChecker, $totalsChecker): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $proposal = $company->accountingProposals()
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('id')
                ->with('lines')
                ->lockForUpdate()
                ->first();

            if ($proposal === null || $proposal->status !== 'superseded'
                || $proposal->journal_entry_id !== null || $lockedInvoice->journalEntry()->exists()) {
                throw ValidationException::withMessages(['proposal' => 'Aucune ancienne proposition ne peut être réactivée pour cette facture.']);
            }

            if ($completenessChecker->missingFields($lockedInvoice->ocr_data ?? []) !== []) {
                throw ValidationException::withMessages(['proposal' => 'Complétez les informations obligatoires de la facture avant de réactiver la proposition.']);
            }

            if ($totalsChecker->hasBlockingWarnings($totalsChecker->warnings($lockedInvoice->ocr_data ?? []))
                || $balanceChecker->hasBlockingWarnings($balanceChecker->warnings($lockedInvoice, $proposal->lines))) {
                throw ValidationException::withMessages(['proposal' => 'Les montants de la facture ou de la proposition ont changé. Générez une nouvelle proposition après les avoir corrigés.']);
            }

            $proposal->forceFill(['status' => 'ready'])->save();
            $lockedInvoice->forceFill(['status' => 'proposal_ready'])->save();
        });

        return back()->with('success', 'La proposition précédente est de nouveau disponible pour vérification et validation.');
    }

    public function exportCsv(Request $request, Company $company, Invoice $invoice): StreamedResponse|JsonResponse
    {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        /** @var User $exporter */
        $exporter = $request->user();

        $export = DB::transaction(function () use ($company, $invoice, $exporter): ?array {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $headers = [
                'Journal', 'Date', 'Référence', 'Libellé écriture', 'Compte', 'Intitulé compte',
                'Tiers', 'Code analytique', 'Libellé ligne', 'Débit', 'Crédit', 'Devise',
            ];
            $rows = [];

            if (in_array($lockedInvoice->status, ['accounting_validated', 'accounting_exported'], true)) {
                $entry = JournalEntry::query()
                    ->where('company_id', $company->id)
                    ->where('invoice_id', $lockedInvoice->id)
                    ->with(['journal', 'lines.chartAccount', 'lines.thirdParty', 'lines.analyticalAccount'])
                    ->lockForUpdate()
                    ->first();

                if ($entry === null) {
                    return null;
                }

                foreach ($entry->lines as $line) {
                    $rows[] = [
                        $this->safeCsvText($entry->journal?->code),
                        $entry->entry_date?->toDateString() ?? '',
                        $this->safeCsvText($entry->reference),
                        $this->safeCsvText($entry->description),
                        $this->safeCsvText($line->chartAccount?->code),
                        $this->safeCsvText($line->chartAccount?->label),
                        $this->safeCsvText($line->thirdParty?->code),
                        $this->safeCsvText($line->analyticalAccount?->code),
                        $this->safeCsvText($line->description),
                        (string) $line->debit,
                        (string) $line->credit,
                        $this->safeCsvText($lockedInvoice->currency),
                    ];
                }

                $lockedInvoice->forceFill([
                    'status' => 'accounting_exported',
                    'accounting_exported_at' => now(),
                    'accounting_exported_by' => $exporter->id,
                ])->save();
            } elseif ($lockedInvoice->status === 'proposal_ready') {
                $proposal = AccountingProposal::query()
                    ->where('company_id', $company->id)
                    ->where('invoice_id', $lockedInvoice->id)
                    ->where('status', 'ready')
                    ->with(['journal', 'lines.chartAccount', 'lines.thirdParty', 'lines.analyticalAccount'])
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($proposal === null || $proposal->journal_entry_id !== null) {
                    return null;
                }

                $reference = $lockedInvoice->invoice_number ?: 'FACTURE-'.$lockedInvoice->id;
                $entryDate = $lockedInvoice->invoice_date?->toDateString() ?? '';

                foreach ($proposal->lines as $line) {
                    $rows[] = [
                        $this->safeCsvText($proposal->journal?->code),
                        $entryDate,
                        $this->safeCsvText($reference),
                        $this->safeCsvText($proposal->entry_description),
                        $this->safeCsvText($line->chartAccount?->code),
                        $this->safeCsvText($line->chartAccount?->label),
                        $this->safeCsvText($line->thirdParty?->code),
                        $this->safeCsvText($line->analyticalAccount?->code),
                        $this->safeCsvText($line->description),
                        (string) $line->debit,
                        (string) $line->credit,
                        $this->safeCsvText($lockedInvoice->currency),
                    ];
                }
            } else {
                return null;
            }

            if ($rows === []) {
                return null;
            }

            return [
                'csv' => $this->buildCsv($headers, $rows),
                'filename' => 'ecriture-facture-'.$lockedInvoice->id.'.csv',
            ];
        });

        if ($export === null) {
            return response()->json([
                'message' => 'Aucune proposition prête ou écriture validée n’est disponible pour l’export CSV.',
            ], 422);
        }

        return response()->streamDownload(
            static function () use ($export): void {
                echo $export['csv'];
            },
            $export['filename'],
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function reject(Request $request, Company $company, Invoice $invoice): RedirectResponse
    {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        /** @var User $reviewer */
        $reviewer = $request->user();

        DB::transaction(function () use ($company, $invoice, $reviewer): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $proposal = AccountingProposal::query()
                ->where('company_id', $company->id)
                ->where('invoice_id', $lockedInvoice->id)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($proposal === null || $proposal->status !== 'ready'
                || $lockedInvoice->status !== 'proposal_ready'
                || $proposal->journal_entry_id !== null) {
                throw ValidationException::withMessages([
                    'proposal' => 'Seule une proposition disponible et non validée peut être rejetée.',
                ]);
            }

            $proposal->forceFill([
                'status' => 'rejected',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();

            $lockedInvoice->forceFill([
                'status' => 'proposal_rejected',
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();
        });

        return back()->with('success', 'La proposition comptable a été rejetée. Aucune écriture n’a été créée.');
    }

    /** @param list<string> $headers
     *  @param list<list<string>> $rows
     */
    private function buildCsv(array $headers, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create the CSV export stream.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ';', '"', '\\');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
    }

    private function safeCsvText(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    /** @param array<string, mixed> $data */
    private function validateCompanyReferences(Company $company, array $data): void
    {
        if (! $company->journals()->where('is_active', true)->whereKey($data['journal_id'])->exists()) {
            throw ValidationException::withMessages(['journal_id' => 'Le journal sélectionné n’est pas actif dans cette société.']);
        }

        $lines = $data['lines'];
        $accountIds = array_values(array_unique(array_map('intval', array_column($lines, 'chart_account_id'))));
        $thirdPartyIds = array_values(array_unique(array_filter(array_map(
            fn (array $line): ?int => isset($line['third_party_id']) ? (int) $line['third_party_id'] : null,
            $lines,
        ))));
        $analyticalIds = array_values(array_unique(array_filter(array_map(
            fn (array $line): ?int => isset($line['analytical_account_id']) ? (int) $line['analytical_account_id'] : null,
            $lines,
        ))));

        if ($company->chartAccounts()->where('is_active', true)->whereIn('id', $accountIds)->count() !== count($accountIds)) {
            throw ValidationException::withMessages(['lines' => 'Chaque compte comptable doit être actif et appartenir à cette société.']);
        }

        if ($thirdPartyIds !== [] && $company->thirdParties()
            ->where('is_active', true)
            ->whereIn('party_type', ['supplier', 'both'])
            ->whereIn('id', $thirdPartyIds)
            ->count() !== count($thirdPartyIds)) {
            throw ValidationException::withMessages(['lines' => 'Chaque tiers doit être un fournisseur actif de cette société.']);
        }

        if ($analyticalIds !== [] && $company->analyticalAccounts()
            ->where('is_active', true)
            ->whereIn('id', $analyticalIds)
            ->count() !== count($analyticalIds)) {
            throw ValidationException::withMessages(['lines' => 'Chaque axe analytique doit être actif et appartenir à cette société.']);
        }

        foreach ($lines as $index => $line) {
            $debit = $this->toMilli($line['debit']);
            $credit = $this->toMilli($line['credit']);

            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.debit" => 'Chaque ligne doit contenir un débit ou un crédit, mais pas les deux.',
                ]);
            }
        }
    }

    private function assertActiveCompanyReferences(Company $company, AccountingProposal $proposal): void
    {
        if (! $company->journals()->where('is_active', true)->whereKey($proposal->journal_id)->exists()) {
            throw ValidationException::withMessages(['proposal' => 'Le journal de la proposition n’est plus actif dans cette société.']);
        }

        foreach ($proposal->lines as $index => $line) {
            if (! $company->chartAccounts()->where('is_active', true)->whereKey($line->chart_account_id)->exists()) {
                throw ValidationException::withMessages(['proposal' => 'Un compte de la proposition n’est plus actif dans cette société.']);
            }

            if ($line->third_party_id !== null && ! $company->thirdParties()
                ->where('is_active', true)
                ->whereIn('party_type', ['supplier', 'both'])
                ->whereKey($line->third_party_id)
                ->exists()) {
                throw ValidationException::withMessages(['proposal' => 'Un tiers de la proposition n’est plus un fournisseur actif de cette société.']);
            }

            if ($line->analytical_account_id !== null && ! $company->analyticalAccounts()
                ->where('is_active', true)
                ->whereKey($line->analytical_account_id)
                ->exists()) {
                throw ValidationException::withMessages(['proposal' => 'Un axe analytique de la proposition n’est plus actif dans cette société.']);
            }

            $debit = $this->toMilli((string) $line->debit);
            $credit = $this->toMilli((string) $line->credit);

            if (($debit > 0) === ($credit > 0)) {
                throw ValidationException::withMessages([
                    "proposal.lines.{$index}" => 'Chaque ligne doit contenir un débit ou un crédit, mais pas les deux.',
                ]);
            }
        }
    }

    private function toMilli(string $amount): int
    {
        preg_match('/^(\d{1,15})(?:\.(\d{1,3}))?$/', $amount, $matches);

        return ((int) $matches[1] * 1000) + (int) str_pad($matches[2] ?? '', 3, '0');
    }
}
