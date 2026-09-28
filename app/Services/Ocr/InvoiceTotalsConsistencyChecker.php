<?php

namespace App\Services\Ocr;

class InvoiceTotalsConsistencyChecker
{
    private const AMOUNT_FIELDS = [
        'subtotal',
        'vat_amount',
        'fodec_amount',
        'other_tax_amount',
        'stamp_amount',
        'withholding_amount',
        'total_amount',
    ];

    /**
     * Report arithmetic inconsistencies without changing any extracted amount.
     * A missing component makes the check inconclusive rather than assuming zero.
     *
     * @param array<string, mixed> $invoiceData
     * @return list<string>
     */
    public function warnings(array $invoiceData): array
    {
        foreach (self::AMOUNT_FIELDS as $field) {
            if (! is_string($invoiceData[$field] ?? null)) {
                return ['invoice_totals_unverified'];
            }
        }

        $expected = $this->toMilli($invoiceData['subtotal'])
            + $this->toMilli($invoiceData['vat_amount'])
            + $this->toMilli($invoiceData['fodec_amount'])
            + $this->toMilli($invoiceData['other_tax_amount'])
            + $this->toMilli($invoiceData['stamp_amount'])
            - $this->toMilli($invoiceData['withholding_amount']);

        return $expected === $this->toMilli($invoiceData['total_amount'])
            ? []
            : ['invoice_total_mismatch'];
    }

    private function toMilli(string $amount): int
    {
        preg_match('/^(-?)(\d+)(?:\.(\d{1,3}))?$/', $amount, $matches);

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 3, '0');
        $value = ($whole * 1000) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$value : $value;
    }
}
