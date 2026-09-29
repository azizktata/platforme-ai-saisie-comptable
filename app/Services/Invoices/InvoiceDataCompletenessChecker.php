<?php

namespace App\Services\Invoices;

class InvoiceDataCompletenessChecker
{
    /**
     * @param array<string, mixed> $invoiceData
     * @return list<string>
     */
    public function missingFields(array $invoiceData): array
    {
        $required = [
            'supplier_name' => 'missing_supplier_name',
            'invoice_number' => 'missing_invoice_number',
            'invoice_date' => 'missing_invoice_date',
            'currency' => 'missing_currency',
            'total_amount' => 'missing_total_amount',
        ];
        $missing = [];

        foreach ($required as $field => $warning) {
            $value = $invoiceData[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $missing[] = $warning;
            }
        }

        $hasDescription = is_string($invoiceData['description'] ?? null)
            && trim($invoiceData['description']) !== '';

        if (! $hasDescription) {
            foreach (($invoiceData['lines'] ?? []) as $line) {
                if (is_string($line['description'] ?? null) && trim($line['description']) !== '') {
                    $hasDescription = true;
                    break;
                }
            }
        }

        if (! $hasDescription) {
            $missing[] = 'missing_invoice_description';
        }

        return $missing;
    }
}
