# Extraction OpenRouter et propositions comptables — Phase 5

## Configuration

OpenRouter est appelé côté serveur pour structurer le texte OCR.space puis proposer une écriture comptable. Configurez dans `.env` :

```dotenv
OPENROUTER_API_KEY=...
OPENROUTER_MODEL=openrouter/free
OPENROUTER_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_TIMEOUT=120
OPENROUTER_MAX_OCR_CHARS=100000
# Facultatifs : attribution OpenRouter et bundle CA PEM
OPENROUTER_SITE_URL=
OPENROUTER_APP_NAME=ComptaFlow
OPENROUTER_CA_BUNDLE=
```

`openrouter/free` route dynamiquement vers un modèle gratuit prenant en charge le JSON Schema strict ; le modèle retourné, l’usage et la réponse complète sont conservés pour audit. La disponibilité des modèles gratuits dépend d’OpenRouter. Gardez la vérification TLS activée et ne placez jamais la clé dans le navigateur ou les logs. Le démarrage des jobs nécessite un worker Laravel sur la file `ocr` :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

## Architecture

- `Services/OpenRouter/OpenRouterClient.php` : endpoint Chat Completions, clés/timeout/CA serveur, JSON strict, erreurs HTTP/TLS/JSON et modèle réel retourné.
- `Services/Ocr/InvoiceOcrSchema.php` : contrat facture réutilisé à la sortie de l’extraction OpenRouter et Mistral.
- `Services/Invoices/InvoiceDataExtractionService.php` : texte OCR traité comme donnée non fiable, appel `json_schema`, validation stricte.
- `InvoiceDataCompletenessChecker.php` : bloque l’analyse tant que fournisseur, numéro, date, devise, total ou libellé/ligne nécessaire manque.
- `InvoiceDataPersistence.php` : transaction sur champs facture et lignes, conserve également le contrat JSON courant.
- `AccountingProposalService.php` : construit un contexte à partir des référentiels actifs de la société, de son profil et de 20 écritures récentes de cette société ; les codes autorisés sont des `enum` du schéma OpenRouter.
- `AccountingProposalBalanceChecker.php` : contrôles déterministes en millimes pour équilibre et total, plus avertissements non bloquants.
- `ExtractInvoiceData` et `AnalyzeAccountingProposal` : jobs distincts, uniques par société/facture, trois tentatives et états d’échec propres à chaque étape.
- `AccountingProposalController.php` : revue/correction, validation des références société, rejet ou approbation explicite et création finale de l’écriture.

### Audit et modèle de données

- `invoices.ocr_response` et `invoices.ocr_text` : réponse OCR et transcription brute.
- `invoices.extraction_response`, `extraction_model`, `extraction_usage` : appel de structuration OCR par OpenRouter, distinct de la réponse OCR.
- `invoices.ocr_data`, colonnes normalisées et `invoice_lines` : extraction structurée actuellement utilisée. Les corrections humaines sont horodatées par `extraction_corrected_at` / `extraction_corrected_by` ; la réponse IA initiale reste conservée.
- `accounting_proposals` / `accounting_proposal_lines` : chaque version conserve proposition, modèle OpenRouter, réponse brute, usage, avertissements, lignes et trace de revue. Les corrections sont horodatées (`modified_by`, `modified_at`). Une correction des données facture crée une nouvelle proposition sans écraser les réponses précédentes ; la boîte de dialogue présente la version la plus récente.
- `journal_entries.invoice_id` relie l’écriture finale à la facture et empêche la création d’une seconde écriture liée à cette facture.

### Routes principales

- `GET /companies/{company}/invoices/{invoice}/details` : détails privés pour la boîte de dialogue, et seulement dans le périmètre société.
- `PUT .../extraction` : correction des données de facture par un gestionnaire ; une extraction complète relance automatiquement l’analyse.
- `PUT .../proposal` : correction des références/montants de la proposition par un gestionnaire.
- `POST .../proposal/reject` : rejet humain sans écriture.
- `POST .../proposal/approve` : revalidation serveur puis création explicite d’une écriture comptable liée.
- `POST .../ocr/retry` : relance ciblée de l’étape en échec sans répéter OCR quand le texte est déjà conservé.

Les policies/Form Requests requièrent un administrateur de cabinet ou un gestionnaire affecté. Comptes, journaux, tiers et axes sont revalidés contre la société et leur état actif ; aucun ID fourni par le navigateur ou le modèle n’est accepté sans vérification.

## Parcours d’un traitement

`ocr_queued` → `ocr_processing` → `data_extraction` → `accounting_analysis` → `proposal_ready` → `accounting_validated` ou `proposal_rejected`.

Si des champs obligatoires sont absents : `invoice_incomplete`, puis édition humaine et reprise automatique dès complétude. Une erreur de fournisseur laisse `data_extraction_failed` ou `accounting_analysis_failed` ; la relance cible cette étape. Mistral reste sélectionnable via `OCR_PROVIDER=mistral` : son annotation structurée validée évite l’appel OpenRouter de structuration, mais la proposition comptable utilise toujours OpenRouter.

Une écriture définitive n’est jamais créée pendant l’OCR, l’extraction ou l’analyse. L’utilisateur doit relire la facture originale, les avertissements et les lignes, puis cliquer pour valider.

## Tests et limites

`tests/Feature/OpenRouterInvoicePipelineTest.php` simule OCR.space et OpenRouter, vérifie la conservation des réponses, la complétude, les appels enchaînés, la séparation des sociétés, les corrections, les comptes autorisés, l’équilibre et l’approbation humaine. `tests/Feature/InvoiceOcrProcessingTest.php` garde les tests Mistral/OCR.space et erreurs OCR.

PHP/Composer ne sont pas disponibles dans l’environnement actuel : les tests et le lint PHP doivent être exécutés avec PHP 8.3+ (`composer test`). Le build front-end se vérifie avec `npm run build`. Le déploiement doit appliquer les migrations `2026_09_29_000001`–`...000003` avant d’activer les jobs Phase 5.

Hors périmètre actuel : mémoire apprenante des corrections, synchronisation/import Sage réel, prise en charge exhaustive des avoirs et règles fiscales complexes, calcul automatique des taxes manquantes, journalisation d’un événement séparé pour chaque interaction LLM.
