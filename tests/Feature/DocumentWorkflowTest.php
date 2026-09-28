<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invoice_upload_is_saved_for_review_in_the_current_users_workspace(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/documents', [
                'file' => UploadedFile::fake()->create('facture.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect('/documents');

        $document = Document::query()->firstOrFail();

        $this->assertSame($user->id, $document->user_id);
        $this->assertSame(Document::STATUS_NEEDS_REVIEW, $document->status);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_incomplete_review_information_can_be_saved_without_posting(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create([
            'original_filename' => 'facture-incomplete.pdf',
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);

        $this->actingAs($user)
            ->put("/documents/{$document->id}", ['supplier_name' => 'Fournisseur ajouté'])
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'supplier_name' => 'Fournisseur ajouté',
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);
        $this->assertDatabaseCount('accounting_entries', 0);
    }

    public function test_a_complete_document_can_be_posted_once(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create($this->reviewableInvoice());

        $this->actingAs($user)
            ->post("/documents/{$document->id}/post", $this->reviewableInvoice())
            ->assertRedirect();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_POSTED,
        ]);
        $this->assertDatabaseHas('accounting_entries', [
            'document_id' => $document->id,
            'account_code' => '606100',
            'total_amount' => '120.00',
        ]);

        $this->post("/documents/{$document->id}/post")->assertRedirect();
        $this->assertDatabaseCount('accounting_entries', 1);
    }

    public function test_document_cannot_be_posted_when_the_invoice_totals_do_not_balance(): void
    {
        $user = User::factory()->create();
        $document = $user->documents()->create(array_merge($this->reviewableInvoice(), [
            'total_amount' => 125.00,
        ]));

        $this->actingAs($user)
            ->from("/documents/{$document->id}")
            ->post("/documents/{$document->id}/post", array_merge($this->reviewableInvoice(), [
                'total_amount' => 125.00,
            ]))
            ->assertSessionHasErrors('total_amount');

        $this->assertDatabaseCount('accounting_entries', 0);
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);
    }

    public function test_a_user_cannot_view_another_users_document(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $document = $owner->documents()->create($this->reviewableInvoice());

        $this->actingAs($otherUser)
            ->get("/documents/{$document->id}")
            ->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function reviewableInvoice(): array
    {
        return [
            'original_filename' => 'facture.pdf',
            'mime_type' => 'application/pdf',
            'supplier_name' => 'Fournisseur Exemple',
            'invoice_number' => 'FAC-001',
            'invoice_date' => '2026-09-20',
            'currency' => 'EUR',
            'account_code' => '606100',
            'description' => 'Fournitures',
            'subtotal' => 100.00,
            'vat_amount' => 20.00,
            'total_amount' => 120.00,
            'status' => Document::STATUS_NEEDS_REVIEW,
        ];
    }
}
