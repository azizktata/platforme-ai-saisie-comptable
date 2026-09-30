<?php

namespace App\Services\Invoices;

use App\Models\AccountingProposal;
use App\Models\Company;
use App\Models\ThirdParty;

/**
 * Produces review confidence from application-side accounting evidence.
 * No score in this service is supplied by an AI provider.
 */
class InvoiceConfidenceEngine
{
    /**
     * @param array<string, mixed> $invoiceData
     * @param list<string> $totalsWarnings
     * @param list<string> $proposalWarnings
     * @return array<string, mixed>
     */
    public function assess(
        Company $company,
        array $invoiceData,
        ?AccountingProposal $proposal,
        array $totalsWarnings,
        array $proposalWarnings,
        bool $hasDuplicate,
    ): array {
        [$party, $supplier] = $this->supplierEvidence($company, $invoiceData);
        $amount = $this->amountEvidence($company, $invoiceData, $totalsWarnings);
        $vat = $this->vatEvidence($company, $invoiceData, $totalsWarnings);
        $type = $this->typeEvidence($company, $party, $proposal);
        $account = $this->accountEvidence($company, $party, $proposal);

        $indicators = [
            'supplier' => $supplier,
            'amount' => $amount,
            'vat' => $vat,
            'invoice_type' => $type,
            'account' => $account,
        ];

        $weights = [
            'supplier' => 20,
            'amount' => 20,
            'vat' => 15,
            'invoice_type' => 15,
            'account' => 30,
        ];
        $weightedScore = 0.0;
        $availableWeight = 0;

        foreach ($indicators as $key => $indicator) {
            if ($indicator['score'] === null) {
                continue;
            }

            $weightedScore += $indicator['score'] * $weights[$key];
            $availableWeight += $weights[$key];
        }

        $overall = $availableWeight === 0 ? null : round($weightedScore / $availableWeight, 4);
        $hardConflict = array_intersect($totalsWarnings, [
            'invoice_total_mismatch',
            'invoice_vat_mismatch',
            'invoice_net_to_pay_mismatch',
            'invoice_lines_subtotal_mismatch',
            'invoice_lines_vat_mismatch',
            'invoice_line_vat_rate_mismatch',
        ]) !== [] || array_intersect($proposalWarnings, [
            'proposal_unbalanced',
            'proposal_invoice_total_mismatch',
        ]) !== [];
        $invoiceCurrency = strtoupper((string) ($invoiceData['currency'] ?? ''));
        $companyCurrency = strtoupper((string) ($company->currency ?: 'TND'));
        $currencyConflict = $invoiceCurrency !== '' && $invoiceCurrency !== $companyCurrency;
        $supplierUnmatched = $party === null;
        $accountConflict = $account['score'] !== null && $account['score'] < 0.4;
        $missingReviewFields = [];

        foreach ([
            'invoice_number' => 'Le numéro de facture doit être vérifié avant validation.',
            'invoice_date' => 'La date de facture doit être vérifiée avant validation.',
            'currency' => 'La devise de la facture doit être confirmée avant validation.',
        ] as $field => $message) {
            if (! is_string($invoiceData[$field] ?? null) || trim($invoiceData[$field]) === '') {
                $missingReviewFields[] = $message;
            }
        }

        if ($missingReviewFields !== []) {
            $amount['evidence'] = [...$amount['evidence'], ...$missingReviewFields];
        }

        $blocking = $hardConflict || $currencyConflict || $supplierUnmatched || $accountConflict || $hasDuplicate || $missingReviewFields !== [];
        $quickValidationEvidenceAvailable = $proposal !== null
            && $supplier['score'] !== null
            && $amount['score'] !== null
            && $vat['score'] !== null
            && $type['score'] !== null
            && $account['score'] !== null;

        $route = $blocking || ($overall !== null && $overall < 0.6)
            ? 'control_queue'
            : ($quickValidationEvidenceAvailable && $overall !== null && $overall >= 0.9 && $supplier['score'] >= 0.95
                ? 'quick_validation'
                : 'accountant_review');

        return [
            'overall' => $overall,
            'route' => $route,
            'blocking' => $blocking,
            'indicators' => $indicators,
        ];
    }

