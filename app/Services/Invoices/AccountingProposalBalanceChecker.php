<?php

namespace App\Services\Invoices;

use App\Models\Invoice;

class AccountingProposalBalanceChecker
{
    /**
     * @param iterable<array<string, mixed>|object> $lines
     * @return list<string>
     */
    public function warnings(Invoice $invoice, iterable $lines): array
    {
        $debit = 0;
        $credit = 0;
        $count = 0;
        $lowConfidence = false;
        $supplierAssigned = false;

        foreach ($lines as $line) {
            $count++;
            $lineDebit = is_array($line) ? ($line['debit'] ?? '0') : ($line->debit ?? '0');
            $lineCredit = is_array($line) ? ($line['credit'] ?? '0') : ($line->credit ?? '0');
            $lineThirdParty = is_array($line) ? ($line['third_party_id'] ?? null) : ($line->third_party_id ?? null);
            $lineConfidence = is_array($line) ? ($line['confidence'] ?? null) : ($line->confidence ?? null);
            $debit += $this->toMilli((string) $lineDebit);
            $credit += $this->toMilli((string) $lineCredit);
            $supplierAssigned = $supplierAssigned || $lineThirdParty !== null;
            $lowConfidence = $lowConfidence || ($lineConfidence !== null && (float) $lineConfidence < 0.6);
        }

        $warnings = [];

        if ($count < 2 || $debit === 0 || $credit === 0 || $debit !== $credit) {
            $warnings[] = 'proposal_unbalanced';
        }

        if (is_string($invoice->total_amount) && trim($invoice->total_amount) !== '') {
            $expected = $this->toMilli($invoice->total_amount);

            if ($debit > 0 && $debit !== $expected) {
                $warnings[] = 'proposal_invoice_total_mismatch';
            }
        }

        if (! $supplierAssigned) {
            $warnings[] = 'supplier_not_linked';
        }

        if ($lowConfidence) {
            $warnings[] = 'proposal_low_confidence';
        }

        if ($invoice->withholding_amount === null) {
            $warnings[] = 'withholding_not_verified';
        }

        return array_values(array_unique($warnings));
    }

    /**
     * @param iterable<string> $warnings
     */
    public function hasBlockingWarnings(iterable $warnings): bool
    {
        return collect($warnings)->contains(fn (string $warning): bool => in_array($warning, [
            'proposal_unbalanced',
            'proposal_invoice_total_mismatch',
        ], true));
    }

    public function toMilli(string $amount): int
    {
        if (! preg_match('/^(-?)(\d{1,15})(?:\.(\d{1,3}))?$/', $amount, $matches)) {
            return 0;
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 3, '0');
        $value = ($whole * 1000) + $fraction;

        return ($matches[1] ?? '') === '-' ? -$value : $value;
    }
}
