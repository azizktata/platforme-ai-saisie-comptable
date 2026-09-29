<?php

namespace App\Services\Ocr;

use DateTimeImmutable;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class InvoiceOcrSchema
{
    private const INVOICE_TEXT_FIELDS = [
        'supplier_name' => ['max' => 255, 'description' => 'Fournisseur tel qu’imprimé sur la facture.'],
        'supplier_tax_identifier' => ['max' => 80, 'description' => 'Matricule fiscal ou identifiant du fournisseur.'],
        'supplier_address' => ['max' => 10000, 'description' => 'Adresse du fournisseur.'],
        'customer_name' => ['max' => 255, 'description' => 'Nom du client ou de la société facturée.'],
        'customer_tax_identifier' => ['max' => 80, 'description' => 'Identifiant fiscal du client facturé.'],
        'invoice_number' => ['max' => 120, 'description' => 'Numéro ou référence de facture.'],
        'purchase_order_reference' => ['max' => 120, 'description' => 'Référence de commande présente sur le document.'],
        'payment_terms' => ['max' => 10000, 'description' => 'Conditions ou échéancier de paiement.'],
        'bank_name' => ['max' => 255, 'description' => 'Nom de la banque indiqué sur la facture.'],
        'bank_account_reference' => ['max' => 190, 'description' => 'RIB, IBAN ou référence bancaire imprimée.'],
        'description' => ['max' => 10000, 'description' => 'Description générale de la facture ou de son objet.'],
    ];

    private const INVOICE_RATE_FIELDS = [
        'vat_rate' => 'Taux de TVA global uniquement si un taux unique est explicitement indiqué.',
        'fodec_rate' => 'Taux FODEC global uniquement si un taux unique est explicitement indiqué.',
    ];

    private const INVOICE_DECIMAL_FIELDS = [
        'subtotal' => 'Montant hors taxes avant taxes et retenues.',
        'vat_amount' => 'Montant total de TVA, tel qu’imprimé.',
        'fodec_amount' => 'Montant total FODEC, tel qu’imprimé.',
        'other_tax_amount' => 'Autres taxes distinctes du FODEC, si elles sont indiquées.',
        'stamp_amount' => 'Montant du timbre fiscal.',
        'withholding_amount' => 'Montant de la retenue à la source.',
        'total_amount' => 'Montant TTC ou total à payer, tel qu’imprimé.',
    ];

    private const LINE_TEXT_FIELDS = [
        'reference' => ['max' => 120, 'description' => 'Code ou référence de la ligne.'],
        'description' => ['max' => 10000, 'description' => 'Libellé de la ligne tel qu’imprimé.'],
    ];

    private const LINE_DECIMAL_FIELDS = [
        'quantity' => 'Quantité indiquée sur la ligne.',
        'unit_price' => 'Prix unitaire indiqué sur la ligne.',
        'subtotal' => 'Montant hors taxes de la ligne.',
        'vat_amount' => 'Montant de TVA de la ligne.',
        'fodec_amount' => 'Montant FODEC de la ligne.',
        'other_tax_amount' => 'Autre taxe de la ligne, si elle est distincte.',
        'total_amount' => 'Montant total de la ligne, tel qu’imprimé.',
    ];

    public function responseFormat(): array
    {
        $properties = [];

        foreach (self::INVOICE_TEXT_FIELDS as $field => $definition) {
            $properties[$field] = $this->nullableString($definition['description']);
        }

        $properties['invoice_date'] = $this->nullableString('Date de facture telle qu’imprimée, si elle est lisible.');
        $properties['due_date'] = $this->nullableString('Date d’échéance telle qu’imprimée, si elle est lisible.');
        $properties['currency'] = $this->nullableString('Devise ou code de devise tel qu’imprimé.');

        foreach (self::INVOICE_RATE_FIELDS as $field => $description) {
            $properties[$field] = $this->nullableRate($description);
        }

        foreach (self::INVOICE_DECIMAL_FIELDS as $field => $description) {
            $properties[$field] = $this->nullableDecimal($description);
        }

        $properties['withholding_rate'] = $this->nullableRate('Taux de retenue à la source en pourcentage.');
        $properties['lines'] = [
            'type' => 'array',
            'description' => 'Lignes détaillées effectivement visibles sur la facture, sans ligne comptable inventée.',
            'items' => [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => $this->lineProperties(),
                'required' => $this->lineFieldNames(),
            ],
        ];

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'supplier_invoice',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => $properties,
                    'required' => array_keys($properties),
                ],
            ],
        ];
    }

    public function annotationPrompt(): string
    {
        return <<<'PROMPT'
Extract only information explicitly visible on this supplier invoice. Return null for any missing, unreadable, or ambiguous value. Copy dates, currency labels, amounts, quantities, and rates as printed; Laravel will normalize and validate them. Do not infer, invent, calculate, sum, or reconcile any value. Preserve printed descriptions and references. Put FODEC in its dedicated fields and use other_tax only for a separate tax. Return an invoice-level VAT or FODEC rate only when one unique global rate is explicitly printed; when rates vary by line, return null at invoice level and preserve the individual line rates. Keep invoice-level totals distinct from line-level amounts. Do not create accounting accounts, journals, or accounting entries.
PROMPT;
    }

    /** @return array<string, mixed> */
    public function emptyAnnotation(): array
    {
        $annotation = array_fill_keys($this->invoiceFieldNames(), null);
        $annotation['lines'] = [];

        return $annotation;
    }

    /** @return array<string, mixed> */
    public function validate(array $data): array
    {
        if (array_is_list($data)) {
            throw new InvalidArgumentException('The structured invoice annotation must be a JSON object.');
        }

        $allowedFields = $this->invoiceFieldNames();
        $unexpectedFields = array_diff(array_keys($data), $allowedFields);

        if ($unexpectedFields !== []) {
            throw new InvalidArgumentException('The structured invoice annotation contains unsupported fields.');
        }

        $data = $this->normalizeAnnotation($data);

        $rules = [
            'invoice_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'due_date' => ['present', 'nullable', 'date_format:Y-m-d'],
            'currency' => ['present', 'nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'withholding_rate' => $this->rateRules(),
            'lines' => ['present', 'array', 'max:1000'],
            'lines.*' => ['required', 'array:'.implode(',', $this->lineFieldNames())],
        ];

        foreach (self::INVOICE_TEXT_FIELDS as $field => $definition) {
            $rules[$field] = ['present', 'nullable', 'string', 'max:'.$definition['max']];
        }

        foreach (self::INVOICE_RATE_FIELDS as $field => $description) {
            $rules[$field] = $this->rateRules();
        }

        foreach (self::INVOICE_DECIMAL_FIELDS as $field => $description) {
            $rules[$field] = $this->decimalRules();
        }

        foreach (self::LINE_TEXT_FIELDS as $field => $definition) {
            $rules["lines.*.{$field}"] = ['present', 'nullable', 'string', 'max:'.$definition['max']];
        }

        foreach (self::LINE_DECIMAL_FIELDS as $field => $description) {
            $rules["lines.*.{$field}"] = $this->decimalRules();
        }

        foreach (['vat_rate', 'fodec_rate'] as $field) {
            $rules["lines.*.{$field}"] = $this->rateRules();
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new InvalidArgumentException('The structured invoice annotation did not pass application validation.');
        }

        return $validator->validated();
    }

    /** @return array<string, mixed> */
    private function lineProperties(): array
    {
        $properties = [];

        foreach (self::LINE_TEXT_FIELDS as $field => $definition) {
            $properties[$field] = $this->nullableString($definition['description']);
        }

        foreach (self::LINE_DECIMAL_FIELDS as $field => $description) {
            $properties[$field] = $this->nullableDecimal($description);
        }

        $properties['vat_rate'] = $this->nullableRate('Taux de TVA de la ligne en pourcentage.');
        $properties['fodec_rate'] = $this->nullableRate('Taux FODEC de la ligne en pourcentage.');

        return $properties;
    }

    private function nullableString(string $description): array
    {
        return [
            'type' => ['string', 'null'],
            'description' => $description.' Use null if unavailable or uncertain.',
        ];
    }

    private function nullableDecimal(string $description): array
    {
        return [
            'type' => ['string', 'null'],
            'description' => $description.' Copy the printed value without converting separators or units; use null if unavailable.',
        ];
    }

    private function nullableRate(string $description): array
    {
        return [
            'type' => ['string', 'null'],
            'description' => $description.' Copy the printed rate, including its percent marker if present; use null if unavailable.',
        ];
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeAnnotation(array $data): array
    {
        foreach (['invoice_date', 'due_date'] as $field) {
            $data[$field] = $this->normalizeDate($data[$field] ?? null);
        }

        $data['currency'] = $this->normalizeCurrency($data['currency'] ?? null);

        foreach ([...array_keys(self::INVOICE_DECIMAL_FIELDS), ...array_keys(self::INVOICE_RATE_FIELDS), 'withholding_rate'] as $field) {
            $data[$field] = $this->normalizeDecimal($data[$field] ?? null);
        }

        if (is_array($data['lines'] ?? null)) {
            foreach ($data['lines'] as $index => $line) {
                if (! is_array($line)) {
                    continue;
                }

                foreach ([...array_keys(self::LINE_DECIMAL_FIELDS), 'vat_rate', 'fodec_rate'] as $field) {
                    $line[$field] = $this->normalizeDecimal($line[$field] ?? null);
                }

                $data['lines'][$index] = $line;
            }
        }

        return $data;
    }

    private function normalizeDate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        foreach (['!Y-m-d', '!Y/m/d', '!Y.m.d', '!d/m/Y', '!d-m-Y', '!d.m.Y', '!j/n/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();

            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        $compact = mb_strtoupper(preg_replace('/[^A-Za-z]/u', '', $value) ?? '');

        if (preg_match('/^[A-Z]{3}$/', $compact) === 1) {
            return $compact;
        }

        return match (mb_strtolower($value)) {
            'د.ت', 'د.ت.', 'دينار تونسي', 'دينار تونسيّ' => 'TND',
            default => match ($compact) {
                'DT', 'DNT', 'DINARTUNISIEN', 'DINARSTUNISIEN' => 'TND',
                'EURO', 'EUROS' => 'EUR',
                'US DOLLAR', 'USDOLLAR', 'US DOLLARS' => 'USD',
                'POUNDSTERLING', 'BRITISHPOUND' => 'GBP',
                default => null,
            },
        };
    }

    private function normalizeDecimal(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $raw = trim($value);

        if ($raw === '') {
            return null;
        }

        $isParenthesizedNegative = str_starts_with($raw, '(') && str_ends_with($raw, ')');

        if ($isParenthesizedNegative) {
            $raw = substr($raw, 1, -1);
        }

        $raw = str_replace(["\u{00A0}", "\u{202F}", ' ', "'", '’'], '', $raw);
        $numeric = preg_replace('/[^0-9,.+\-]/u', '', $raw) ?? '';

        if ($numeric === '' || preg_match('/\d/', $numeric) !== 1) {
            return null;
        }

        $negative = $isParenthesizedNegative || str_starts_with($numeric, '-');

        if (str_starts_with($numeric, '-') || str_starts_with($numeric, '+')) {
            $numeric = substr($numeric, 1);
        }

        if (str_contains($numeric, '+') || str_contains($numeric, '-')) {
            return $value;
        }

        $lastComma = strrpos($numeric, ',');
        $lastDot = strrpos($numeric, '.');
        $decimalSeparator = null;

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
        } elseif ($lastComma !== false || $lastDot !== false) {
            $separator = $lastComma !== false ? ',' : '.';
            $parts = explode($separator, $numeric);

            $allTrailingGroupsAreThousands = count($parts) > 2;

            foreach (array_slice($parts, 1) as $part) {
                $allTrailingGroupsAreThousands = $allTrailingGroupsAreThousands && strlen($part) === 3;
            }

            if (count($parts) === 2 || ! $allTrailingGroupsAreThousands) {
                $decimalSeparator = $separator;
            }
        }

        if ($decimalSeparator !== null) {
            $position = strrpos($numeric, $decimalSeparator);
            $whole = preg_replace('/[,.]/', '', substr($numeric, 0, $position)) ?? '';
            $fraction = preg_replace('/[,.]/', '', substr($numeric, $position + 1)) ?? '';
            $normalized = ($whole === '' ? '0' : $whole).($fraction === '' ? '' : '.'.$fraction);
        } else {
            $normalized = preg_replace('/[,.]/', '', $numeric) ?? '';
        }

        if ($negative && $normalized !== '0') {
            $normalized = '-'.$normalized;
        }

        return preg_match('/^-?\d{1,15}(?:\.\d{1,3})?$/', $normalized) === 1
            ? $normalized
            : $value;
    }

    private function decimalRules(): array
    {
        return ['present', 'nullable', 'string', 'regex:/^-?\d{1,15}(?:\.\d{1,3})?$/'];
    }

    private function rateRules(): array
    {
        return ['present', 'nullable', 'string', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(?:\.\d{1,3})?$/'];
    }

    private function invoiceFieldNames(): array
    {
        return [
            ...array_keys(self::INVOICE_TEXT_FIELDS),
            ...array_keys(self::INVOICE_DECIMAL_FIELDS),
            ...array_keys(self::INVOICE_RATE_FIELDS),
            'invoice_date',
            'due_date',
            'currency',
            'withholding_rate',
            'lines',
        ];
    }

    private function lineFieldNames(): array
    {
        return [
            ...array_keys(self::LINE_TEXT_FIELDS),
            ...array_keys(self::LINE_DECIMAL_FIELDS),
            'vat_rate',
            'fodec_rate',
        ];
    }
}
