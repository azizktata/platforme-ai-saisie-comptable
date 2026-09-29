<?php

namespace Tests\Feature;

use App\Jobs\ProcessInvoiceOcr;
use App\Models\Cabinet;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ocr.provider', 'ocr_space');
        config()->set('services.ocr_space.max_file_size_bytes', 1024 * 1024);
    }

    public function test_invoice_manager_can_upload_view_and_download_a_private_company_document(): void
    {
        Storage::fake('local');
        Queue::fake();

        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $file = $this->fakePng('facture.png');

        $this->postUpload($manager, $company, $file)
            ->assertCreated()
            ->assertJsonPath('invoice.original_filename', 'facture.png')
            ->assertJsonPath('invoice.status', 'ocr_queued');

        /** @var Invoice $invoice */
        $invoice = $company->invoices()->sole();
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $invoice->file_sha256);
        $this->assertSame($manager->id, $invoice->uploaded_by);
        $this->assertSame('local', $invoice->storage_disk);
        $this->assertSame('ocr_queued', $invoice->status);
        Storage::disk('local')->assertExists($invoice->file_path);
        Queue::assertPushedOn('ocr', ProcessInvoiceOcr::class, fn (ProcessInvoiceOcr $job): bool => $job->invoiceId === $invoice->id && $job->companyId === $company->id);
        Queue::assertPushed(ProcessInvoiceOcr::class, fn (ProcessInvoiceOcr $job): bool => $job->connection === 'database');

        $this->actingAs($manager)
            ->get(route('invoices.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('company.id', $company->id)
                ->where('canUploadInvoices', true)
                ->where('invoices.total', 1)
                ->where('invoices.data.0.original_filename', 'facture.png')
                ->where('invoices.data.0.status', 'ocr_queued'))
            ->assertDontSee($invoice->file_path);

        $this->actingAs($manager)
            ->get(route('companies.invoices.download', [$company, $invoice]))
            ->assertOk()
            ->assertDownload('facture.png');
    }

    public function test_ocr_space_free_uploads_are_limited_to_one_megabyte(): void
    {
        config()->set('services.ocr.provider', 'ocr_space');
        Storage::fake('local');

        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $file = UploadedFile::fake()->create('large.pdf', 1025, 'application/pdf');

        $this->postUpload($manager, $company, $file)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file')
            ->assertJsonPath('errors.file.0', 'Chaque fichier doit faire 1 Mo maximum.');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_same_company_duplicate_requires_explicit_confirmation_before_storage(): void
    {
        Storage::fake('local');
        Queue::fake();

        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $file = $this->fakePng('facture.png');

        $this->postUpload($manager, $company, $file)->assertCreated();

        $this->postUpload($manager, $company, $file)
            ->assertStatus(409)
            ->assertJsonPath('duplicate.filename', 'facture.png');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles("companies/{$company->id}/invoices"));

        $this->postUpload($manager, $company, $file, confirmDuplicate: true)->assertCreated();
        $this->assertDatabaseCount('invoices', 2);
        Queue::assertPushed(ProcessInvoiceOcr::class, 2);
        $this->assertCount(2, Storage::disk('local')->allFiles("companies/{$company->id}/invoices"));
    }

    public function test_invoice_pages_and_uploads_are_limited_by_company_access_and_role(): void
    {
        Storage::fake('local');
        Queue::fake();

        $cabinet = Cabinet::factory()->create();
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $otherCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $reader = $this->companyUser($company, User::COMPANY_ROLE_USER);
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);

        $this->actingAs($reader)
            ->get(route('invoices.index', ['company_id' => $company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('canUploadInvoices', false));

        $this->postUpload($reader, $company, $this->fakePng('refusee.png'))
            ->assertForbidden();
        $this->postUpload($manager, $otherCompany, $this->fakePng('autre.png'))
            ->assertForbidden();

        $this->actingAs($reader)
            ->get(route('companies.invoices.index', $otherCompany))
            ->assertForbidden();

        $this->postUpload($manager, $company, UploadedFile::fake()->create('note.txt', 5, 'text/plain'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_authorized_manager_can_queue_ocr_for_failed_and_legacy_uploaded_invoices(): void
    {
        Storage::fake('local');
        Queue::fake();

        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $reader = $this->companyUser($company, User::COMPANY_ROLE_USER);
        $failedInvoice = $company->invoices()->create([
            'uploaded_by' => $manager->id,
            'storage_disk' => 'local',
            'file_path' => "companies/{$company->id}/invoices/failed.pdf",
            'original_filename' => 'failed.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'file_sha256' => hash('sha256', 'failed'),
            'status' => 'ocr_failed',
            'ocr_attempts' => 2,
            'ocr_error_code' => 'provider_unavailable',
        ]);

        $this->actingAs($reader)
            ->post(route('companies.invoices.ocr.retry', [$company, $failedInvoice]))
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('companies.invoices.ocr.retry', [$company, $failedInvoice]))
            ->assertRedirect(route('invoices.index', ['company_id' => $company->id]));

        $failedInvoice->refresh();
        $this->assertSame('ocr_queued', $failedInvoice->status);
        $this->assertNull($failedInvoice->ocr_error_code);
        Queue::assertPushedOn('ocr', ProcessInvoiceOcr::class, fn (ProcessInvoiceOcr $job): bool => $job->invoiceId === $failedInvoice->id);

        $legacyInvoice = $company->invoices()->create([
            'uploaded_by' => $manager->id,
            'storage_disk' => 'local',
            'file_path' => "companies/{$company->id}/invoices/legacy.pdf",
            'original_filename' => 'legacy.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'file_sha256' => hash('sha256', 'legacy'),
            'status' => 'uploaded',
        ]);

        $this->actingAs($manager)
            ->post(route('companies.invoices.ocr.retry', [$company, $legacyInvoice]))
            ->assertRedirect(route('invoices.index', ['company_id' => $company->id]));

        $this->assertSame('ocr_queued', $legacyInvoice->fresh()->status);
        Queue::assertPushed(ProcessInvoiceOcr::class, 2);
    }

    public function test_an_invoice_identifier_from_another_company_cannot_be_downloaded_through_this_company_route(): void
    {
        Storage::fake('local');
        Queue::fake();

        $cabinet = Cabinet::factory()->create();
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $otherCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $otherManager = $this->companyUser($otherCompany, User::COMPANY_ROLE_INVOICE_MANAGER);
        $file = $this->fakePng('autre-societe.png');

        $this->postUpload($otherManager, $otherCompany, $file)->assertCreated();
        $invoice = $otherCompany->invoices()->sole();

        $this->actingAs($manager)
            ->get(route('companies.invoices.download', [$company, $invoice]))
            ->assertNotFound();
    }

    public function test_duplicate_detection_is_scoped_to_the_company(): void
    {
        Storage::fake('local');
        Queue::fake();

        $cabinet = Cabinet::factory()->create();
        $firstCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $secondCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $firstManager = $this->companyUser($firstCompany, User::COMPANY_ROLE_INVOICE_MANAGER);
        $secondManager = $this->companyUser($secondCompany, User::COMPANY_ROLE_INVOICE_MANAGER);
        $sameFileContents = $this->fakePng('facture.png');

        $this->postUpload($firstManager, $firstCompany, $sameFileContents)->assertCreated();
        $this->postUpload($secondManager, $secondCompany, $sameFileContents)->assertCreated();

        $this->assertDatabaseCount('invoices', 2);
    }

    public function test_global_invoice_workspace_scopes_the_selected_company_and_preserves_company_history(): void
    {
        $cabinet = Cabinet::factory()->create();
        $admin = User::factory()->cabinetAdmin()->create(['cabinet_id' => $cabinet->id]);
        $firstCompany = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Alpha société']);
        $secondCompany = Company::factory()->create(['cabinet_id' => $cabinet->id, 'name' => 'Beta société']);
        $firstInvoice = $this->createInvoice($firstCompany, 'premiere.pdf', 'ocr_completed');
        $firstInvoice->forceFill([
            'ocr_reviewed_at' => now(),
            'ocr_reviewed_by' => $admin->id,
        ])->save();
        $expectedReviewedAt = $firstInvoice->fresh()->ocr_reviewed_at->toIso8601String();
        $this->createInvoice($secondCompany, 'seconde.pdf');

        $this->actingAs($admin)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('mode', 'workspace')
                ->where('ocrProvider', 'ocr_space')
                ->where('maxUploadFileSizeBytes', 1024 * 1024)
                ->where('company.id', $firstCompany->id)
                ->where('invoices.total', 1)
                ->where('invoices.data.0.id', $firstInvoice->id)
                ->where('canUploadInvoices', true)
                ->where('canReviewInvoices', true)
                ->has('companies', 2));

        $this->actingAs($admin)
            ->get(route('invoices.index', ['company_id' => $firstCompany->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'workspace')
                ->where('ocrProvider', 'ocr_space')
                ->where('maxUploadFileSizeBytes', 1024 * 1024)
                ->where('company.id', $firstCompany->id)
                ->where('canUploadInvoices', true)
                ->where('canReviewInvoices', true)
                ->where('invoices.total', 1)
                ->where('invoices.data.0.id', $firstInvoice->id)
                ->where('invoices.data.0.ocr_reviewed_at', $expectedReviewedAt));

        $this->actingAs($admin)
            ->get(route('companies.invoices.index', $firstCompany))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'history')
                ->where('company.id', $firstCompany->id)
                ->where('invoices.total', 1));
    }

    public function test_global_invoice_workspace_only_offers_and_serves_companies_the_user_can_access(): void
    {
        $cabinet = Cabinet::factory()->create();
        $user = User::factory()->create(['cabinet_id' => $cabinet->id]);
        $visibleCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $hiddenCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $foreignCompany = Company::factory()->create();
        $user->companies()->attach($visibleCompany, ['role' => User::COMPANY_ROLE_USER]);
        $user->companies()->attach($foreignCompany, ['role' => User::COMPANY_ROLE_USER]);
        $this->createInvoice($visibleCompany, 'visible.pdf');
        $this->createInvoice($hiddenCompany, 'hidden.pdf');
        $this->createInvoice($foreignCompany, 'foreign.pdf');

        $this->actingAs($user)
            ->get(route('invoices.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 1)
                ->where('companies.0.id', $visibleCompany->id)
                ->where('company.id', $visibleCompany->id)
                ->where('invoices.total', 1)
                ->where('canUploadInvoices', false));

        $this->actingAs($user)
            ->get(route('invoices.index', ['company_id' => $hiddenCompany->id]))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('invoices.index', ['company_id' => $foreignCompany->id]))
            ->assertNotFound();
    }

    public function test_invoice_manager_can_bulk_mark_only_completed_company_extractions_as_reviewed(): void
    {
        $cabinet = Cabinet::factory()->create();
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $otherCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $reader = $this->companyUser($company, User::COMPANY_ROLE_USER);
        $completed = $this->createInvoice($company, 'completed.pdf', 'ocr_completed');
        $anotherCompleted = $this->createInvoice($company, 'another-completed.pdf', 'ocr_completed');
        $pending = $this->createInvoice($company, 'pending.pdf', 'ocr_queued');
        $foreign = $this->createInvoice($otherCompany, 'foreign.pdf', 'ocr_completed');

        $this->actingAs($reader)
            ->post(route('companies.invoices.bulk-review', $company), ['invoice_ids' => [$completed->id]])
            ->assertForbidden();

        $this->actingAs($manager)
            ->post(route('companies.invoices.bulk-review', $company), ['invoice_ids' => [$completed->id, $foreign->id]])
            ->assertSessionHasErrors('invoice_ids');

        $this->actingAs($manager)
            ->post(route('companies.invoices.bulk-review', $company), ['invoice_ids' => [$pending->id]])
            ->assertSessionHasErrors('invoice_ids');

        $this->assertNull($completed->fresh()->ocr_reviewed_at);
        $this->assertNull($anotherCompleted->fresh()->ocr_reviewed_at);

        $this->actingAs($manager)
            ->post(route('companies.invoices.bulk-review', $company), ['invoice_ids' => [$completed->id, $anotherCompleted->id]])
            ->assertRedirect(route('invoices.index', ['company_id' => $company->id]));

        foreach ([$completed, $anotherCompleted] as $invoice) {
            $this->assertNotNull($invoice->fresh()->ocr_reviewed_at);
            $this->assertSame($manager->id, $invoice->fresh()->ocr_reviewed_by);
        }

    }

    private function createInvoice(Company $company, string $filename, string $status = 'uploaded'): Invoice
    {
        return $company->invoices()->create([
            'storage_disk' => 'local',
            'file_path' => "companies/{$company->id}/invoices/{$filename}",
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'size_bytes' => 12,
            'file_sha256' => hash('sha256', $company->id.'-'.$filename),
            'status' => $status,
            'ocr_warnings' => [],
        ]);
    }

    private function postUpload(User $user, Company $company, UploadedFile $file, bool $confirmDuplicate = false): TestResponse
    {
        $data = ['file' => $file];

        if ($confirmDuplicate) {
            $data['confirm_duplicate'] = true;
        }

        return $this->actingAs($user)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('companies.invoices.upload', $company), $data);
    }

    private function fakePng(string $name): UploadedFile
    {
        $content = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if (! is_string($content)) {
            throw new \RuntimeException('Could not create the PNG upload fixture.');
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function companyUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['cabinet_id' => $company->cabinet_id]);
        $user->companies()->attach($company, ['role' => $role]);

        return $user;
    }
}
