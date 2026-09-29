<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class InvoiceDataPersistence
{
    /**
     * Persist the validated contract both as normalized invoice columns/lines and as the exact structured JSON.
     * Caller owns the surrounding transaction when this runs as part of a queue job.
     *
     * @param array<string, mixed> $invoiceData
     */
    public function store(Invoice $invoice, array $invoiceData): void
    {
        $lines = $invoiceData['lines'];
        $invoiceAttributes = $invoiceData;
        unset($invoiceAttributes['lines']);

        $invoice->forceFill([
            ...$invoiceAttributes,
            'ocr_data' => $invoiceData,
        ])->save();

        $invoice->lines()->delete();

        foreach ($lines as $index => $line) {
            $invoice->lines()->create([
                ...$line,
                'line_number' => $index + 1,
            ]);
        }
    }
}
