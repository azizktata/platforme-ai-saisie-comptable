<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\AccountingData\SeedDemoAccountingData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingDataController extends Controller
{
    public function workspace(Request $request): Response|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', Company::class);
        $companies = $this->accessibleCompanies($user);

        if ($companies->isEmpty()) {
            return to_route('companies.index');
        }

        $requestedCompanyId = $request->query('company_id');

        if ($requestedCompanyId === null || $requestedCompanyId === '') {
            return to_route('accounting-data.index', ['company_id' => $companies->first()->id]);
        }

        abort_unless(is_string($requestedCompanyId) && ctype_digit($requestedCompanyId), 404);
        $company = $companies->firstWhere('id', (int) $requestedCompanyId);
        abort_unless($company instanceof Company, 404);

        return $this->renderCompanyData($request, $company, $companies);
    }

    public function show(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);
        /** @var User $user */
        $user = $request->user();
        $companies = $this->accessibleCompanies($user);

        return $this->renderCompanyData($request, $company, $companies);
    }

    private function renderCompanyData(Request $request, Company $company, Collection $companies): Response
    {
        $chartAccounts = $company->chartAccounts()
            ->orderBy('code')
            ->paginate(25, ['id', 'code', 'label', 'account_type', 'is_active'], 'accounts_page')
            ->withQueryString();

        $analyticalAccounts = $company->analyticalAccounts()
            ->orderBy('code')
            ->paginate(25, ['id', 'code', 'label', 'is_active'], 'analytical_page')
            ->withQueryString();

        $thirdParties = $company->thirdParties()
            ->orderBy('name')
            ->paginate(25, ['id', 'code', 'party_type', 'name', 'tax_identifier', 'is_active'], 'parties_page')
            ->withQueryString();

        $journals = $company->journals()
            ->orderBy('code')
            ->paginate(25, ['id', 'code', 'label', 'journal_type', 'is_active'], 'journals_page')
            ->withQueryString();

        $entries = $company->journalEntries()
            ->with([
                'journal:id,code,label',
                'lines:id,journal_entry_id,line_number,chart_account_id,third_party_id,analytical_account_id,description,debit,credit',
                'lines.chartAccount:id,code,label',
                'lines.thirdParty:id,code,name',
                'lines.analyticalAccount:id,code,label',
            ])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(10, ['id', 'company_id', 'journal_id', 'reference', 'entry_date', 'description', 'source'], 'entries_page')
            ->withQueryString()
            ->through(fn ($entry): array => [
                'id' => $entry->id,
                'reference' => $entry->reference,
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'source' => $entry->source,
                'journal' => [
                    'code' => $entry->journal->code,
                    'label' => $entry->journal->label,
                ],
                'lines' => $entry->lines->map(fn ($line): array => [
                    'line_number' => $line->line_number,
                    'account_code' => $line->chartAccount->code,
                    'account_label' => $line->chartAccount->label,
                    'third_party' => $line->thirdParty?->name,
                    'analytical_account' => $line->analyticalAccount?->code,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ])->values(),
            ]);

        /** @var User $user */
        $user = $request->user();
        $canManage = $user->can('manageAccountingData', $company);
        $hasAccountingData = $chartAccounts->total() > 0
            || $analyticalAccounts->total() > 0
            || $thirdParties->total() > 0
            || $journals->total() > 0
            || $entries->total() > 0;

        return Inertia::render('AccountingData/Show', [
            'companies' => $this->companyOptions($companies),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency ?? 'TND',
            ],
            'chartAccounts' => $chartAccounts,
            'analyticalAccounts' => $analyticalAccounts,
            'thirdParties' => $thirdParties,
            'journals' => $journals,
            'entries' => $entries,
            'canLoadDemoData' => $canManage
                && app()->environment(['local', 'testing'])
                && ! $hasAccountingData,
        ]);
    }

    private function accessibleCompanies(User $user): Collection
    {
        return $user->isCabinetAdmin()
            ? $user->cabinet->companies()->orderBy('name')->get()
            : $user->companies()->where('companies.cabinet_id', $user->cabinet_id)->orderBy('name')->get();
    }

    private function companyOptions(Collection $companies): array
    {
        return $companies->map(fn (Company $item): array => [
            'id' => $item->id,
            'name' => $item->name,
        ])->values()->all();
    }

    public function seedDemo(
        Company $company,
        SeedDemoAccountingData $seedDemoAccountingData,
    ): RedirectResponse {
        $this->authorize('manageAccountingData', $company);
        abort_unless(app()->environment(['local', 'testing']), 404);

        $seedDemoAccountingData->handle($company);

        return to_route('companies.accounting-data', $company);
    }
}
