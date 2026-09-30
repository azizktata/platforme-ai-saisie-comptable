# Extraction OpenRouter et propositions comptables — Phase 5

## Configuration

OpenRouter est appelé côté serveur pour structurer le texte OCR.space puis proposer une écriture comptable. Configurez dans `.env` :

```dotenv
OPENROUTER_API_KEY=...
OPENROUTER_MODEL=openrouter/free
INVOICE_EXTRACTION_PROVIDER=openrouter
OPENROUTER_EXTRACTION_MODEL=qwen/qwen-2.5-7b-instruct:free
OPENROUTER_EXTRACTION_MAX_TOKENS=2500
OPENROUTER_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_TIMEOUT=120
OPENROUTER_MAX_OCR_CHARS=100000
OPENROUTER_SITE_URL=
OPENROUTER_APP_NAME=ComptaFlow
OPENROUTER_CA_BUNDLE=
```

L’extraction du texte OCR utilise séparément `INVOICE_EXTRACTION_PROVIDER` et `OPENROUTER_EXTRACTION_MODEL`, par défaut `qwen/qwen-2.5-7b-instruct:free`. La requête est JSON-only avec un JSON Schema strict ; `InvoiceOcrSchema` normalise et valide en Laravel et aucune sortie d’explication, calcul ou décision comptable n’est admise. L’analyse comptable utilise indépendamment `OPENROUTER_MODEL=openrouter/free`, qui route dynamiquement vers un modèle gratuit compatible avec la sortie structurée ; modèle retourné, usage et réponse complète sont conservés pour audit. Gardez la vérification TLS activée et ne placez jamais la clé dans le navigateur ou les logs.

Les jobs utilisent la file `ocr` :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

## Architecture

- `Services/OpenRouter/OpenRouterClient.php` : endpoint Chat Completions, clés/timeout/CA serveur, JSON strict, erreurs HTTP/TLS/JSON et modèle réel retourné.
- `Services/Ocr/InvoiceOcrSchema.php` et `Services/Invoices/InvoiceDataExtractionService.php` : schéma strict et extraction du texte OCR non fiable ; aucun calcul ou compte comptable n’est produit à cette étape.
- `InvoiceDataCompletenessChecker.php` : bloque l’analyse tant que fournisseur, numéro, date, devise, total ou libellé/ligne nécessaire manque.
- `InvoiceDataPersistence.php` : transaction sur champs facture et lignes, conserve également le contrat JSON courant.
- `AccountingProposalService.php` : construit un contexte à partir des référentiels actifs, du profil de la société et de 20 écritures récentes de cette même société ; les codes de comptes/journaux et le type de facture sont limités par le schéma.
- `InvoiceType` : enum des catégories comptables persistées (frais bancaires/généraux, eau, électricité, fournitures, honoraires, immobilisation, informatique, location, logiciels, maintenance, marchandises, matières premières, publicité, sous-traitance, télécommunications et transport).
- `AccountingProposalBalanceChecker.php` : contrôles déterministes en millimes pour équilibre et rapprochement au TTC brut ; la retenue ne majore pas le débit attendu.
- `ExtractInvoiceData` et `AnalyzeAccountingProposal` : jobs distincts, uniques par société/facture, trois tentatives et états d’échec propres à chaque étape.
- `AccountingProposalController.php` : correction, régénération gardée, validation des références société, rejet, approbation explicite, création de l’écriture et export CSV interne.

### Audit et modèle de données

- `invoices.ocr_response` et `invoices.ocr_text` : réponse OCR et transcription brute.
- `invoices.extraction_response`, `extraction_model`, `extraction_usage` : structuration OCR par OpenRouter, distincte de la réponse OCR.
- `invoices.ocr_data`, colonnes normalisées et `invoice_lines` : extraction structurée actuellement utilisée. Les corrections humaines sont horodatées par `extraction_corrected_at` / `extraction_corrected_by` ; la réponse IA initiale reste conservée.
- `accounting_proposals` / `accounting_proposal_lines` : chaque version conserve le type de facture, la proposition, le modèle OpenRouter, la réponse brute, l’usage, les avertissements, les lignes et la trace de revue. `invoice_type` est nullable pour les versions antérieures ; une catégorie est requise avant nouvelle validation. Les corrections sont horodatées (`modified_by`, `modified_at`). Une correction/régénération crée une nouvelle proposition sans écraser les réponses précédentes.
- `journal_entries.invoice_id` relie l’écriture finale à la facture et empêche une seconde écriture liée à cette facture.
- `invoices.accounting_exported_at` et `accounting_exported_by` auditent la dernière génération serveur du CSV de l’écriture finalisée. L’état `accounting_exported` signifie que le CSV interne a été généré ; il ne confirme aucune synchronisation externe.

