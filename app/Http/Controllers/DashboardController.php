<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', Company::class);

        $companies = $user->isCabinetAdmin()
            ? $user->cabinet->companies()->withCount('users')->orderBy('name')->get()
            : $user->companies()
                ->where('companies.cabinet_id', $user->cabinet_id)
                ->withCount('users')
                ->orderBy('name')
                ->get();

        $selectedCompany = $companies->firstWhere('id', $request->integer('company_id')) ?? $companies->first();
        $dashboard = null;
        $activity = collect();

        if ($selectedCompany !== null) {
            $this->authorize('view', $selectedCompany);
            $invoices = $selectedCompany->invoices()->with('accountingProposal')->latest()->get();
            $imported = $invoices->count();
            $analyzedStatuses = ['ocr_completed', 'proposal_ready', 'proposal_rejected', 'accounting_validated', 'accounting_exported'];
            $analyzed = $invoices->whereIn('status', $analyzedStatuses)->count();
            $validated = $invoices->whereIn('status', ['accounting_validated', 'accounting_exported'])->count();
            $pending = $invoices->whereIn('status', ['uploaded', 'ocr_queued', 'ocr_processing', 'data_extraction', 'accounting_analysis', 'ocr_completed', 'proposal_ready'])->count();
            $intervention = $invoices->filter(fn (Invoice $invoice): bool => in_array($invoice->status, ['invoice_incomplete', 'ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed', 'proposal_rejected'], true)
                || ! empty($invoice->ocr_warnings))->count();
            $recognitionComplete = $invoices->filter(fn (Invoice $invoice): bool => trim((string) $invoice->supplier_name) !== ''
                && trim((string) $invoice->invoice_date) !== '' && trim((string) $invoice->total_amount) !== '')->count();
            $errors = $invoices->whereIn('status', ['ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed'])->count();
            $proposed = $invoices->filter(fn (Invoice $invoice): bool => $invoice->accountingProposal !== null)->count();
            $entries = $selectedCompany->journalEntries()->count();
            $manualMinutes = max(0, (int) config('services.invoices.estimated_manual_minutes', 15));
            $dashboard = [
                'imported' => $imported,
                'analyzed' => $analyzed,
                'validated' => $validated,
                'pending' => $pending,
                'intervention' => $intervention,
                'recognition_rate' => $imported === 0 ? 0 : (int) round(($recognitionComplete / $imported) * 100),
                'validation_rate' => $analyzed === 0 ? 0 : (int) round(($validated / $analyzed) * 100),
                'errors' => $errors,
                'estimated_minutes_saved' => $proposed * $manualMinutes,
                'journal_entries' => $entries,
                'manual_minutes_per_invoice' => $manualMinutes,
            ];

            $invoiceActivity = $invoices->take(8)->map(fn (Invoice $invoice): array => [
                'id' => 'invoice-'.$invoice->id,
                'label' => match ($invoice->status) {
                    'accounting_validated', 'accounting_exported' => 'Facture validée',
                    'proposal_ready' => 'Proposition prête à valider',
                    'proposal_rejected' => 'Proposition rejetée',
                    'ocr_failed', 'data_extraction_failed', 'accounting_analysis_failed' => 'Facture à traiter',
                    default => 'Facture importée',
                },
                'detail' => $invoice->original_filename,
                'date' => $invoice->updated_at?->toIso8601String(),
                'url' => route('companies.invoices.show', [$selectedCompany, $invoice]),
            ]);
            $proposalActivity = $selectedCompany->accountingProposals()->with('invoice:id,original_filename')->latest()->limit(8)->get()->map(fn ($proposal): array => [
                'id' => 'proposal-'.$proposal->id,
                'label' => match ($proposal->status) { 'approved' => 'Écriture validée', 'rejected' => 'Proposition rejetée', default => 'Proposition générée' },
                'detail' => $proposal->invoice?->original_filename ?? 'Facture',
                'date' => $proposal->updated_at?->toIso8601String(),
                'url' => $proposal->invoice ? route('companies.invoices.show', [$selectedCompany, $proposal->invoice]) : route('companies.invoices.index', $selectedCompany),
            ]);
            $activity = $invoiceActivity->concat($proposalActivity)->sortByDesc('date')->take(10)->values();
        }

        return Inertia::render('Dashboard', [
            'cabinet' => [
                'name' => $user->cabinet->name,
            ],
            'companies' => $companies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'tax_identifier' => $company->tax_identifier,
                'activity' => $company->activity,
                'sector' => $company->sector,
                'currency' => $company->currency,
                'users_count' => $company->users_count,
                'access_role' => $user->companyRole($company),
            ])->values(),
            'canManageCabinet' => $user->isCabinetAdmin(),
            'selectedCompanyId' => $selectedCompany?->id,
            'dashboard' => $dashboard,
            'activity' => $activity,
        ]);
    }
}
