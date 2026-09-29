<?php

namespace Tests\Feature;

use App\Contracts\OcrProvider;
use App\Jobs\AnalyzeAccountingProposal;
use App\Jobs\ExtractInvoiceData;
use App\Jobs\ProcessInvoiceOcr;
use App\Models\AccountingProposal;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\AccountingProposalService;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Invoices\InvoiceDataExtractionService;
use App\Services\Invoices\InvoiceDataPersistence;
use App\Services\Invoices\InvoiceProcessingErrorMessage;
use App\Services\Ocr\InvoiceOcrSchema;
use App\Services\Ocr\InvoiceTotalsConsistencyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenRouterInvoicePipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.api_key', 'test-ocr-space-key');
        config()->set('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        config()->set('services.ocr_space.ocr_engine', 3);
        config()->set('services.ocr_space.max_file_size_bytes', 1024 * 1024);
        config()->set('services.ocr_space.ca_bundle', null);
        config()->set('services.openrouter.api_key', 'test-openrouter-key');
        config()->set('services.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions');
        config()->set('services.openrouter.model', 'openrouter/free');
        config()->set('services.openrouter.timeout', 120);
        config()->set('services.openrouter.ca_bundle', null);
        config()->set('services.openrouter.max_ocr_chars', 100000);
    }

    public function test_ocr_text_is_structured_and_audited_then_a_company_scoped_proposal_is_created_and_human_approved(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create(['name' => 'Société Alpha', 'activity' => 'Services informatiques']);
        $this->seedAccountingContext($company);
        $foreignCompany = Company::factory()->create(['name' => 'Société Confidentielle']);
        $foreignCompany->chartAccounts()->create([
            'code' => '999999',
            'label' => 'Compte secret autre société',
            'account_type' => 'expense',
            'is_active' => true,
        ]);
        $invoice = $this->queuedInvoice($company);
        $ocrText = "FACTURE FAC-2026-001\nPrestation de service\nHT 100,000 TND\nTVA 19,000 TND\nTTC 119,000 TND";
        $annotation = $this->validInvoiceData();
        $proposal = $this->validProposal();

        Http::fake([
            'https://api.ocr.space/parse/image' => Http::response([
                'OCRExitCode' => 1,
                'IsErroredOnProcessing' => false,
                'ProcessingTimeInMilliseconds' => 320,
                'ParsedResults' => [['FileParseExitCode' => 1, 'ParsedText' => $ocrText]],
            ]),
            'https://openrouter.ai/api/v1/chat/completions' => function (ClientRequest $request) use ($annotation, $proposal) {
                $schemaName = $request->data()['response_format']['json_schema']['name'] ?? null;
                $content = $schemaName === 'supplier_invoice' ? $annotation : $proposal;

                return Http::response($this->openRouterResponse($content), 200);
            },
        ]);

        $this->runOcr($invoice);
        $invoice->refresh();

        $this->assertSame('data_extraction', $invoice->status);
        $this->assertSame($ocrText, $invoice->ocr_text);
        $this->assertSame($ocrText, $invoice->ocr_response['ParsedResults'][0]['ParsedText']);
        $this->assertNull($invoice->ocr_data);
        Queue::assertPushed(ExtractInvoiceData::class);

        $this->runExtraction($invoice);
        $invoice->refresh();
        $this->assertSame('accounting_analysis', $invoice->status);
        $this->assertSame('Fournisseur Démo', $invoice->supplier_name);
        $this->assertSame('119.000', $invoice->total_amount);
        $this->assertSame('FAC-2026-001', $invoice->ocr_data['invoice_number']);
        $this->assertSame('qwen/test-free-model', $invoice->extraction_model);
        $this->assertSame($annotation, json_decode($invoice->extraction_response['choices'][0]['message']['content'], true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame($ocrText, $invoice->ocr_text);
        Queue::assertPushed(AnalyzeAccountingProposal::class);

        $this->runAccountingAnalysis($invoice);
        $invoice->refresh();
        $accountingProposal = $invoice->accountingProposal()->with('lines')->firstOrFail();
        $this->assertSame('proposal_ready', $invoice->status);
        $this->assertSame('ready', $accountingProposal->status);
        $this->assertSame('ACH', $accountingProposal->journal->code);
        $this->assertSame([], $accountingProposal->warnings);
        $this->assertCount(3, $accountingProposal->lines);

        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $this->actingAs($manager)
            ->post(route('companies.invoices.proposal.approve', [$company, $invoice]))
            ->assertRedirect();

        $invoice->refresh();
        $accountingProposal->refresh();
        $journalEntry = $invoice->journalEntry()->with('lines')->firstOrFail();
        $this->assertSame('accounting_validated', $invoice->status);
        $this->assertSame('approved', $accountingProposal->status);
        $this->assertSame($manager->id, $accountingProposal->reviewed_by);
        $this->assertSame('ai_proposal', $journalEntry->source);
        $this->assertSame($invoice->id, $journalEntry->invoice_id);
        $this->assertSame(3, $journalEntry->lines->count());

        Http::assertSent(function (ClientRequest $request) use ($ocrText): bool {
            if ($request->url() !== 'https://openrouter.ai/api/v1/chat/completions'
                || ! $request->hasHeader('Authorization', 'Bearer test-openrouter-key')
                || ($request->data()['model'] ?? null) !== 'openrouter/free') {
                return false;
            }

            return str_contains($request->data()['messages'][1]['content'] ?? '', $ocrText);
        });
        Http::assertSent(function (ClientRequest $request): bool {
            $data = $request->data();

            if (($data['response_format']['json_schema']['name'] ?? null) !== 'accounting_proposal') {
                return false;
            }

            $allowedCodes = $data['response_format']['json_schema']['schema']['properties']['lines']['items']['properties']['chart_account_code']['enum'] ?? [];
            $context = $data['messages'][1]['content'] ?? '';

            return ! in_array('999999', $allowedCodes, true)
                && ! str_contains($context, 'Société Confidentielle')
                && ! str_contains($context, 'Compte secret autre société');
        });
        Http::assertSentCount(3);
    }

    public function test_openrouter_extraction_error_keeps_the_raw_ocr_response_and_text_intact(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->queuedInvoice($company);
        $ocrText = 'Facture fournisseur non structurée';
        $ocrResponse = [
            'OCRExitCode' => 1,
            'ParsedResults' => [['FileParseExitCode' => 1, 'ParsedText' => $ocrText]],
        ];

        Http::fake([
            'https://api.ocr.space/parse/image' => Http::response($ocrResponse),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'invalid key']], 401),
        ]);

        $this->runOcr($invoice);
        $this->runExtraction($invoice);

        $invoice->refresh();
        $this->assertSame('data_extraction_failed', $invoice->status);
        $this->assertSame('openrouter_authentication_failed', $invoice->ocr_error_code);
        $this->assertSame($ocrResponse, $invoice->ocr_response);
        $this->assertSame($ocrText, $invoice->ocr_text);
        $this->assertSame(['error' => ['message' => 'invalid key']], $invoice->extraction_response);
        Queue::assertNotPushed(AnalyzeAccountingProposal::class);
    }

    public function test_incomplete_extraction_is_saved_but_never_queued_for_accounting_analysis(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $invoice = $this->queuedInvoice($company);
        $incomplete = $this->validInvoiceData();
        $incomplete['supplier_name'] = null;
        $ocrText = "FACTURE FAC-2026-001\nTotal 119,000 TND";

        Http::fake([
            'https://api.ocr.space/parse/image' => Http::response([
                'OCRExitCode' => 1,
                'ParsedResults' => [['FileParseExitCode' => 1, 'ParsedText' => $ocrText]],
            ]),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->openRouterResponse($incomplete)),
        ]);

        $this->runOcr($invoice);
        $this->runExtraction($invoice);

        $invoice->refresh();
        $this->assertSame('invoice_incomplete', $invoice->status);
        $this->assertNull($invoice->ocr_data['supplier_name']);
        $this->assertSame('Prestation informatique', $invoice->ocr_data['lines'][0]['description']);
        $this->assertContains('missing_supplier_name', $invoice->ocr_warnings);
        $this->assertSame('invoice_data_incomplete', $invoice->ocr_error_code);
        Queue::assertNotPushed(AnalyzeAccountingProposal::class);
    }

    public function test_authorized_correction_of_required_invoice_data_resumes_analysis_and_preserves_raw_ocr_audit(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $invoice = $this->queuedInvoice($company, 'invoice_incomplete');
        $rawOcrResponse = ['ParsedResults' => [['ParsedText' => 'raw invoice text']]];
        $invoice->forceFill([
            'ocr_response' => $rawOcrResponse,
            'ocr_text' => 'raw invoice text',
            'ocr_data' => $this->validInvoiceData(),
        ])->save();
        $incomplete = $this->validInvoiceData();
        $incomplete['supplier_name'] = null;
        app(InvoiceDataPersistence::class)->store($invoice, app(InvoiceOcrSchema::class)->validate($incomplete));

        $this->actingAs($manager)
            ->put(route('companies.invoices.extraction.update', [$company, $invoice]), [
                'invoice_data' => $this->validInvoiceData(),
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('accounting_analysis', $invoice->status);
        $this->assertSame('Fournisseur Démo', $invoice->supplier_name);
        $this->assertSame($rawOcrResponse, $invoice->ocr_response);
        $this->assertSame('raw invoice text', $invoice->ocr_text);
        $this->assertSame($manager->id, $invoice->extraction_corrected_by);
        $this->assertNotNull($invoice->extraction_corrected_at);
        Queue::assertPushed(AnalyzeAccountingProposal::class);
    }

    public function test_reanalysis_preserves_the_previous_proposal_response_and_creates_a_new_version(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $context = $this->seedAccountingContext($company);
        $invoice = $this->queuedInvoice($company, 'proposal_ready');
        app(InvoiceDataPersistence::class)->store($invoice, app(InvoiceOcrSchema::class)->validate($this->validInvoiceData()));
        $firstProposal = $this->createProposal($company, $invoice, $context);
        $firstRawResponse = ['choices' => [['message' => ['content' => 'original accounting analysis']]]];
        $firstProposal->forceFill(['raw_response' => $firstRawResponse])->save();
        $correctedData = $this->validInvoiceData();
        $correctedData['description'] = 'Prestation informatique corrigée';

        $this->actingAs($manager)
            ->put(route('companies.invoices.extraction.update', [$company, $invoice]), [
                'invoice_data' => $correctedData,
            ])
            ->assertRedirect();

        $this->assertSame('accounting_analysis', $invoice->fresh()->status);
        $this->assertSame('superseded', $firstProposal->fresh()->status);
        $this->assertSame($firstRawResponse, $firstProposal->fresh()->raw_response);
        $this->assertCount(3, $firstProposal->lines()->get());

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->openRouterResponse($this->validProposal())),
        ]);
        $this->runAccountingAnalysis($invoice->fresh());

        $this->assertDatabaseCount('accounting_proposals', 2);
        $latestProposal = $invoice->fresh()->accountingProposal()->firstOrFail();
        $this->assertNotSame($firstProposal->id, $latestProposal->id);
        $this->assertSame('ready', $latestProposal->status);
        $this->assertSame('superseded', $firstProposal->fresh()->status);
        $this->assertSame($firstRawResponse, $firstProposal->fresh()->raw_response);
    }

    public function test_viewing_or_editing_an_invoice_through_another_company_is_not_allowed(): void
    {
        Storage::fake('local');
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create(['cabinet_id' => $firstCompany->cabinet_id]);
        $manager = $this->companyUser($firstCompany, User::COMPANY_ROLE_INVOICE_MANAGER);
        $invoice = $this->queuedInvoice($secondCompany, 'invoice_incomplete');

        $this->actingAs($manager)
            ->get(route('companies.invoices.details', [$firstCompany, $invoice]))
            ->assertNotFound();

        $this->actingAs($manager)
            ->put(route('companies.invoices.extraction.update', [$firstCompany, $invoice]), [
                'invoice_data' => $this->validInvoiceData(),
            ])
            ->assertForbidden();
    }

    public function test_proposal_edits_reject_cross_company_accounts_and_approval_rejects_unbalanced_lines(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $context = $this->seedAccountingContext($company);
        $invoice = $this->queuedInvoice($company, 'proposal_ready');
        app(InvoiceDataPersistence::class)->store($invoice, app(InvoiceOcrSchema::class)->validate($this->validInvoiceData()));
        $proposal = $this->createProposal($company, $invoice, $context);
        $otherCompany = Company::factory()->create(['cabinet_id' => $company->cabinet_id]);
        $foreignAccount = $otherCompany->chartAccounts()->create([
            'code' => '9999', 'label' => 'Compte étranger', 'account_type' => 'expense', 'is_active' => true,
        ]);

        $badLines = $this->proposalLines($context);
        $badLines[0]['chart_account_id'] = $foreignAccount->id;
        $this->actingAs($manager)
            ->from('/invoices')
            ->put(route('companies.invoices.proposal.update', [$company, $invoice]), [
                'journal_id' => $context['journal']->id,
                'entry_description' => 'Facture de service',
                'lines' => $badLines,
            ])
            ->assertSessionHasErrors('lines');

        $twoSidedLines = $this->proposalLines($context);
        $twoSidedLines[0]['credit'] = '10.000';
        $twoSidedLines[2]['credit'] = '109.000';
        $this->actingAs($manager)
            ->from('/invoices')
            ->put(route('companies.invoices.proposal.update', [$company, $invoice]), [
                'journal_id' => $context['journal']->id,
                'entry_description' => 'Facture de service',
                'lines' => $twoSidedLines,
            ])
            ->assertSessionHasErrors('lines');

        $unbalancedLines = $this->proposalLines($context);
        $unbalancedLines[2]['credit'] = '120.000';
        $this->actingAs($manager)
            ->from('/invoices')
            ->put(route('companies.invoices.proposal.update', [$company, $invoice]), [
                'journal_id' => $context['journal']->id,
                'entry_description' => 'Facture de service',
                'lines' => $unbalancedLines,
            ])
            ->assertRedirect('/invoices');
        $this->assertContains('proposal_unbalanced', $proposal->fresh()->warnings);
        $this->assertSame($manager->id, $proposal->fresh()->modified_by);
        $this->assertNotNull($proposal->fresh()->modified_at);

        $this->actingAs($manager)
            ->post(route('companies.invoices.proposal.approve', [$company, $invoice]))
            ->assertSessionHasErrors('proposal');
        $this->assertDatabaseCount('journal_entries', 0);

        $this->actingAs($manager)
            ->from('/invoices')
            ->put(route('companies.invoices.proposal.update', [$company, $invoice]), [
                'journal_id' => $context['journal']->id,
                'entry_description' => 'Facture de service',
                'lines' => $this->proposalLines($context),
            ])
            ->assertRedirect('/invoices');

        $this->actingAs($manager)
            ->post(route('companies.invoices.proposal.approve', [$company, $invoice]))
            ->assertRedirect();
        $this->assertSame('accounting_validated', $invoice->fresh()->status);
        $this->assertSame(1, $invoice->journalEntry()->count());
    }

    public function test_retry_targets_the_failed_ai_stage_without_repeating_ocr(): void
    {
        Storage::fake('local');
        Queue::fake();
        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $extractionInvoice = $this->queuedInvoice($company, 'data_extraction_failed');
        $extractionInvoice->forceFill(['ocr_text' => 'raw OCR already stored'])->save();
        $analysisInvoice = $this->queuedInvoice($company, 'accounting_analysis_failed');
        $analysisInvoice->forceFill(['ocr_data' => $this->validInvoiceData()])->save();

        $this->actingAs($manager)
            ->post(route('companies.invoices.ocr.retry', [$company, $extractionInvoice]))
            ->assertRedirect();
        $this->actingAs($manager)
            ->post(route('companies.invoices.ocr.retry', [$company, $analysisInvoice]))
            ->assertRedirect();

        $this->assertSame('data_extraction', $extractionInvoice->fresh()->status);
        $this->assertSame('accounting_analysis', $analysisInvoice->fresh()->status);
        Queue::assertPushed(ExtractInvoiceData::class, fn (ExtractInvoiceData $job): bool => $job->invoiceId === $extractionInvoice->id);
        Queue::assertPushed(AnalyzeAccountingProposal::class, fn (AnalyzeAccountingProposal $job): bool => $job->invoiceId === $analysisInvoice->id);
        Queue::assertNotPushed(ProcessInvoiceOcr::class);
    }

    public function test_rejecting_a_proposal_is_a_human_decision_without_creating_a_journal_entry(): void
    {
        Storage::fake('local');
        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $context = $this->seedAccountingContext($company);
        $invoice = $this->queuedInvoice($company, 'proposal_ready');
        app(InvoiceDataPersistence::class)->store($invoice, app(InvoiceOcrSchema::class)->validate($this->validInvoiceData()));
        $proposal = $this->createProposal($company, $invoice, $context);

        $this->actingAs($manager)
            ->post(route('companies.invoices.proposal.reject', [$company, $invoice]))
            ->assertRedirect();

        $this->assertSame('proposal_rejected', $invoice->fresh()->status);
        $this->assertSame('rejected', $proposal->fresh()->status);
        $this->assertSame($manager->id, $proposal->fresh()->reviewed_by);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    private function runOcr(Invoice $invoice): void
    {
        (new ProcessInvoiceOcr($invoice->id, $invoice->company_id))->handle(
            app(OcrProvider::class),
            app(InvoiceDataCompletenessChecker::class),
            app(InvoiceDataPersistence::class),
            app(InvoiceTotalsConsistencyChecker::class),
            app(InvoiceProcessingErrorMessage::class),
        );
    }

    private function runExtraction(Invoice $invoice): void
    {
        (new ExtractInvoiceData($invoice->id, $invoice->company_id))->handle(
            app(InvoiceDataExtractionService::class),
            app(InvoiceDataCompletenessChecker::class),
            app(InvoiceDataPersistence::class),
            app(InvoiceTotalsConsistencyChecker::class),
            app(InvoiceProcessingErrorMessage::class),
        );
    }

    private function runAccountingAnalysis(Invoice $invoice): void
    {
        (new AnalyzeAccountingProposal($invoice->id, $invoice->company_id))->handle(
            app(AccountingProposalService::class),
            app(InvoiceDataCompletenessChecker::class),
            app(InvoiceProcessingErrorMessage::class),
        );
    }

    private function queuedInvoice(Company $company, string $status = 'ocr_queued'): Invoice
    {
        $path = "companies/{$company->id}/invoices/source.png";
        Storage::disk('local')->put($path, 'private-test-image');

        return $company->invoices()->create([
            'storage_disk' => 'local',
            'file_path' => $path,
            'original_filename' => 'source.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen('private-test-image'),
            'file_sha256' => hash('sha256', uniqid('', true)),
            'status' => $status,
        ]);
    }

    private function companyUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['cabinet_id' => $company->cabinet_id]);
        $company->users()->attach($user, ['role' => $role]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validInvoiceData(): array
    {
        return [
            'supplier_name' => 'Fournisseur Démo',
            'supplier_tax_identifier' => '1234567/A/B/000',
            'supplier_address' => 'Tunis',
            'customer_name' => 'Société Démo',
            'customer_tax_identifier' => null,
            'invoice_number' => 'FAC-2026-001',
            'purchase_order_reference' => null,
            'payment_terms' => null,
            'bank_name' => null,
            'bank_account_reference' => null,
            'description' => 'Prestation de service',
            'invoice_date' => '2026-09-27',
            'due_date' => null,
            'currency' => 'TND',
            'vat_rate' => '19.000',
            'fodec_rate' => '0.000',
            'subtotal' => '100.000',
            'vat_amount' => '19.000',
            'fodec_amount' => '0.000',
            'other_tax_amount' => '0.000',
            'stamp_amount' => '0.000',
            'withholding_rate' => null,
            'withholding_amount' => '0.000',
            'total_amount' => '119.000',
            'lines' => [[
                'reference' => 'SVC-1',
                'description' => 'Prestation informatique',
                'quantity' => '2.000',
                'unit_price' => '50.000',
                'subtotal' => '100.000',
                'vat_amount' => '19.000',
                'fodec_amount' => '0.000',
                'other_tax_amount' => '0.000',
                'total_amount' => '119.000',
                'vat_rate' => '19.000',
                'fodec_rate' => '0.000',
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function validProposal(): array
    {
        return [
            'journal_code' => 'ACH',
            'entry_description' => 'Achat prestation FAC-2026-001',
            'explanation' => 'La prestation est imputée en charge, la TVA est isolée, et le fournisseur est crédité du TTC.',
            'lines' => [
                ['chart_account_code' => '607000', 'third_party_code' => null, 'analytical_account_code' => null, 'description' => 'Prestation informatique', 'debit' => '100.000', 'credit' => '0.000', 'confidence' => 0.9],
                ['chart_account_code' => '445660', 'third_party_code' => null, 'analytical_account_code' => null, 'description' => 'TVA déductible', 'debit' => '19.000', 'credit' => '0.000', 'confidence' => 0.9],
                ['chart_account_code' => '401000', 'third_party_code' => 'SUP-001', 'analytical_account_code' => 'AXE-IT', 'description' => 'Fournisseur FAC-2026-001', 'debit' => '0.000', 'credit' => '119.000', 'confidence' => 0.95],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function seedAccountingContext(Company $company): array
    {
        $expense = $company->chartAccounts()->create(['code' => '607000', 'label' => 'Achats de prestations', 'account_type' => 'expense', 'is_active' => true]);
        $vat = $company->chartAccounts()->create(['code' => '445660', 'label' => 'TVA déductible', 'account_type' => 'asset', 'is_active' => true]);
        $payable = $company->chartAccounts()->create(['code' => '401000', 'label' => 'Fournisseurs', 'account_type' => 'liability', 'is_active' => true]);
        $journal = $company->journals()->create(['code' => 'ACH', 'label' => 'Achats', 'journal_type' => 'purchase', 'is_active' => true]);
        $analytical = $company->analyticalAccounts()->create(['code' => 'AXE-IT', 'label' => 'Informatique', 'is_active' => true]);
        $supplier = $company->thirdParties()->create([
            'code' => 'SUP-001',
            'party_type' => 'supplier',
            'name' => 'Fournisseur Démo',
            'tax_identifier' => '1234567/A/B/000',
            'payables_account_id' => $payable->id,
            'is_active' => true,
        ]);

        return compact('expense', 'vat', 'payable', 'journal', 'analytical', 'supplier');
    }

    /** @param array<string, mixed> $context
     * @return list<array<string, mixed>>
     */
    private function proposalLines(array $context): array
    {
        return [
            ['chart_account_id' => $context['expense']->id, 'third_party_id' => null, 'analytical_account_id' => $context['analytical']->id, 'description' => 'Prestation informatique', 'debit' => '100.000', 'credit' => '0.000', 'confidence' => 0.9],
            ['chart_account_id' => $context['vat']->id, 'third_party_id' => null, 'analytical_account_id' => null, 'description' => 'TVA déductible', 'debit' => '19.000', 'credit' => '0.000', 'confidence' => 0.9],
            ['chart_account_id' => $context['payable']->id, 'third_party_id' => $context['supplier']->id, 'analytical_account_id' => null, 'description' => 'Fournisseur FAC-2026-001', 'debit' => '0.000', 'credit' => '119.000', 'confidence' => 0.95],
        ];
    }

    /** @param array<string, mixed> $context */
    private function createProposal(Company $company, Invoice $invoice, array $context): AccountingProposal
    {
        $proposal = $company->accountingProposals()->create([
            'invoice_id' => $invoice->id,
            'journal_id' => $context['journal']->id,
            'status' => 'ready',
            'model' => 'qwen/test-free-model',
            'entry_description' => 'Achat prestation FAC-2026-001',
            'explanation' => 'Proposition de test équilibrée.',
            'warnings' => [],
        ]);

        foreach ($this->proposalLines($context) as $index => $line) {
            $proposal->lines()->create([...$line, 'line_number' => $index + 1]);
        }

        return $proposal;
    }

    /** @param array<string, mixed> $content
     * @return array<string, mixed>
     */
    private function openRouterResponse(array $content): array
    {
        return [
            'id' => 'gen-test',
            'model' => 'qwen/test-free-model',
            'choices' => [[
                'index' => 0,
                'finish_reason' => 'stop',
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ],
            ]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 80, 'total_tokens' => 180],
        ];
    }
}
