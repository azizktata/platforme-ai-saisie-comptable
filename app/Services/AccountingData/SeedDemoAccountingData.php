<?php

namespace App\Services\AccountingData;

use App\Models\Company;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Models\ThirdParty;
use Illuminate\Support\Facades\DB;

class SeedDemoAccountingData
{
    /**
     * Add a small, repeatable Sage-like dataset for local demos and tests.
     * Account codes are deliberately stored and handled as strings.
     */
    public function handle(Company $company): void
    {
        DB::transaction(function () use ($company): void {
            $accounts = [];

            foreach ($this->chartAccounts() as $account) {
                $accounts[$account['code']] = $company->chartAccounts()->updateOrCreate(
                    ['code' => $account['code']],
                    $account,
                );
            }

            $analyticalAccounts = [];

            foreach ($this->analyticalAccounts() as $account) {
                $analyticalAccounts[$account['code']] = $company->analyticalAccounts()->updateOrCreate(
                    ['code' => $account['code']],
                    $account,
                );
            }

            $parties = [];

            foreach ($this->thirdParties($accounts) as $party) {
                $parties[$party['code']] = $company->thirdParties()->updateOrCreate(
                    ['code' => $party['code']],
                    $party,
                );
            }

            $journals = [];

            foreach ($this->journals() as $journal) {
                $journals[$journal['code']] = $company->journals()->updateOrCreate(
                    ['code' => $journal['code']],
                    $journal,
                );
            }

            foreach ($this->entries($journals, $accounts, $parties, $analyticalAccounts) as $entryData) {
                $entry = $company->journalEntries()->updateOrCreate(
                    [
                        'journal_id' => $entryData['journal']->id,
                        'reference' => $entryData['reference'],
                    ],
                    [
                        'entry_date' => $entryData['entry_date'],
                        'description' => $entryData['description'],
                        'source' => 'sage_demo',
                    ],
                );

                foreach ($entryData['lines'] as $line) {
                    $entry->lines()->updateOrCreate(
                        ['line_number' => $line['line_number']],
                        $line,
                    );
                }
            }
        });
    }

