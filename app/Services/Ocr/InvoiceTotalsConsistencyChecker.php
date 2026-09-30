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
     * A missing withholding is conclusive when the printed net payable equals gross TTC,
     * because that establishes that no withholding was deducted.
     *
     * @param array<string, mixed> $invoiceData
     */
    public function canVerifyNetToPay(array $invoiceData): bool
    {
        if (! is_string($invoiceData['total_amount'] ?? null)
            || ! $this->isDecimal($invoiceData['total_amount'])
            || ! is_string($invoiceData['net_to_pay_amount'] ?? null)
            || ! $this->isDecimal($invoiceData['net_to_pay_amount'])) {
            return false;
        }

        if (is_string($invoiceData['withholding_amount'] ?? null) && $this->isDecimal($invoiceData['withholding_amount'])) {
            return true;
        }

        return $this->toMilli($invoiceData['total_amount']) === $this->toMilli($invoiceData['net_to_pay_amount']);
    }

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

        // A plausible invoice-level total must also agree with the itemized
        // amounts when every line subtotal was extracted. This catches model
        // errors that can otherwise look valid in isolation (for example, a
        // grouped amount misread as 1,000,000 instead of 1,000).
        $lines = $invoiceData['lines'] ?? null;
        if (is_array($lines) && $lines !== []) {
            $lineSubtotals = array_map(static fn ($line): mixed => is_array($line) ? ($line['subtotal'] ?? null) : null, $lines);
            if (count(array_filter($lineSubtotals, fn ($amount): bool => is_string($amount) && $this->isDecimal($amount))) === count($lines)
                && is_string($invoiceData['subtotal'] ?? null)
                && $this->isDecimal($invoiceData['subtotal'])) {
                $lineSubtotalMilli = array_sum(array_map(fn (string $amount): int => $this->toMilli($amount), $lineSubtotals));
                if ($lineSubtotalMilli !== $this->toMilli($invoiceData['subtotal'])) {
                    $warnings[] = 'invoice_lines_subtotal_mismatch';
                }
            }

            $lineVatAmounts = array_map(static fn ($line): mixed => is_array($line) ? ($line['vat_amount'] ?? null) : null, $lines);
            if (count(array_filter($lineVatAmounts, fn ($amount): bool => is_string($amount) && $this->isDecimal($amount))) === count($lines)
                && is_string($invoiceData['vat_amount'] ?? null)
                && $this->isDecimal($invoiceData['vat_amount'])) {
                $lineVatMilli = array_sum(array_map(fn (string $amount): int => $this->toMilli($amount), $lineVatAmounts));
                if ($lineVatMilli !== $this->toMilli($invoiceData['vat_amount'])) {
                    $warnings[] = 'invoice_lines_vat_mismatch';
                }
            }

            foreach ($lines as $line) {
                if (! is_array($line)
                    || ! is_string($line['subtotal'] ?? null)
                    || ! $this->isDecimal($line['subtotal'])
                    || ! is_string($line['vat_rate'] ?? null)
                    || ! $this->isDecimal($line['vat_rate'])
                    || ! is_string($line['vat_amount'] ?? null)
                    || ! $this->isDecimal($line['vat_amount'])) {
                    continue;
                }

                if ($this->percentageOf($this->toMilli($line['subtotal']), $this->toMilli($line['vat_rate']))
                    !== $this->toMilli($line['vat_amount'])) {
                    $warnings[] = 'invoice_line_vat_rate_mismatch';
                    break;
                }
            }
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
                'invoice_lines_subtotal_mismatch',
                'invoice_lines_vat_mismatch',
                'invoice_line_vat_rate_mismatch',
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
