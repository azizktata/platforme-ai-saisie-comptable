# Factures — intake, extraction, revue et proposition comptable

Ce module couvre l’import sécurisé, le workflow OCR/extraction, la revue humaine et la proposition comptable. Il est implémenté avec Laravel, Inertia, React et TypeScript ; les fichiers originaux restent dans un disque privé et les appels fournisseur restent côté serveur.

## Configuration

### OCR

OCR.space Engine 3 Free est le défaut. Configurer la clé côté serveur dans `.env` :

```dotenv
OCR_PROVIDER=ocr_space
OCR_SPACE_API_KEY=...
OCR_SPACE_ENGINE=3
```

Mistral reste disponible comme fournisseur OCR/annotation alternative : `OCR_PROVIDER=mistral`, `MISTRAL_API_KEY=...`. Les données structurées de Mistral passent par le même contrat/normalisateur Laravel ; cette voie ne lance pas l’appel d’extraction OpenRouter. Les propositions comptables continuent à utiliser OpenRouter.

### Extraction de facture et proposition comptable

L’extraction de texte OCR est indépendante du fournisseur OCR et de la proposition comptable :

```dotenv
INVOICE_EXTRACTION_PROVIDER=openrouter
OPENROUTER_API_KEY=...
OPENROUTER_EXTRACTION_MODEL=qwen/qwen-2.5-7b-instruct:free
OPENROUTER_EXTRACTION_MAX_TOKENS=2500
OPENROUTER_MODEL=openrouter/free
OPENROUTER_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_TIMEOUT=120
OPENROUTER_MAX_OCR_CHARS=100000
OPENROUTER_SITE_URL=
OPENROUTER_APP_NAME=ComptaFlow
OPENROUTER_CA_BUNDLE=
```

Le modèle par défaut est exactement `qwen/qwen-2.5-7b-instruct:free` ; il est configurable via `OPENROUTER_EXTRACTION_MODEL`, pas codé dans l’interface. `INVOICE_EXTRACTION_PROVIDER` et `OPENROUTER_EXTRACTION_MODEL` sont configurés sous `services.invoice_extraction`. La proposition comptable est configurée séparément par `OPENROUTER_MODEL=openrouter/free`.

`InvoiceDataExtractionService` transmet un JSON Schema strict et demande uniquement le JSON, sans ajout de raisonnement, d’explication, de champ inventé, de calcul ou de décision comptable. La validation rejette les propriétés inconnues, les valeurs mal formées, les réponses tronquées et les réponses safety-classifier. La limite par défaut de 2 500 tokens est bornée/configurable entre 2 000 et 3 000. Laravel normalise les dates, devises, taux et montants ; les validations déterministes de complétude et totaux sont séparées du modèle. Aucune valeur manquante n’est calculée. Une erreur d’extraction ne remplace pas les dernières données facture, les lignes valides ni la proposition prête précédente.

Les clés fournisseur ne doivent pas être exposées au navigateur ni consignées dans les logs. Conserver la validation TLS activée et configurer `OPENROUTER_CA_BUNDLE`/les CA des fournisseurs uniquement si l’environnement l’exige. OCR.space Free limite un fichier à 1 Mo et un PDF à trois pages.

## Démarrage

Appliquer les migrations puis exécuter un worker sur la file utilisée par les jobs d’intake et d’IA :

