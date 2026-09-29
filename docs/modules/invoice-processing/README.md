# Factures — intake, OCR et proposition comptable (Phases 3–5)

## Pré-requis et configuration IA

OCR.space Engine 3 est le fournisseur OCR par défaut ; sa clé reste côté serveur :

```dotenv
OCR_PROVIDER=ocr_space
OCR_SPACE_API_KEY=...
OCR_SPACE_ENGINE=3
```

Mistral reste disponible pour une utilisation future/alternative : `OCR_PROVIDER=mistral` et `MISTRAL_API_KEY=...`. Mistral fournit des données structurées ; dans ce chemin l’étape OpenRouter de structuration est sautée, mais OpenRouter reste le fournisseur de propositions comptables.

Pour OCR.space et les propositions comptables, configurer OpenRouter :

```dotenv
OPENROUTER_API_KEY=...
OPENROUTER_MODEL=openrouter/free
OPENROUTER_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_TIMEOUT=120
OPENROUTER_MAX_OCR_CHARS=100000
OPENROUTER_SITE_URL=
OPENROUTER_APP_NAME=ComptaFlow
OPENROUTER_CA_BUNDLE=
```

`openrouter/free` route vers un modèle gratuit disponible compatible JSON Schema strict. Le modèle réellement renvoyé peut varier et est conservé en audit. Les documents quittent le stockage privé uniquement dans la requête du fournisseur configuré. Ne désactivez jamais la validation TLS et ne mettez pas les clés dans le navigateur ou le dépôt.

Appliquer les migrations puis faire tourner le worker (timeouts OCR/LLM jusqu’à 210 secondes, `DB_QUEUE_RETRY_AFTER` doit rester supérieur) :

```bash
php artisan migrate
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

## Données et architecture

- `Invoice` et `InvoiceLine` portent les champs normalisés et lignes.
- `InvoiceOcrResult` transporte réponse brute, structure optionnelle, modèle, usage, transcription et indicateur de données structurées. OCR.space précise qu’il ne produit pas de JSON facture ; Mistral précise qu’il le produit.
- `ProcessInvoiceOcr` : OCR et transitions `ocr_queued` → `ocr_processing` → extraction ou analyse.
- `ocr_text` / `ocr_response` : transcription lisible et réponse fournisseur complète OCR.
- `ExtractInvoiceData` / `InvoiceDataExtractionService` : OpenRouter `json_schema` strict et validation `InvoiceOcrSchema`; audit séparé dans `extraction_response`, `extraction_model`, `extraction_usage`.
- `InvoiceDataCompletenessChecker` bloque les données obligatoires manquantes ; `InvoiceDataPersistence` synchronise JSON, colonnes et lignes.
- `AnalyzeAccountingProposal` / `AccountingProposalService` : contexte construit depuis la société de la facture (profil, comptes, journaux, tiers, axes et 20 écritures récentes), réponses schema strict et codes à choix bornés.
- `AccountingProposal` / `AccountingProposalLine` gardent la réponse brute, modèle, usage, lignes, avertissements et décision de revue.
- `AccountingProposalController` revalide la portée société des références et n’écrit un `JournalEntry` qu’après approbation humaine. La clé `journal_entries.invoice_id` est unique.

Les migrations Phase 5 sont `2026_09_29_000001_add_llm_audit_and_invoice_journal_link`, `...000002_create_accounting_proposals_table` et `...000003_create_accounting_proposal_lines_table`.

## Parcours HTTP et autorisations

- `GET /invoices` : workspace global, première société accessible préselectionnée ; toutes les actions revérifient l’accès.
- `GET /companies/{company}/invoices` : historique complet de la société.
- `POST /companies/{company}/invoices/upload` : import privé un fichier à la fois, lots UI séquentiels, doublon par hash société.
- `GET /companies/{company}/invoices/{invoice}/details` : données et transcription OCR affichées dans le dialogue, autorisation lecture société.
- `PUT .../extraction` : correction des données par administrateur/gestionnaire ; complétude déclenche l’analyse.
- `PUT .../proposal` : correction des lignes et comptes par administrateur/gestionnaire.
- `POST .../proposal/reject` : rejet explicite, sans écriture.
- `POST .../proposal/approve` : nouvelle vérification des règles et création explicite d’une seule écriture.
- `POST .../ocr/retry` : relance de l’étape échouée. Une erreur d’analyse ne refait pas l’OCR.

Le texte brut n’est pas envoyé dans les props de liste ; il est chargé à la demande dans un endpoint privé `no-store`. Les réponses complètes sont conservées en base pour audit et ne sont pas journalisées dans les logs.

## Contrôles et états

Le contrôle facture compare au millime HT + TVA + FODEC + autres taxes + timbre − retenue = total. Il ne suppose pas automatiquement qu’une donnée manquante est nulle. Champs requis pour analyse : fournisseur, numéro, date complète, devise, total et description de facture ou d’une ligne.

La proposition doit utiliser uniquement les comptes/journaux actifs de la société. Avant validation, le serveur vérifie à nouveau les références, les montants, l’équilibre, le rapprochement au total invoice + retenue explicite et la complétude. Les alertes faible confiance/tiers non apparié restent visibles. Un manager peut rejeter ou approuver ; l’écriture est créée seulement par ce clic humain et n’est pas exportée vers Sage.

Les états d’étape affichés sont `ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated` et les états d’échec dédiés. La page interroge les données toutes les cinq secondes pendant les étapes en cours.

## Tests

- `tests/Feature/InvoiceIntakeTest.php` : stockage privé, rôles, accès société, doublons, historique, file et téléchargement.
- `tests/Feature/InvoiceOcrProcessingTest.php` : OCR.space Engine 3, Mistral, schémas, erreurs et TLS.
- `tests/Feature/OpenRouterInvoicePipelineTest.php` : extraction structurée, audits, complétude, analyse, isolement des sociétés, corrections et validation humaine.
- `npm run build` valide React/TypeScript. `composer test` et lint PHP demandent PHP 8.3+ et Composer, non disponibles dans l’environnement courant.

## Hors périmètre

Pas d’import/export Sage réel, apprentissage des corrections, creation de nouveaux tiers/comptes, rapprochement OCR de doublons sémantiques, règles exhaustives de devises/avoirs/retenues, ni conversion automatique de résultats incertains.
