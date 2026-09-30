<?php

namespace Tests\Feature;

use App\Contracts\OcrProvider;
use App\Exceptions\OcrProviderException;
use App\Jobs\ProcessInvoiceOcr;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Invoices\InvoiceDataPersistence;
use App\Services\Invoices\InvoiceProcessingErrorMessage;
use App\Services\Ocr\InvoiceTotalsConsistencyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceOcrProcessingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ocr.provider', 'mistral');
        config()->set('services.mistral.api_key', 'test-mistral-key');
        config()->set('services.mistral.base_url', 'https://api.mistral.ai');
        config()->set('services.mistral.ocr_model', 'mistral-ocr-latest');
        config()->set('services.mistral.ca_bundle', null);
        config()->set('services.ocr_space.api_key', null);
        config()->set('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        config()->set('services.ocr_space.ocr_engine', 3);
        config()->set('services.ocr_space.ca_bundle', null);
        config()->set('services.ocr_space.max_file_size_bytes', 1024 * 1024);
    }

    public function test_ocr_extracts_validated_invoice_and_lines_and_keeps_raw_response(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $annotation = $this->validAnnotation();
        $providerResponse = $this->providerResponse($annotation);
        Http::fake(['https://api.mistral.ai/v1/ocr' => Http::response($providerResponse)]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('accounting_analysis', $invoice->status);
        $this->assertSame(1, $invoice->ocr_attempts);
        $this->assertSame('Fournisseur Démo', $invoice->supplier_name);
        $this->assertSame('119.000', $invoice->total_amount);
        $this->assertSame([], $invoice->ocr_warnings);
        $this->assertSame('FAC-2026-001', $invoice->ocr_data['invoice_number']);
        $this->assertSame('ART-1', $invoice->ocr_data['lines'][0]['reference']);
        $this->assertSame($providerResponse, $invoice->ocr_response);
        $this->assertSame('mistral-ocr-latest', $invoice->ocr_model);
        $this->assertSame(['pages_processed' => 1], $invoice->ocr_usage);

        $line = $invoice->lines()->sole();
        $this->assertSame(1, $line->line_number);
        $this->assertSame('ART-1', $line->reference);
        $this->assertSame('119.000', $line->total_amount);

        Http::assertSent(function (ClientRequest $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.mistral.ai/v1/ocr'
                && $request->hasHeader('Authorization', 'Bearer test-mistral-key')
                && $data['model'] === 'mistral-ocr-latest'
                && $data['document']['type'] === 'image_url'
                && $data['document_annotation_format']['type'] === 'json_schema'
                && $data['document_annotation_format']['json_schema']['strict'] === true
                && $data['include_image_base64'] === false;
        });
    }

    public function test_invalid_structured_annotation_is_kept_for_audit_but_not_persisted_as_invoice_data(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $providerResponse = $this->providerResponse(['supplier_name' => 'Texte sans schéma']);
        Http::fake(['https://api.mistral.ai/v1/ocr' => Http::response($providerResponse)]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('invalid_structured_annotation', $invoice->ocr_error_code);
        $this->assertSame($providerResponse, $invoice->ocr_response);
        $this->assertNull($invoice->ocr_data);
        $this->assertNull($invoice->supplier_name);
        $this->assertSame(0, $invoice->lines()->count());
    }

    public function test_tax_total_mismatch_is_signaled_without_rewriting_the_extracted_total(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $annotation = $this->validAnnotation();
        $annotation['total_amount'] = '120.000';
        $providerResponse = $this->providerResponse($annotation);
        Http::fake(['https://api.mistral.ai/v1/ocr' => Http::response($providerResponse)]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('accounting_analysis', $invoice->status);
        $this->assertSame('120.000', $invoice->total_amount);
        $this->assertSame(['invoice_total_mismatch'], $invoice->ocr_warnings);
    }

    public function test_gross_ttc_is_checked_before_withholding_and_net_to_pay_is_checked_separately(): void
    {
        $invoiceData = $this->validAnnotation();
        $invoiceData['withholding_amount'] = '10.000';
        $invoiceData['total_amount'] = '119.000';
        $invoiceData['net_to_pay_amount'] = '109.000';

        $checker = app(InvoiceTotalsConsistencyChecker::class);
        $this->assertSame([], $checker->warnings($invoiceData));

        $invoiceData['net_to_pay_amount'] = '108.999';
        $this->assertSame(['invoice_net_to_pay_mismatch'], $checker->warnings($invoiceData));

        $invoiceData['net_to_pay_amount'] = '109.000';
        $invoiceData['total_amount'] = '109.000';
        $this->assertContains('invoice_total_mismatch', $checker->warnings($invoiceData));
    }

    public function test_missing_withholding_does_not_make_gross_ttc_check_inconclusive(): void
    {
        $invoiceData = $this->validAnnotation();
        $invoiceData['withholding_amount'] = null;
        $invoiceData['net_to_pay_amount'] = null;

        $this->assertSame([], app(InvoiceTotalsConsistencyChecker::class)->warnings($invoiceData));
    }

    public function test_vat_rate_is_checked_deterministically_when_a_global_rate_is_available(): void
    {
        $invoiceData = $this->validAnnotation();
        $invoiceData['vat_amount'] = '18.000';
        $invoiceData['total_amount'] = '118.000';

        $this->assertSame(
            ['invoice_vat_mismatch'],
            app(InvoiceTotalsConsistencyChecker::class)->warnings($invoiceData),
        );
    }

    public function test_itemized_amounts_are_checked_against_invoice_level_totals(): void
    {
        $invoiceData = $this->validAnnotation();
        $invoiceData['subtotal'] = '1645.000';
        $invoiceData['vat_rate'] = null;
        $invoiceData['vat_amount'] = '1645.000';
        $invoiceData['stamp_amount'] = '1.000';
        $invoiceData['total_amount'] = '1646.000';
        $invoiceData['lines'] = [
            ['subtotal' => '300.000', 'vat_amount' => '0.000'],
            ['subtotal' => '1000000.000', 'vat_amount' => '0.000'],
            ['subtotal' => '345.000', 'vat_amount' => '0.000'],
        ];

        $checker = app(InvoiceTotalsConsistencyChecker::class);
        $warnings = $checker->warnings($invoiceData);

        $this->assertContains('invoice_lines_subtotal_mismatch', $warnings);
        $this->assertContains('invoice_lines_vat_mismatch', $warnings);
        $this->assertTrue($checker->hasBlockingWarnings($warnings));
    }

    public function test_missing_tax_components_make_total_check_inconclusive_instead_of_assuming_zero(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $annotation = $this->validAnnotation();
        $annotation['fodec_amount'] = null;
        $providerResponse = $this->providerResponse($annotation);
        Http::fake(['https://api.mistral.ai/v1/ocr' => Http::response($providerResponse)]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('119.000', $invoice->total_amount);
        $this->assertSame(['invoice_totals_unverified'], $invoice->ocr_warnings);
    }

    public function test_temporary_provider_failures_are_retried_and_eventually_marked_failed(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        Http::fake(['https://api.mistral.ai/v1/ocr' => Http::response(['message' => 'temporary'], 503)]);
        $job = new ProcessInvoiceOcr($invoice->id, $invoice->company_id);
        $exception = null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $this->handleOcr($job);
                $this->fail('A temporary provider error should be rethrown so Laravel can retry the job.');
            } catch (OcrProviderException $caught) {
                $exception = $caught;
                $this->assertTrue($caught->retryable);
                $this->assertSame('provider_unavailable', $caught->errorCode);
            }

            $this->assertSame($attempt, $invoice->fresh()->ocr_attempts);
            $this->assertSame('ocr_processing', $invoice->fresh()->status);
        }

        $job->failed($exception);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('provider_unavailable', $invoice->ocr_error_code);
        $this->assertNotNull($invoice->ocr_failed_at);
    }

    public function test_job_does_not_process_an_invoice_outside_its_company_scope(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $otherCompany = Company::factory()->create();
        Http::preventStrayRequests();

        $this->handleOcr(new ProcessInvoiceOcr($invoice->id, $otherCompany->id));

        $invoice->refresh();
        $this->assertSame('ocr_queued', $invoice->status);
        $this->assertSame(0, $invoice->ocr_attempts);
    }

    public function test_missing_provider_configuration_fails_visibly_without_sending_a_request(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        config()->set('services.mistral.api_key', null);
        Http::preventStrayRequests();

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('configuration_missing', $invoice->ocr_error_code);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    public function test_invalid_ca_bundle_fails_without_retrying_or_sending_a_request(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $missingCaBundle = sys_get_temp_dir()
            .DIRECTORY_SEPARATOR.'missing-mistral-ca-bundle-'.bin2hex(random_bytes(8)).'.pem';
        config()->set('services.mistral.ca_bundle', $missingCaBundle);
        Http::preventStrayRequests();

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('tls_ca_bundle_invalid', $invoice->ocr_error_code);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    public function test_tls_certificate_failure_is_not_retried_and_has_an_actionable_error(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        Http::fake([
            'https://api.mistral.ai/v1/ocr' => function (): never {
                throw new ConnectionException(
                    'cURL error 60: SSL certificate problem: unable to get local issuer certificate',
                );
            },
        ]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('tls_certificate_verification_failed', $invoice->ocr_error_code);
        $this->assertStringContainsString('vérification TLS', $invoice->ocr_error_message);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    public function test_ocr_space_engine_3_transcribes_text_without_fabricating_structured_invoice_fields(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        $ocrText = "FACTURE N° FA-2026-01\nDate : 29/09/2026\nTotal TTC : 119,000 TND";
        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.api_key', 'test-ocr-space-key');
        config()->set('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');
        Http::fake([
            'https://api.ocr.space/parse/image' => Http::response([
                'OCRExitCode' => 1,
                'IsErroredOnProcessing' => false,
                'ProcessingTimeInMilliseconds' => '451',
                'ParsedResults' => [[
                    'FileParseExitCode' => '1',
                    'ParsedText' => $ocrText,
                ]],
            ]),
        ]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('data_extraction', $invoice->status);
        $this->assertSame('ocr.space-engine-3', $invoice->ocr_model);
        $this->assertNull($invoice->description);
        $this->assertSame($ocrText, $invoice->ocr_text);
        $this->assertNull($invoice->supplier_name);
        $this->assertNull($invoice->invoice_number);
        $this->assertNull($invoice->total_amount);
        $this->assertSame([], $invoice->ocr_warnings);
        $this->assertNull($invoice->ocr_data);
        $this->assertSame([], $invoice->lines()->get()->all());
        $this->assertSame(1, $invoice->ocr_usage['pages_processed']);
        $this->assertSame(451, $invoice->ocr_usage['processing_time_ms']);
        $this->assertSame($ocrText, $invoice->ocr_response['ParsedResults'][0]['ParsedText']);

        Http::assertSent(function (ClientRequest $request): bool {
            $fields = collect($request->data())->mapWithKeys(
                fn (array $part): array => [$part['name'] => $part['contents'] ?? null],
            );

            return $request->url() === 'https://api.ocr.space/parse/image'
                && $request->hasHeader('apikey', 'test-ocr-space-key')
                && $fields->get('OCREngine') === '3'
                && $fields->get('language') === 'auto'
                && $fields->get('isTable') === 'true'
                && $request->hasFile('file');
        });
    }

    public function test_ocr_space_partial_pdf_result_fails_instead_of_silently_saving_incomplete_text(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.api_key', 'test-ocr-space-key');
        Http::fake([
            'https://api.ocr.space/parse/image' => Http::response([
                'OCRExitCode' => 2,
                'IsErroredOnProcessing' => true,
                'ParsedResults' => [[
                    'FileParseExitCode' => '1',
                    'ParsedText' => 'First page only',
                ]],
            ]),
        ]);

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('provider_partial_result', $invoice->ocr_error_code);
        $this->assertSame('First page only', $invoice->ocr_response['ParsedResults'][0]['ParsedText']);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    public function test_ocr_space_missing_key_fails_without_sending_a_request(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.api_key', null);
        Http::preventStrayRequests();

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('configuration_missing', $invoice->ocr_error_code);
        $this->assertStringContainsString('fournisseur OCR actif', $invoice->ocr_error_message);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    public function test_ocr_space_rejects_files_over_its_configured_free_limit(): void
    {
        Storage::fake('local');
        $invoice = $this->queuedInvoice();
        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.api_key', 'test-ocr-space-key');
        config()->set('services.ocr_space.max_file_size_bytes', $invoice->size_bytes - 1);
        Http::preventStrayRequests();

        $this->runJob($invoice);

        $invoice->refresh();
        $this->assertSame('ocr_failed', $invoice->status);
        $this->assertSame('provider_file_too_large', $invoice->ocr_error_code);
        $this->assertSame(1, $invoice->ocr_attempts);
    }

    private function runJob(Invoice $invoice): void
    {
        $this->handleOcr(new ProcessInvoiceOcr($invoice->id, $invoice->company_id));
    }

    private function handleOcr(ProcessInvoiceOcr $job): void
    {
        $job->handle(
            app(OcrProvider::class),
            app(InvoiceDataCompletenessChecker::class),
            app(InvoiceDataPersistence::class),
            app(InvoiceTotalsConsistencyChecker::class),
            app(InvoiceProcessingErrorMessage::class),
        );
    }

    private function queuedInvoice(): Invoice
    {
        $company = Company::factory()->create();
        $path = "companies/{$company->id}/invoices/source.png";
        Storage::disk('local')->put($path, 'private-test-image');

        return $company->invoices()->create([
            'storage_disk' => 'local',
            'file_path' => $path,
            'original_filename' => 'source.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen('private-test-image'),
            'file_sha256' => hash('sha256', 'private-test-image'),
            'status' => 'ocr_queued',
        ]);
    }

    /** @return array<string, mixed> */
    private function validAnnotation(): array
    {
        return [
            'supplier_name' => 'Fournisseur Démo',
            'supplier_tax_identifier' => '1234567/A/B/000',
            'supplier_address' => 'Tunis',
            'supplier_phone' => null,
            'supplier_mobile' => null,
            'supplier_email' => null,
            'customer_name' => 'Client Démo',
            'customer_tax_identifier' => null,
            'customer_reference' => null,
            'customer_address' => null,
            'customer_phone' => null,
            'invoice_number' => 'FAC-2026-001',
            'purchase_order_reference' => null,
            'payment_method' => null,
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
            'total_discount_amount' => null,
            'vat_amount' => '19.000',
            'fodec_amount' => '0.000',
            'other_tax_amount' => '0.000',
            'stamp_amount' => '0.000',
            'withholding_rate' => null,
            'withholding_amount' => '0.000',
            'total_amount' => '119.000',
            'net_to_pay_amount' => null,
            'lines' => [[
                'reference' => 'ART-1',
                'description' => 'Prestation',
                'quantity' => '2.000',
                'unit_price' => '50.000',
                'discount_amount' => null,
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

    /** @param array<string, mixed> $annotation
     * @return array<string, mixed>
     */
    private function providerResponse(array $annotation): array
    {
        return [
            'pages' => [[
                'index' => 0,
                'markdown' => 'Facture FAC-2026-001',
            ]],
            'model' => 'mistral-ocr-latest',
            'document_annotation' => json_encode($annotation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'usage_info' => ['pages_processed' => 1],
        ];
    }
}