    /**
     * @param array<string, mixed> $invoiceData
     * @return array{0: ?ThirdParty, 1: array{score: ?float, evidence: list<string>, matched: bool, party_id: ?int, party_name: ?string}}
     */
    private function supplierEvidence(Company $company, array $invoiceData): array
    {
        $name = $this->normalizeText($invoiceData['supplier_name'] ?? null);
        $taxId = $this->normalizeTaxId($invoiceData['supplier_tax_identifier'] ?? null);
        $parties = $company->thirdParties()
            ->where('is_active', true)
            ->whereIn('party_type', ['supplier', 'both'])
            ->get(['id', 'name', 'tax_identifier']);

        $taxMatches = $taxId === '' ? collect() : $parties->filter(
            fn (ThirdParty $candidate): bool => $this->normalizeTaxId($candidate->tax_identifier) === $taxId,
        );

        if ($taxMatches->count() > 1) {
            return [null, [
                'score' => 0.55,
                'evidence' => ['Plusieurs tiers actifs portent le même matricule fiscal. Le rapprochement est ambigu et nécessite un contrôle.'],
                'matched' => false,
                'party_id' => null,
                'party_name' => null,
            ]];
        }

        $exactTaxParty = $taxMatches->first();

        if ($exactTaxParty !== null) {
            $nameMatches = $name !== '' && $name === $this->normalizeText($exactTaxParty->name);

            return [$exactTaxParty, [
                'score' => $nameMatches ? 0.99 : 0.94,
                'evidence' => [$nameMatches
                    ? 'Matricule fiscal et nom exacts dans les tiers actifs de la société.'
                    : 'Matricule fiscal exact trouvé dans les tiers actifs de la société ; le nom diffère, vérification conseillée.'],
                'matched' => true,
                'party_id' => $exactTaxParty->id,
                'party_name' => $exactTaxParty->name,
            ]];
        }

        $nameMatches = $name === '' ? collect() : $parties->filter(
            fn (ThirdParty $candidate): bool => $name === $this->normalizeText($candidate->name),
        );
        $exactNameParty = $nameMatches->count() === 1 ? $nameMatches->first() : null;

        if ($nameMatches->count() > 1 && $taxId === '') {
            return [null, [
                'score' => 0.4,
                'evidence' => ['Plusieurs tiers actifs partagent ce nom et aucun matricule fiscal ne permet de les départager.'],
                'matched' => false,
                'party_id' => null,
                'party_name' => null,
            ]];
        }

        if ($exactNameParty !== null && $taxId === '') {
            return [$exactNameParty, [
                'score' => 0.78,
                'evidence' => ['Nom exact trouvé dans les tiers actifs ; aucun matricule fiscal extrait pour confirmer le rapprochement.'],
                'matched' => true,
                'party_id' => $exactNameParty->id,
                'party_name' => $exactNameParty->name,
            ]];
        }

        if ($exactNameParty !== null && $taxId !== '') {
            return [null, [
                'score' => 0.25,
                'evidence' => ['Le nom correspond à un tiers actif, mais le matricule fiscal extrait est différent. Rapprochement bloqué pour contrôle.'],
                'matched' => false,
                'party_id' => null,
                'party_name' => null,
            ]];
        }

        return [null, [
            'score' => $name === '' && $taxId === '' ? 0.1 : 0.2,
            'evidence' => [$parties->isEmpty()
                ? 'Aucun tiers fournisseur actif n’est configuré pour cette société.'
                : 'Aucun tiers fournisseur actif ne correspond au nom ou au matricule fiscal extrait.'],
            'matched' => false,
            'party_id' => null,
            'party_name' => null,
        ]];
    }