### Routes principales

- `GET /companies/{company}/invoices/{invoice}` : page dédiée, original à gauche et workspace IA/comptabilité à droite.
- `GET /companies/{company}/invoices/{invoice}/details` : données privées chargées par le workspace et scoppées à la société.
- `POST .../extraction/retry` : extraction depuis le texte OCR enregistré, sans refaire OCR.
- `PUT .../extraction` : correction facture par un gestionnaire ; une extraction complète relance automatiquement l’analyse.
- `PUT .../proposal` : correction du type de facture, des références et des montants d’une proposition prête.
- `POST .../proposal/regenerate` : régénération gardée d’un brouillon prêt/rejeté ou d’une analyse échouée ; les versions d’audit sont conservées.
- `POST .../proposal/reject` : rejet humain sans écriture.
- `POST .../proposal/approve` : contrôles serveur puis création explicite d’une écriture comptable liée.
- `POST .../proposal/export-csv` : CSV du brouillon prêt ou de l’écriture validée/exportée.

Les policies/Form Requests requièrent un administrateur de cabinet ou un gestionnaire affecté. Comptes, journaux, tiers et axes sont revalidés contre la société et leur état actif ; aucun ID fourni par le navigateur ou le modèle n’est accepté sans vérification.

## Parcours de revue

`ocr_queued` → `ocr_processing` → `data_extraction` → `accounting_analysis` → `proposal_ready` → `accounting_validated` / `proposal_rejected` → éventuellement `accounting_exported`.

Le workspace affiche une confiance globale et des barres de confiance par dimension uniquement si l’API fournit des scores réels. À ce jour, les scores fournisseur/montants/TVA/type ne sont pas fournis et restent `N/D` ; la confiance comptable affichée est la moyenne des confiances de ligne. Le gestionnaire choisit/enregistre le type, modifie la facture, le journal, les comptes, tiers, analytique, libellés et montants, ajoute/supprime des lignes, consulte les totaux débit/crédit, régénère une proposition éligible, exporte un brouillon, rejette, valide, ou valide puis exporte.

Le recalcul de TTC/net est un bouton explicite, déterministe et local (sans appel LLM). Il utilise le HT imprimé (sans déduire une seconde fois une remise conservée séparément) + TVA + FODEC + autres taxes + timbre pour le TTC brut ; la retenue n’est pas soustraite du TTC mais du net à payer. Les composants vides demandent confirmation avant d’être traités comme zéro. Avant approbation, le serveur revérifie complétude, cohérence HT/TVA/TTC/net, équilibre, rapprochement des débits au TTC brut, catégorie de facture, références actives et devise. Un écart de devise bloque en l’absence de conversion. L’empreinte SHA-256 identique déclenche un avertissement ; le contrôle des doublons par numéro et l’exercice fiscal sont explicitement indisponibles, pas réussis.

Le CSV séparé par point-virgule est un format interne et contient journal, date, référence, comptes, tiers/analytique, libellés, débit/crédit et devise. Il peut exporter un brouillon sans le valider ; seul l’export d’une écriture déjà validée la marque `accounting_exported`. Ce n’est pas un format Sage certifié et aucune synchronisation Sage n’est réalisée.

Une écriture définitive n’est jamais créée pendant OCR, extraction ou analyse. Le comptable doit relire la facture originale, les avertissements et les lignes, puis valider explicitement.

## Tests et limites

`tests/Feature/OpenRouterInvoicePipelineTest.php` simule OCR.space et OpenRouter, vérifie la conservation des réponses, le type persisté, la complétude, les appels enchaînés, l’isolation des sociétés, les corrections/régénérations, les comptes autorisés, le rapprochement TTC malgré une retenue, l’équilibre, les décisions humaines et les exports CSV. `tests/Feature/InvoiceOcrProcessingTest.php` couvre les règles de cohérence TTC/TVA/net ainsi que les fournisseurs OCR.

PHP/Composer doivent être installés pour exécuter `composer test` et le lint PHP. Le build frontend se vérifie avec `npm run build`. Appliquez les migrations d’audit/proposition/export, dont `2026_09_29_000006_add_invoice_type_to_accounting_proposals`, avant d’activer les jobs de production.

Hors périmètre : mémoire apprenante des corrections, synchronisation/import Sage réel, prise en charge exhaustive des avoirs et règles fiscales complexes, exercice fiscal configurable, conversion multidevise, calcul automatique des taxes manquantes (seul le bouton explicite calcule à partir des taux/montants saisis), journalisation d’un événement séparé pour chaque interaction LLM.