```bash
php artisan migrate
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

`DB_QUEUE_RETRY_AFTER` doit être supérieur au timeout du worker. Le frontend est inclus dans le build principal (`npm run build`).

## Workflow et validation

1. Un utilisateur autorisé choisit une société accessible et importe un PDF/JPG/JPEG/PNG. Chaque fichier est enregistré dans `companies/{company}/invoices`, dédoublonné par SHA-256 dans la société et traité indépendamment.
2. `ProcessInvoiceOcr` produit `ocr_text` et `ocr_response` avec OCR.space, ou une annotation structurée Mistral. OCR et extraction disposent de statuts, compteurs, modèles, dates et erreurs dédiés.
3. Avec OCR.space, `ExtractInvoiceData` structure uniquement le texte OCR sauvegardé. Le résultat validé est conservé séparément et synchronisé dans `ocr_data`, les colonnes `invoices` et `invoice_lines`.
4. `InvoiceDataCompletenessChecker` bloque l’analyse si le fournisseur, numéro, date, devise, total ou description/ligne nécessaire manque. `InvoiceTotalsConsistencyChecker` signale les incohérences sans changer les montants ni inventer de valeurs.
5. Une facture complète lance `AnalyzeAccountingProposal` avec les référentiels et l’historique limité à la société liée. La proposition est révisable et auditable. Le serveur revalide comptes, journaux, tiers, axes, équilibre et rapprochement avant approbation.
6. Un gestionnaire peut enregistrer la facture/proposition, rejeter ou approuver. Le rejet ne crée aucune écriture ; seule l’approbation explicite crée une écriture liée, unique par facture. L’export réel Sage est hors périmètre.

Pour relancer la structuration sans refaire OCR, `POST /companies/{company}/invoices/{invoice}/extraction/retry` reprend le `ocr_text` conservé. La route exige une transcription disponible, interdit un traitement actif ou une facture déjà journalisée, puis ne dispatch que `ExtractInvoiceData`. `POST .../ocr/retry` est l’action séparée pour relancer OCR. En cas d’échec d’extraction, la transcription, les lignes et les dernières valeurs normalisées valides restent intactes ; le statut/message d’erreur permet une correction ou une nouvelle tentative.

## Espace et composants React

- `resources/js/Pages/Invoices/Index.tsx` : sélection de société, liste et import ; les liens de facture ouvrent la revue dédiée.
- `resources/js/Pages/Invoices/Show.tsx` : page de revue d’une facture, retour à la liste.
- `resources/js/Components/InvoiceDocumentViewer.tsx` : original PDF/image, téléchargement, zoom, ajustement, rotation des images, plein écran et navigation PDF ; la rotation native des PDF dépend du navigateur.
- `resources/js/Components/InvoiceReviewPanel.tsx` : détails privés, statut/modèle lorsqu’ils existent, texte OCR, formulaire facture et proposition existante. L’éditeur regroupe fournisseur, client, métadonnées, lignes, totaux/taxes et coordonnées bancaires. Les totaux extraits sont préservés ; aucune somme n’est recalculée dans React.

Sur desktop, le viewer et le panneau sont côte à côte dans des colonnes indépendamment scrollables ; sur petit écran ils s’empilent. Les succès et erreurs d’action sont signalés avec toast.

## Routes HTTP principales

- `GET /invoices` : workspace multi-société ; choisit une société accessible par défaut, permet de sélectionner les autres sociétés autorisées.
- `GET /companies/{company}/invoices` : historique société.
- `POST /companies/{company}/invoices/upload` : import privé.
- `GET /companies/{company}/invoices/{invoice}` : page dédiée de revue.
- `GET /companies/{company}/invoices/{invoice}/preview` : flux privé du document (`private, no-store`).
- `GET /companies/{company}/invoices/{invoice}/details` : données JSON à la demande, protégées par l’accès société.
- `PUT /companies/{company}/invoices/{invoice}/extraction` : enregistrer les corrections structurées.
- `POST /companies/{company}/invoices/{invoice}/extraction/retry` : nouvelle extraction à partir du texte OCR stocké, sans OCR.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` : reprise OCR.
- `PUT /companies/{company}/invoices/{invoice}/proposal` : correction de proposition.
- `POST /companies/{company}/invoices/{invoice}/proposal/reject` et `/proposal/approve` : décisions humaines.

Chaque endpoint revérifie utilisateur/rôle, société et lien facture-société. Les détails et fichiers ne sont pas exposés dans les props globales de liste.

## Données et structure

- `Invoice` / `InvoiceLine` : champs normalisés facture/lignes ; contact fournisseur/client, modalités de paiement, coordonnées bancaires, réductions et montants extraits.
- `InvoiceOcrResult` : réponse OCR brute, texte, structure optionnelle, modèle, usage et informations de traitement.
- `InvoiceOcrSchema` / `InvoiceDataExtractionService` : JSON Schema strict, validation et normalisation ; extraction n’effectue aucun calcul comptable.
- `InvoiceDataPersistence` : persistance transactionnelle de l’annotation JSON, colonnes et lignes.
- `InvoiceDataCompletenessChecker` / `InvoiceTotalsConsistencyChecker` : règles déterministes distinctes du modèle.
- `ProcessInvoiceOcr`, `ExtractInvoiceData`, `AnalyzeAccountingProposal` : jobs indépendants et tenant-scoped sur `ocr`.
- `AccountingProposal` / `AccountingProposalLine` : réponses, version, modèle, usage, avertissements et revue humaine.
- `InvoiceController` / `AccountingProposalController` : intake, accès, actions de revue, corrections et validation.

Migrations relatives aux factures/propositions : `2026_09_28_000015_create_invoices_table`, `2026_09_28_000016_create_invoice_lines_table`, migrations OCR/audit, `2026_09_29_000001_add_llm_audit_and_invoice_journal_link`, `...000002_create_accounting_proposals_table`, `...000003_create_accounting_proposal_lines_table` et `...000004_add_invoice_contact_and_discount_fields`.

## États et erreurs

Les statuts incluent `uploaded`, `ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated` ainsi que `ocr_failed`, `data_extraction_failed`, `accounting_analysis_failed`. `ocr_completed` reste un état historique. Les nouvelles réponses hors JSON/schema ou safety-classifier échouent sans modifier l’annotation déjà enregistrée ; les modèles et réponses fournisseur sont conservés pour audit sans inclure les secrets dans les logs.

## Tests

- `tests/Feature/InvoiceIntakeTest.php` : uploads, rôles, isolement société, page/aperçu, doublons, file et téléchargement.
- `tests/Feature/InvoiceOcrProcessingTest.php` : OCR.space Engine 3, Mistral, schémas, erreurs et TLS.
- `tests/Feature/OpenRouterInvoicePipelineTest.php` : extraction, validation/normalisation, erreurs, relance depuis OCR existant, préservation des données, analyse et décision humaine.
- `npm run build` : build React/TypeScript.
- `composer test` et lint PHP : nécessitent PHP 8.3+ et Composer.

## Hors périmètre

Pas d’import/export Sage réel, apprentissage automatique des corrections, création de comptes/tiers par IA, rapprochement OCR sémantique des doublons, ni correction automatique de totaux ambigus.