    /** @param array<string, mixed> $data @param list<string> $warnings @return array{score: float, evidence: list<string>} */
    private function amountEvidence(Company $company, array $data, array $warnings): array
    {
        if (! $this->hasDecimal($data['total_amount'] ?? null)) {
            return ['score' => 0.1, 'evidence' => ['Le TTC imprimé est absent ou invalide.']];
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $companyCurrency = strtoupper((string) ($company->currency ?: 'TND'));

        if ($currency !== '' && $currency !== $companyCurrency) {
            return ['score' => 0.2, 'evidence' => ["La facture est en {$currency} et la société en {$companyCurrency} ; aucun taux de conversion n’est configuré."]];
        }

        if (in_array('invoice_total_mismatch', $warnings, true)) {
            return ['score' => 0.15, 'evidence' => ['HT, taxes, timbre et TTC imprimé ne concordent pas.']];
        }

        if (in_array('invoice_totals_unverified', $warnings, true)) {
            return ['score' => 0.62, 'evidence' => ['Le TTC est présent, mais certains composants imprimés manquent pour vérifier la somme.']];
        }

        return ['score' => 0.98, 'evidence' => ['La somme des montants HT, taxes et timbre correspond au TTC imprimé.']];
    }

    /** @param array<string, mixed> $data @param list<string> $warnings @return array{score: ?float, evidence: list<string>} */
    private function vatEvidence(Company $company, array $data, array $warnings): array
    {
        $hasVat = $this->hasDecimal($data['vat_amount'] ?? null)
            || $this->hasDecimal($data['vat_rate'] ?? null);

        if (! $hasVat) {
            return ['score' => null, 'evidence' => ['Aucun montant ni taux de TVA extrait.']];
        }

        if (in_array('invoice_vat_mismatch', $warnings, true)) {
            return ['score' => 0.15, 'evidence' => ['Le montant de TVA ne correspond pas au taux et au montant HT selon le calcul serveur.']];
        }

        if (in_array('invoice_lines_vat_mismatch', $warnings, true) || in_array('invoice_line_vat_rate_mismatch', $warnings, true)) {
            return ['score' => 0.15, 'evidence' => ['Les taux ou montants de TVA par ligne ne concordent pas avec la TVA globale.']];
        }

        $configuredRates = array_map(static fn ($rate): string => rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.'), $company->vat_rates ?? []);
        $rate = $data['vat_rate'] ?? null;
        if ($configuredRates !== [] && is_string($rate) && $this->hasDecimal($rate)) {
            $normalizedRate = rtrim(rtrim(number_format((float) $rate, 3, '.', ''), '0'), '.');
            if (! in_array($normalizedRate, $configuredRates, true)) {
                return ['score' => 0.35, 'evidence' => ["Le taux extrait ({$normalizedRate} %) ne figure pas dans les taux configurés pour la société."]];
            }
        }

        if ($this->hasDecimal($data['subtotal'] ?? null)
            && $this->hasDecimal($data['vat_rate'] ?? null)
            && $this->hasDecimal($data['vat_amount'] ?? null)) {
            return ['score' => $configuredRates === [] ? 0.8 : 0.98, 'evidence' => [$configuredRates === []
                ? 'Le montant de TVA correspond au taux et à la base extraits ; aucun taux de référence n’est configuré pour cette société.'
                : 'Le montant de TVA correspond au taux et à la base extraits ; le taux figure dans la configuration de la société.']];
        }

        return ['score' => 0.5, 'evidence' => ['Les données TVA sont partielles ; aucun taux configuré de société n’est disponible pour comparaison.']];
    }

    /** @return array{score: ?float, evidence: list<string>} */
    private function typeEvidence(Company $company, ?ThirdParty $party, ?AccountingProposal $proposal): array
    {
        if ($proposal === null || ! is_string($proposal->invoice_type) || $proposal->invoice_type === '') {
            return ['score' => null, 'evidence' => ['Aucun type de facture proposé.']];
        }

        if ($party === null) {
            return ['score' => 0.45, 'evidence' => ['Le type proposé n’a pas d’historique fournisseur vérifiable dans la société.']];
        }

        $history = $company->accountingProposals()
            ->where('status', 'approved')
            ->whereNotNull('invoice_type')
            ->whereHas('journalEntry.lines', fn ($query) => $query->where('third_party_id', $party->id))
            ->get(['invoice_type']);

        if ($history->isEmpty()) {
            return ['score' => 0.55, 'evidence' => ['Aucun type validé antérieurement n’est disponible pour ce fournisseur.']];
        }

        $matching = $history->where('invoice_type', $proposal->invoice_type)->count();
        $ratio = $matching / $history->count();

        return [
            'score' => round(0.35 + (0.6 * $ratio), 4),
            'evidence' => [$matching > 0
                ? "{$matching} sur {$history->count()} proposition(s) validée(s) de ce fournisseur utilisent le même type."
                : 'Le type proposé diffère des types validés historiquement pour ce fournisseur.'],
        ];
    }

    /** @return array{score: ?float, evidence: list<string>} */
    private function accountEvidence(Company $company, ?ThirdParty $party, ?AccountingProposal $proposal): array
    {
        if ($proposal === null || $proposal->lines->isEmpty()) {
            return ['score' => null, 'evidence' => ['Aucun compte proposé à évaluer.']];
        }

        $debitLines = $proposal->lines->filter(fn ($line): bool => (float) $line->debit > 0);

        if ($debitLines->isEmpty()) {
            return ['score' => 0.2, 'evidence' => ['La proposition ne contient aucune ligne de débit exploitable.']];
        }

        $invalidJournal = $proposal->journal === null
            || ! $proposal->journal->is_active
            || (int) $proposal->journal->company_id !== (int) $company->id;
        $invalidAccount = $proposal->lines->contains(fn ($line): bool => $line->chartAccount === null
            || ! $line->chartAccount->is_active
            || (int) $line->chartAccount->company_id !== (int) $company->id);

        if ($invalidJournal || $invalidAccount) {
            return ['score' => 0.05, 'evidence' => ['Le journal ou au moins un compte proposé est absent, inactif ou rattaché à une autre société.']];
        }

        if ($party === null) {
            return ['score' => 0.5, 'evidence' => ['Les comptes existent dans le plan actif, mais aucun tiers rapproché ne permet d’évaluer les habitudes fournisseur.']];
        }

        $historicalEntries = $company->journalEntries()
            ->whereHas('lines', fn ($query) => $query->where('third_party_id', $party->id))
            ->with(['lines.chartAccount'])
            ->orderByDesc('entry_date')
            ->limit(100)
            ->get();
        $historicalDebitAccounts = $historicalEntries
            ->flatMap(fn ($entry) => $entry->lines)
            ->filter(fn ($line): bool => (float) $line->debit > 0)
            ->pluck('chart_account_id');

        if ($historicalDebitAccounts->isEmpty()) {
            return ['score' => 0.58, 'evidence' => ['Les comptes proposés sont actifs ; aucun historique de compte de charge lié à ce fournisseur n’est disponible.']];
        }

        $supported = $debitLines->filter(fn ($line): bool => $historicalDebitAccounts->contains($line->chart_account_id))->count();
        $ratio = $supported / $debitLines->count();

        return [
            'score' => round($ratio > 0 ? 0.65 + (0.33 * $ratio) : 0.3, 4),
            'evidence' => [$supported > 0
                ? "{$supported} sur {$debitLines->count()} compte(s) de débit apparaissent dans les écritures antérieures de ce fournisseur."
                : 'Aucun des comptes de charge proposés ne correspond aux comptes débiteurs historiquement utilisés pour ce fournisseur.'],
        ];
    }

    private function hasDecimal(mixed $value): bool
    {
        return is_string($value) && preg_match('/^-?\d{1,15}(?:\.\d{1,3})?$/', $value) === 1;
    }

    private function normalizeText(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_strtolower(preg_replace('/[^\pL\pN]+/u', '', trim($value)) ?? '');
    }

    private function normalizeTaxId(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return mb_strtoupper(preg_replace('/[^\pL\pN]+/u', '', trim($value)) ?? '');
    }
}
