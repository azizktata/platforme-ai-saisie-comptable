<?php

namespace App\Services\Ocr;

class InvoiceTotalsConsistencyChecker
{
    private const GROSS_AMOUNT_FIELDS = [
        'subtotal',
        'vat_amount',
        'fodec_amount',
        'other_tax_amount',
        'stamp_amount',
        'total_amount',
    ];

    /**
     * Check printed gross TTC independently from withholding. Missing values stay inconclusive;
     * this service reports discrepancies and never changes extracted invoice amounts.
     *
     * @param array<string, mixed> $invoiceData
     * @return list<string>
     */
    public function warnings(array $invoiceData): array
    {
        $warnings = [];
        $hasGrossAmounts = true;

        foreach (self::GROSS_AMOUNT_FIELDS as $field) {
            if (! is_string($invoiceData[$field] ?? null) || ! $this->isDecimal($invoiceData[$field])) {
                $hasGrossAmounts = false;
                break;
            }
        }

        if (! $hasGrossAmounts) {
            $warnings[] = 'invoice_totals_unverified';
        } else {
            $expectedGross = $this->toMilli($invoiceData['subtotal'])
                + $this->toMilli($invoiceData['vat_amount'])
                + $this->toMilli($invoiceData['fodec_amount'])
                + $this->toMilli($invoiceData['other_tax_amount'])
                + $this->toMilli($invoiceData['stamp_amount']);

            if ($expectedGross !== $this->toMilli($invoiceData['total_amount'])) {
                $warnings[] = 'invoice_total_mismatch';
            }
        }

        if (is_string($invoiceData['subtotal'] ?? null)
            && $this->isDecimal($invoiceData['subtotal'])
            && is_string($invoiceData['vat_rate'] ?? null)
            && $this->isDecimal($invoiceData['vat_rate'])
            && is_string($invoiceData['vat_amount'] ?? null)
            && $this->isDecimal($invoiceData['vat_amount'])
            && $this->percentageOf($this->toMilli($invoiceData['subtotal']), $this->toMilli($invoiceData['vat_rate']))
                !== $this->toMilli($invoiceData['vat_amount'])) {
            $warnings[] = 'invoice_vat_mismatch';
        }

        if (is_string($invoiceData['total_amount'] ?? null)
            && $this->isDecimal($invoiceData['total_amount'])
            && is_string($invoiceData['withholding_amount'] ?? null)
            && $this->isDecimal($invoiceData['withholding_amount'])
            && is_string($invoiceData['net_to_pay_amount'] ?? null)
            && $this->isDecimal($invoiceData['net_to_pay_amount'])
            && $this->toMilli($invoiceData['total_amount']) - $this->toMilli($invoiceData['withholding_amount'])
                !== $this->toMilli($invoiceData['net_to_pay_amount'])) {
            $warnings[] = 'invoice_net_to_pay_mismatch';
        }

        return array_values(array_unique($warnings));
    }

    public function hasBlockingWarnings(iterable $warnings): bool
    {
        foreach ($warnings as $warning) {
            if (in_array($warning, [
                'invoice_total_mismatch',
                'invoice_vat_mismatch',
                'invoice_net_to_pay_mismatch',
            ], true)) {
                return true;
            }
        }

        return false;
    }

    private function isDecimal(string $value): bool
    {
        return preg_match('/^-?\d{1,15}(?:\.\d{1,3})?$/', $value) === 1;
    }

    private function toMilli(string $amount): int
    {
        preg_match('/^(-?)(\d{1,15})(?:\.(\d{1,3}))?$/', $amount, $matches);

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 3, '0');
        $value = ($whole * 1000) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$value : $value;
    }

    private function percentageOf(int $amountMilli, int $rateMilli): int
    {
        $sign = $amountMilli < 0 ? -1 : 1;
        $absoluteAmount = abs($amountMilli);
        $denominator = 100_000;
        $whole = intdiv($absoluteAmount, $denominator) * $rateMilli;
        $remainder = ($absoluteAmount % $denominator) * $rateMilli;

        return $sign * ($whole + intdiv($remainder + intdiv($denominator, 2), $denominator));
    }
}
