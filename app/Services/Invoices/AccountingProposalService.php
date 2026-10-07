<?php

namespace App\Services\Invoices;

use App\Data\StructuredAiResult;
use App\Enums\InvoiceType;
use App\Exceptions\AiProviderException;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\OpenRouter\OpenRouterClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AccountingProposalService
{
    public function __construct(
        private readonly OpenRouterClient $client,
        private readonly AccountingProposalBalanceChecker $balanceChecker,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function analyze(Invoice $invoice): array
    {
        $company = $invoice->company;
        /*
         * -------------------------------------------------------------
         * 1. Find likely supplier candidates
         * -------------------------------------------------------------
         */
        $supplierIds = collect();

        if ($invoice->third_party_id) {
            $supplierIds->push($invoice->third_party_id);
        }

        if ($supplierIds->isEmpty() && $invoice->supplier_tax_identifier) {
            $supplierIds = $company->thirdParties()
                ->where('is_active', true)
                ->whereIn('party_type', ['supplier', 'both'])
                ->where('tax_identifier', $invoice->supplier_tax_identifier)
                ->pluck('id');
        }

        if ($supplierIds->isEmpty() && $invoice->supplier_name) {
            $supplierIds = $company->thirdParties()
                ->where('is_active', true)
                ->whereIn('party_type', ['supplier', 'both'])
                ->where('name', 'like', '%'.$invoice->supplier_name.'%')
                ->limit(10)
                ->pluck('id');
        }
        /*
         * -------------------------------------------------------------
         * 2. Retrieve relevant historical entries
         * -------------------------------------------------------------
         */
        $similarHistoricalEntries = $this->similarHistoricalEntries(
            $invoice,
            $company,
            $supplierIds,
        );
        /*
         * -------------------------------------------------------------
         * 3. Chart accounts
         *
         * Small chart  -> send all
         * Large chart  -> send accounts used in relevant history
         * -------------------------------------------------------------
         */
        $activeAccountsQuery = $company->chartAccounts()
            ->where('is_active', true);

        $accountCount = (clone $activeAccountsQuery)->count();

        if ($accountCount <= 300) {
            $accounts = $activeAccountsQuery
                ->orderBy('code')
                ->get(['id', 'code', 'label', 'account_type']);
        } else {
            $historicalAccountIds = $similarHistoricalEntries
                ->flatMap(fn ($entry) => $entry->lines->pluck('chart_account_id'))
                ->filter()
                ->unique()
                ->values();

            $accounts = $activeAccountsQuery
                ->whereIn('id', $historicalAccountIds)
                ->orderBy('code')
                ->get(['id', 'code', 'label', 'account_type']);
        }

        /*
         * -------------------------------------------------------------
         * 4. Journals
         * -------------------------------------------------------------
         */
        $journals = $company->journals()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'label', 'journal_type']);

        /*
         * -------------------------------------------------------------
         * 5. Suppliers
         *
         * Only send suppliers that are plausible matches.
         * -------------------------------------------------------------
         */
        $thirdParties = $company->thirdParties()
            ->where('is_active', true)
            ->whereIn('party_type', ['supplier', 'both'])
            ->whereIn('id', $supplierIds)
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'name',
                'tax_identifier',
                'payables_account_id',
            ]);

        /* -------------------------------------------------------------
         * 6. Analytical accounts
         *
         * Small list -> send all
         * Large list -> use accounts appearing in relevant history
         * -------------------------------------------------------------
         */
        $activeAnalyticalQuery = $company->analyticalAccounts()
            ->where('is_active', true);

        $analyticalCount = (clone $activeAnalyticalQuery)->count();

        if ($analyticalCount <= 100) {
            $analyticalAccounts = $activeAnalyticalQuery
                ->orderBy('code')
                ->get(['id', 'code', 'label']);
        } else {
            $historicalAnalyticalIds = $similarHistoricalEntries
                ->flatMap(fn ($entry) => $entry->lines->pluck('analytical_account_id'))
                ->filter()
                ->unique()
                ->values();

            $analyticalAccounts = $activeAnalyticalQuery
                ->whereIn('id', $historicalAnalyticalIds)
                ->orderBy('code')
                ->get(['id', 'code', 'label']);
        }

        if ($accounts->isEmpty() || $journals->isEmpty()) {
            throw new AiProviderException(
                'accounting_context_incomplete',
                false,
                'La société doit disposer d’au moins un journal et un compte comptable actif avant l’analyse.',
            );
        }
        // --- old one
        // $accounts = $company->chartAccounts()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label', 'account_type']);
        // $journals = $company->journals()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label', 'journal_type']);
        // $thirdParties = $company->thirdParties()
        //     ->where('is_active', true)
        //     ->whereIn('party_type', ['supplier', 'both'])
        //     ->orderBy('code')
        //     ->get(['id', 'code', 'name', 'tax_identifier', 'payables_account_id']);
        // $analyticalAccounts = $company->analyticalAccounts()
        //     ->where('is_active', true)
        //     ->orderBy('code')
        //     ->get(['id', 'code', 'label']);

        // if ($accounts->isEmpty() || $journals->isEmpty()) {
        //     throw new AiProviderException(
        //         'accounting_context_incomplete',
        //         false,
        //         'La société doit disposer d’au moins un journal et un compte comptable actif avant l’analyse.',
        //     );
        // }

        $result = $this->client->completeJson(
            $this->messages(
                $invoice,
                $company,
                $accounts,
                $journals,
                $thirdParties,
                $analyticalAccounts,
                $similarHistoricalEntries,
            ),
            $this->responseFormat(
                $accounts,
                $journals,
                $thirdParties,
                $analyticalAccounts,
            ),
            6000,
            config('services.openrouter.analyse_models', []),
            'accounting_proposal',
        );


        Log::debug('OpenRouter accounting raw content', [

            'data' => $result->data,
            'usage' => $result->usage,
            'response' => $result->response,
            'model' => $result->model,

        ]);
        $proposal = $this->mapProposal(
            $result,
            $accounts,
            $journals,
            $thirdParties,
            $analyticalAccounts,
        );

        $proposal['warnings'] = $this->balanceChecker->warnings(
            $invoice,
            $proposal['lines'],
        );

        return $proposal;
    }
    /**
     * @param Collection<int, int> $supplierIds
     * @return Collection<int, JournalEntry>
     */
    private function similarHistoricalEntries(
        Invoice $invoice,
        Company $company,
        Collection $supplierIds,
    ): Collection {
        $entries = collect();

        /*
         * First priority:
         * Same supplier + same historical invoice type.
         */
        if ($supplierIds->isNotEmpty()) {
            $sameSupplierAndType = $company->journalEntries()
                ->with([
                    'invoice.accountingProposal',
                    'journal',
                    'lines.chartAccount',
                    'lines.thirdParty',
                    'lines.analyticalAccount',
                ])
                ->where('invoice_id', '!=', $invoice->id)
                ->whereHas('lines', function ($query) use ($supplierIds) {
                    $query->whereIn('third_party_id', $supplierIds);
                })
                ->when(
                    $invoice->accountingProposal?->invoice_type,
                    function ($query) use ($invoice) {
                        $query->whereHas('invoice.accountingProposal', function ($q) use ($invoice) {
                            $q->where('invoice_type', $invoice->accountingProposal->invoice_type);
                        });
                    },
                )
                ->orderByDesc('entry_date')
                ->limit(10)
                ->get();

            $entries = $entries->concat($sameSupplierAndType);
        }

        /*
         * Second priority:
         * Same supplier, regardless of invoice type.
         */
        if ($entries->count() < 10 && $supplierIds->isNotEmpty()) {
            $sameSupplier = $company->journalEntries()
                ->with([
                    'invoice.accountingProposal',
                    'journal',
                    'lines.chartAccount',
                    'lines.thirdParty',
                    'lines.analyticalAccount',
                ])
                ->where('invoice_id', '!=', $invoice->id)
                ->whereHas('lines', function ($query) use ($supplierIds) {
                    $query->whereIn('third_party_id', $supplierIds);
                })
                ->whereNotIn('id', $entries->pluck('id'))
                ->orderByDesc('entry_date')
                ->limit(10 - $entries->count())
                ->get();

            $entries = $entries->concat($sameSupplier);
        }

        /*
         * Third priority:
         * Similar invoice description.
         */
        if ($entries->count() < 10 && $invoice->description) {
            $description = trim($invoice->description);

            $descriptionEntries = $company->journalEntries()
                ->with([
                    'invoice.accountingProposal',
                    'journal',
                    'lines.chartAccount',
                    'lines.thirdParty',
                    'lines.analyticalAccount',
                ])
                ->where('invoice_id', '!=', $invoice->id)
                ->whereNotIn('id', $entries->pluck('id'))
                ->whereHas('invoice', function ($query) use ($description) {
                    $query->where('description', 'like', '%'.$description.'%');
                })
                ->orderByDesc('entry_date')
                ->limit(10 - $entries->count())
                ->get();

            $entries = $entries->concat($descriptionEntries);
        }

        return $entries->take(10)->values();
    }

    /**
     * @param Collection<int, mixed> $accounts
     * @param Collection<int, mixed> $journals
     * @param Collection<int, mixed> $thirdParties
     * @param Collection<int, mixed> $analyticalAccounts
     * @return list<array{role: string, content: string}>
     */
    private function messages(
        Invoice $invoice,
        Company $company,
        Collection $accounts,
        Collection $journals,
        Collection $thirdParties,
        Collection $analyticalAccounts,
    ): array {
        $invoiceData = $invoice->ocr_data ?? [];
        $recentEntries = $company->journalEntries()
            ->with(['journal', 'lines.chartAccount', 'lines.thirdParty', 'lines.analyticalAccount'])
            ->orderByDesc('entry_date')
            ->limit(20)
            ->get();
        $context = [
            'company' => [
                'name' => $company->name,
                'legal_name' => $company->legal_name,
                'activity' => $company->activity,
                'sector' => $company->sector,
                'country_code' => $company->country_code,
                'currency' => $company->currency,
                'configured_vat_rates_percent' => $company->vat_rates ?? [],
                'fiscal_year' => [
                    'start' => $company->fiscal_year_start?->toDateString(),
                    'end' => $company->fiscal_year_end?->toDateString(),
                ],
                'capitalization_threshold' => $company->capitalization_threshold === null ? null : [
                    'amount' => $company->capitalization_threshold,
                    'currency' => $company->currency ?: 'TND',
                ],
            ],
            'invoice' => $invoiceData,
            'invoice_type_choices' => InvoiceType::options(),
            'journals' => $journals->map(fn ($journal): array => [
                'code' => $journal->code,
                'label' => $journal->label,
                'type' => $journal->journal_type,
            ])->all(),
            'active_chart_accounts' => $accounts->map(fn ($account): array => [
                'code' => $account->code,
                'label' => $account->label,
                'type' => $account->account_type,
            ])->all(),
            'existing_supplier_parties' => $thirdParties->map(fn ($party): array => [
                'code' => $party->code,
                'name' => $party->name,
                'tax_identifier' => $party->tax_identifier,
                'payables_account_code' => $accounts->firstWhere('id', $party->payables_account_id)?->code,
            ])->all(),
            'active_analytical_accounts' => $analyticalAccounts->map(fn ($account): array => [
                'code' => $account->code,
                'label' => $account->label,
            ])->all(),
            'similar_historical_entries' => $recentEntries->map(fn ($entry): array => [
                'journal_code' => $entry->journal?->code,
                'reference' => $entry->reference,
                'date' => $entry->entry_date?->toDateString(),
                'description' => $entry->description,
                'lines' => $entry->lines->map(fn ($line): array => [
                    'account_code' => $line->chartAccount?->code,
                    'account_label' => $line->chartAccount?->label,
                    'supplier_code' => $line->thirdParty?->code,
                    'analytical_code' => $line->analyticalAccount?->code,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ])->all(),
            ])->all(),
        ];

        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return [
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
                You prepare a draft purchase-invoice journal proposal for human review.
                Use only the active journals, chart accounts, supplier parties, and analytical accounts provided. Never invent codes or create master data. Classify the invoice using the supplied invoice types, considering the invoice content, company activity, and sector.
                Prioritize evidence in this order: invoice values, company configuration, existing master data, similar historical journal entries, then general accounting conventions.
                Use similar historical journal entries as examples of this company's established accounting treatment, but do not copy them blindly when the current invoice differs.
                Propose a conventional double-entry purchase posting using the supplied invoice values. The entry must balance and debit total must equal the gross invoice total. Do not silently change invoice values. Flag discrepancies or uncertainty in the explanation.
                When applicable, use the company's capitalization threshold together with the nature and intended use of the purchase; do not treat the threshold alone as an automatic capitalization rule.
                Return one-sided amounts per line: never both debit and credit. Use decimal strings with a dot and at most three fractional digits.
                Do not include markdown fences.
                Do not include reasoning, analysis, explanations outside the JSON.
                PROMPT,
            ],
            [
                'role' => 'user',
                'content' => "Create a draft accounting proposal using only this company-scoped context:\n".$contextJson,
            ],
        ];
    }

    /**
     * @param Collection<int, mixed> $accounts
     * @param Collection<int, mixed> $journals
     * @param Collection<int, mixed> $thirdParties
     * @param Collection<int, mixed> $analyticalAccounts
     * @return array<string, mixed>
     */
    private function responseFormat(
        Collection $accounts,
        Collection $journals,
        Collection $thirdParties,
        Collection $analyticalAccounts,
    ): array {
        $nullableCode = fn (array $codes, string $description): array => [
            'type' => ['string', 'null'],
            'enum' => [...$codes, null],
            'description' => $description,
        ];

        $lineProperties = [
            'chart_account_code' => [
                'type' => 'string',
                'enum' => $accounts->pluck('code')->values()->all(),
                'description' => 'One active chart-account code from this company.',
            ],
            'third_party_code' => $nullableCode(
                $thirdParties->pluck('code')->values()->all(),
                'An existing supplier code from this company, or null if no match is certain.',
            ),
            'analytical_account_code' => $nullableCode(
                $analyticalAccounts->pluck('code')->values()->all(),
                'An active analytical-account code from this company, or null if not applicable.',
            ),
            'description' => ['type' => 'string', 'description' => 'Short journal line label.'],
            'debit' => ['type' => 'string', 'description' => 'Non-negative decimal string; use 0.000 on credit-only lines.'],
            'credit' => ['type' => 'string', 'description' => 'Non-negative decimal string; use 0.000 on debit-only lines.'],
        ];

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'accounting_proposal',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'invoice_type' => [
                            'type' => 'string',
                            'enum' => InvoiceType::values(),
                            'description' => 'One accounting category for this supplier invoice.',
                        ],
                        'journal_code' => [
                            'type' => 'string',
                            'enum' => $journals->pluck('code')->values()->all(),
                            'description' => 'One active journal code from this company.',
                        ],
                        'entry_description' => ['type' => 'string', 'description' => 'Concise entry header description.'],
                        'explanation' => ['type' => 'string', 'description' => 'Concise accounting rationale and relevant uncertainty only.'],
                        'lines' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => $lineProperties,
                                'required' => array_keys($lineProperties),
                            ],
                            'description' => 'At least two proposed journal lines.',
                        ],
                    ],
                    'required' => ['invoice_type', 'journal_code', 'entry_description', 'explanation', 'lines'],
                ],
            ],
        ];
    }

    /**
     * @param Collection<int, mixed> $accounts
     * @param Collection<int, mixed> $journals
     * @param Collection<int, mixed> $thirdParties
     * @param Collection<int, mixed> $analyticalAccounts
     * @return array<string, mixed>
     */
    private function mapProposal(
        StructuredAiResult $result,
        Collection $accounts,
        Collection $journals,
        Collection $thirdParties,
        Collection $analyticalAccounts,
    ): array {
        $data = $result->data;
        
        $allowedTopLevel = ['invoice_type', 'journal_code', 'entry_description', 'explanation', 'lines'];
        $unexpectedFields = array_diff(array_keys($data), $allowedTopLevel);
        $invoiceType = is_string($data['invoice_type'] ?? null) ? InvoiceType::tryFrom($data['invoice_type']) : null;
        $journal = $journals->firstWhere('code', $data['journal_code'] ?? null);
        $lines = $data['lines'] ?? null;

        if ($unexpectedFields !== [] || count($data) !== count($allowedTopLevel)) {
            throw new AiProviderException(
                'openrouter_invalid_accounting_proposal',
                false,
                'La proposition comptable contient des champs inattendus.',
                $result->response,
            );
        }

        if ($invoiceType === null || $journal === null || ! is_array($lines) || ! array_is_list($lines) || count($lines) < 2 || count($lines) > 100) {
            throw new AiProviderException(
                'openrouter_invalid_accounting_proposal',
                false,
                'La proposition comptable retournée ne peut pas être validée.',
                $result->response,
            );
        }

        $mappedLines = [];

        $allowedLineFields = [
            'chart_account_code',
            'third_party_code',
            'analytical_account_code',
            'description',
            'debit',
            'credit',
        ];

        foreach ($lines as $line) {
            if (! is_array($line) || array_is_list($line)
                || array_diff(array_keys($line), [...$allowedLineFields, 'confidence']) !== []
                || array_diff($allowedLineFields, array_keys($line)) !== []) {
                throw new AiProviderException(
                    'openrouter_invalid_accounting_proposal',
                    false,
                    'Une ligne de la proposition comptable est invalide.',
                    $result->response,
                );
            }

            $account = $accounts->firstWhere('code', $line['chart_account_code'] ?? null);
            $thirdParty = ($line['third_party_code'] ?? null) === null
                ? null
                : $thirdParties->firstWhere('code', $line['third_party_code']);
            $analyticalAccount = ($line['analytical_account_code'] ?? null) === null
                ? null
                : $analyticalAccounts->firstWhere('code', $line['analytical_account_code']);
            $debit = $line['debit'] ?? null;
            $credit = $line['credit'] ?? null;
            if ($account === null
                || (($line['third_party_code'] ?? null) !== null && $thirdParty === null)
                || (($line['analytical_account_code'] ?? null) !== null && $analyticalAccount === null)
                || ! is_string($line['description'] ?? null)
                || mb_strlen($line['description']) > 255
                || ! $this->isAmount($debit)
                || ! $this->isAmount($credit)
            ) {
                throw new AiProviderException(
                    'openrouter_invalid_accounting_proposal',
                    false,
                    'Une ligne de la proposition contient un compte ou un montant invalide.',
                    $result->response,
                );
            }

            if (($this->amountToMilli($debit) > 0) === ($this->amountToMilli($credit) > 0)) {
                throw new AiProviderException(
                    'openrouter_invalid_accounting_proposal',
                    false,
                    'Chaque ligne comptable doit porter un débit ou un crédit, mais pas les deux.',
                    $result->response,
                );
            }

            $mappedLines[] = [
                'chart_account_id' => $account->id,
                'third_party_id' => $thirdParty?->id,
                'analytical_account_id' => $analyticalAccount?->id,
                'description' => trim($line['description']) === '' ? null : $line['description'],
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        $entryDescription = $data['entry_description'] ?? null;
        $explanation = $data['explanation'] ?? null;

        if (! is_string($entryDescription) || trim($entryDescription) === '' || mb_strlen($entryDescription) > 255
            || ! is_string($explanation) || trim($explanation) === '' || mb_strlen($explanation) > 10000) {
            throw new AiProviderException(
                'openrouter_invalid_accounting_proposal',
                false,
                'La proposition comptable ne contient pas de libellé ou d’explication valide.',
                $result->response,
            );
        }

        return [
            'journal_id' => $journal->id,
            'invoice_type' => $invoiceType->value,
            'entry_description' => trim($entryDescription),
            'explanation' => trim($explanation),
            'lines' => $mappedLines,
            'model' => $result->model,
            'raw_response' => $result->response,
            'usage' => $result->usage,
        ];
    }

    private function isAmount(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{1,15}(?:\.\d{1,3})?$/', $value) === 1;
    }

    private function amountToMilli(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 1000) + (int) str_pad($fraction, 3, '0');
    }
}
