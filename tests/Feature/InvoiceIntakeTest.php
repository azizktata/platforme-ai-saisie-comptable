<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_manager_can_upload_view_and_download_a_private_company_document(): void
    {
        Storage::fake('local');

        $company = Company::factory()->create();
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);
        $file = $this->fakePng('facture.png');

        $this->postUpload($manager, $company, $file)
            ->assertCreated()
            ->assertJsonPath('invoice.original_filename', 'facture.png')
            ->assertJsonPath('invoice.status', 'uploaded');

        /** @var Invoice $invoice */
        $invoice = $company->invoices()->sole();
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $invoice->file_sha256);
        $this->assertSame($manager->id, $invoice->uploaded_by);
        $this->assertSame('local', $invoice->storage_disk);
        Storage::disk('local')->assertExists($invoice->file_path);

        $this->actingAs($manager)
            ->get(route('companies.invoices.index', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Invoices/Index')
                ->where('company.id', $company->id)
                ->where('canUploadInvoices', true)
                ->where('invoices.total', 1)
                ->where('invoices.data.0.original_filename', 'facture.png')
                ->where('invoices.data.0.status', 'uploaded'))
            ->assertDontSee($invoice->file_path);

        $this->actingAs($manager)
            ->get(route('companies.invoices.download', [$company, $invoice]))
            ->assertOk()
            ->assertDownload('facture.png');
    }

    public function test_same_company_duplicate_requires_explicit_confirmation_before_storage(): void
    {
        Storage::fake('local');

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
        $this->assertCount(2, Storage::disk('local')->allFiles("companies/{$company->id}/invoices"));
    }

    public function test_invoice_pages_and_uploads_are_limited_by_company_access_and_role(): void
    {
        Storage::fake('local');

        $cabinet = Cabinet::factory()->create();
        $company = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $otherCompany = Company::factory()->create(['cabinet_id' => $cabinet->id]);
        $reader = $this->companyUser($company, User::COMPANY_ROLE_USER);
        $manager = $this->companyUser($company, User::COMPANY_ROLE_INVOICE_MANAGER);

        $this->actingAs($reader)
            ->get(route('companies.invoices.index', $company))
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

    public function test_an_invoice_identifier_from_another_company_cannot_be_downloaded_through_this_company_route(): void
    {
        Storage::fake('local');

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
