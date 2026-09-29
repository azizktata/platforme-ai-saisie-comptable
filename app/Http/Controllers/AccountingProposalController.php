<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveAccountingProposalRequest;
use App\Models\AccountingProposal;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Invoices\AccountingProposalBalanceChecker;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

            $existingConfidence = $proposal->lines()
                ->get(['id', 'confidence'])
                ->mapWithKeys(fn ($line): array => [$line->id => $line->confidence])
                ->all();

            foreach ($data['lines'] as $line) {
                if (isset($line['id']) && ! array_key_exists((int) $line['id'], $existingConfidence)) {
                    throw ValidationException::withMessages([
                        'lines' => 'Une ligne sélectionnée n’appartient pas à cette proposition.',
                    ]);
                }
            }

            $proposal->forceFill([
                'journal_id' => (int) $data['journal_id'],
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
                    'confidence' => isset($line['id']) ? $existingConfidence[(int) $line['id']] : null,
                ]);
            }

            $proposal->forceFill([
                'warnings' => $balanceChecker->warnings($lockedInvoice, $proposal->lines()->get()),
            ])->save();
        });

        return back()->with('success', 'La proposition comptable a été mise à jour. Vérifiez les avertissements avant validation.');
    }

    public function approve(
        Request $request,
        Company $company,
        Invoice $invoice,
        AccountingProposalBalanceChecker $balanceChecker,
        InvoiceDataCompletenessChecker $completenessChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        /** @var User $reviewer */
        $reviewer = $request->user();

        DB::transaction(function () use ($company, $invoice, $reviewer, $balanceChecker, $completenessChecker): void {
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

            if ($completenessChecker->missingFields($lockedInvoice->ocr_data ?? []) !== []) {
                throw ValidationException::withMessages([
                    'proposal' => 'Complétez les informations obligatoires de la facture avant de valider la proposition.',
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
