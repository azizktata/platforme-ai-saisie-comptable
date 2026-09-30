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
        $missing = [];

        // These two values are the minimum needed to create a useful accounting
        // draft. Other omissions remain visible review controls and must not
        // prevent the independent accounting-analysis stage from starting.
        $total = $invoiceData['total_amount'] ?? null;

        if (! is_string($total) || ! preg_match('/^-?\d{1,15}(?:\.\d{1,3})?$/', $total)) {
            $missing[] = 'missing_total_amount';
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

    /**
     * @param array<string, mixed> $invoiceData
     * @return list<string>
     */
    public function warnings(array $invoiceData): array
    {
        $warnings = [];

        foreach ([
            'supplier_name' => 'missing_supplier_name',
            'invoice_number' => 'missing_invoice_number',
            'invoice_date' => 'missing_invoice_date',
            'currency' => 'missing_currency',
        ] as $field => $warning) {
            $value = $invoiceData[$field] ?? null;

            if (! is_string($value) || trim($value) === '') {
                $warnings[] = $warning;
            }
        }

        return array_values(array_unique([...$warnings, ...$this->missingFields($invoiceData)]));
    }
}
