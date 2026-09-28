<?php

namespace App\Services;

use App\Models\AccountingEntry;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostDocumentToLedger
{
    /** @param array<string, mixed> $updates */
    public function handle(Document $document, array $updates = []): AccountingEntry
    {
        return DB::transaction(function () use ($document, $updates): AccountingEntry {
            $lockedDocument = Document::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            if ($lockedDocument->isPosted()) {
                return $lockedDocument->accountingEntry()->firstOrFail();
            }

            $lockedDocument->fill($updates);

            $data = Validator::make($lockedDocument->only([
                'supplier_name',
                'invoice_number',
                'invoice_date',
                'account_code',
                'description',
                'subtotal',
                'vat_amount',
                'total_amount',
                'currency',
            ]), [
                'supplier_name' => ['required', 'string'],
                'invoice_number' => ['required', 'string'],
                'invoice_date' => ['required', 'date'],
                'account_code' => ['required', 'string'],
                'description' => ['required', 'string'],
                'subtotal' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                'vat_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
                'total_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
                'currency' => ['required', 'in:EUR'],
            ])->validate();

            $expectedTotal = round((float) $data['subtotal'] + (float) $data['vat_amount'], 2);
            $actualTotal = round((float) $data['total_amount'], 2);

            if (abs($expectedTotal - $actualTotal) > 0.01) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Le montant TTC doit correspondre au montant HT plus la TVA.',
                ]);
            }

            $entry = $lockedDocument->accountingEntry()->create([
                'entry_date' => $data['invoice_date'],
                'description' => $data['description'],
                'supplier_name' => $data['supplier_name'],
                'invoice_number' => $data['invoice_number'],
                'account_code' => $data['account_code'],
                'subtotal' => $data['subtotal'],
                'vat_amount' => $data['vat_amount'],
                'total_amount' => $data['total_amount'],
                'currency' => $data['currency'],
            ]);

            $lockedDocument->update(['status' => Document::STATUS_POSTED]);

            return $entry;
        });
    }
}