    /** @return list<array{code: string, label: string, account_type: string, sage_identifier: string, is_active: bool}> */
    private function chartAccounts(): array
    {
        return [
            ['code' => '401000', 'label' => 'Fournisseurs', 'account_type' => 'liability', 'sage_identifier' => 'SAGE-401000', 'is_active' => true],
            ['code' => '411000', 'label' => 'Clients', 'account_type' => 'asset', 'sage_identifier' => 'SAGE-411000', 'is_active' => true],
            ['code' => '445660', 'label' => 'TVA déductible sur autres biens et services', 'account_type' => 'asset', 'sage_identifier' => 'SAGE-445660', 'is_active' => true],
            ['code' => '445710', 'label' => 'TVA collectée', 'account_type' => 'liability', 'sage_identifier' => 'SAGE-445710', 'is_active' => true],
            ['code' => '606300', 'label' => 'Fournitures d’entretien', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-606300', 'is_active' => true],
            ['code' => '606400', 'label' => 'Fournitures administratives', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-606400', 'is_active' => true],
            ['code' => '615000', 'label' => 'Entretien et réparations', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-615000', 'is_active' => true],
            ['code' => '622600', 'label' => 'Honoraires', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-622600', 'is_active' => true],
            ['code' => '626000', 'label' => 'Frais de télécommunications', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-626000', 'is_active' => true],
            ['code' => '707000', 'label' => 'Ventes de marchandises', 'account_type' => 'revenue', 'sage_identifier' => 'SAGE-707000', 'is_active' => true],
            ['code' => '000012', 'label' => 'Compte auxiliaire de démonstration', 'account_type' => 'other', 'sage_identifier' => 'SAGE-000012', 'is_active' => true],
            ['code' => '6A1000', 'label' => 'Compte local importé avec code alphanumérique', 'account_type' => 'expense', 'sage_identifier' => 'SAGE-6A1000', 'is_active' => true],
        ];
    }

    /** @return list<array{code: string, label: string, sage_identifier: string, is_active: bool}> */
    private function analyticalAccounts(): array
    {
        return [
            ['code' => 'AN001', 'label' => 'Informatique', 'sage_identifier' => 'SAGE-AN001', 'is_active' => true],
            ['code' => 'AN002', 'label' => 'Administration', 'sage_identifier' => 'SAGE-AN002', 'is_active' => true],
            ['code' => '0001', 'label' => 'Centre analytique historique', 'sage_identifier' => 'SAGE-0001', 'is_active' => true],
        ];
    }

    /**
     * @param  array<string, \App\Models\ChartAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    private function thirdParties(array $accounts): array
    {
        return [
            [
                'code' => 'FOU0001',
                'sage_identifier' => 'SAGE-FOU0001',
                'party_type' => 'supplier',
                'name' => 'Atlas Services Numériques',
                'tax_identifier' => '1234567A',
                'payables_account_id' => $accounts['401000']->id,
                'receivables_account_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'FOU0002',
                'sage_identifier' => 'SAGE-FOU0002',
                'party_type' => 'supplier',
                'name' => 'Télécom Tunisie',
                'tax_identifier' => '7654321B',
                'payables_account_id' => $accounts['401000']->id,
                'receivables_account_id' => null,
                'is_active' => true,
            ],
            [
                'code' => 'CLI0001',
                'sage_identifier' => 'SAGE-CLI0001',
                'party_type' => 'customer',
                'name' => 'Carthage Négoce',
                'tax_identifier' => '2345678C',
                'payables_account_id' => null,
                'receivables_account_id' => $accounts['411000']->id,
                'is_active' => true,
            ],
        ];
    }

    /** @return list<array{code: string, label: string, journal_type: string, is_active: bool}> */
    private function journals(): array
    {
        return [
            ['code' => 'ACH', 'label' => 'Achats', 'journal_type' => 'purchase', 'is_active' => true],
            ['code' => 'VTE', 'label' => 'Ventes', 'journal_type' => 'sales', 'is_active' => true],
            ['code' => 'BQ', 'label' => 'Banque', 'journal_type' => 'cash', 'is_active' => true],
            ['code' => 'OD', 'label' => 'Opérations diverses', 'journal_type' => 'general', 'is_active' => true],
        ];
    }

    /**
     * @param  array<string, Journal>  $journals
     * @param  array<string, \App\Models\ChartAccount>  $accounts
     * @param  array<string, ThirdParty>  $parties
     * @param  array<string, \App\Models\AnalyticalAccount>  $analyticalAccounts
     * @return list<array{journal: Journal, reference: string, entry_date: string, description: string, lines: list<array<string, mixed>>}>
     */
    private function entries(array $journals, array $accounts, array $parties, array $analyticalAccounts): array
    {
        return [
            [
                'journal' => $journals['ACH'],
                'reference' => 'ACH-2026-001',
                'entry_date' => '2026-01-15',
                'description' => 'Maintenance du parc informatique',
                'lines' => [
                    [
                        'line_number' => 1,
                        'chart_account_id' => $accounts['615000']->id,
                        'third_party_id' => null,
                        'analytical_account_id' => $analyticalAccounts['AN001']->id,
                        'description' => 'Prestation de maintenance',
                        'debit' => '100.000',
                        'credit' => '0.000',
                    ],
                    [
                        'line_number' => 2,
                        'chart_account_id' => $accounts['445660']->id,
                        'third_party_id' => null,
                        'analytical_account_id' => null,
                        'description' => 'TVA déductible',
                        'debit' => '19.000',
                        'credit' => '0.000',
                    ],
                    [
                        'line_number' => 3,
                        'chart_account_id' => $accounts['401000']->id,
                        'third_party_id' => $parties['FOU0001']->id,
                        'analytical_account_id' => null,
                        'description' => 'Facture Atlas Services Numériques',
                        'debit' => '0.000',
                        'credit' => '119.000',
                    ],
                ],
            ],
            [
                'journal' => $journals['VTE'],
                'reference' => 'VTE-2026-004',
                'entry_date' => '2026-01-28',
                'description' => 'Vente de marchandises à Carthage Négoce',
                'lines' => [
                    [
                        'line_number' => 1,
                        'chart_account_id' => $accounts['411000']->id,
                        'third_party_id' => $parties['CLI0001']->id,
                        'analytical_account_id' => null,
                        'description' => 'Facture client',
                        'debit' => '238.000',
                        'credit' => '0.000',
                    ],
                    [
                        'line_number' => 2,
                        'chart_account_id' => $accounts['707000']->id,
                        'third_party_id' => null,
                        'analytical_account_id' => $analyticalAccounts['AN002']->id,
                        'description' => 'Vente hors taxes',
                        'debit' => '0.000',
                        'credit' => '200.000',
                    ],
                    [
                        'line_number' => 3,
                        'chart_account_id' => $accounts['445710']->id,
                        'third_party_id' => null,
                        'analytical_account_id' => null,
                        'description' => 'TVA collectée',
                        'debit' => '0.000',
                        'credit' => '38.000',
                    ],
                ],
            ],
        ];
    }
}
