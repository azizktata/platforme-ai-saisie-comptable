# Traitement OCR des factures — Phase 4

## Architecture et données

- `App\Contracts\OcrProvider` isole l’accès au fournisseur. Le binding Laravel courant est `MistralOcrProvider`.
- `InvoiceOcrSchema` construit le schéma JSON strict envoyé à Mistral et valide indépendamment la réponse. Les montants, quantités et taux restent des chaînes décimales jusqu’à validation.
- `InvoiceOcrResult` transporte réponse brute, données validées, modèle et usage.
- `ProcessInvoiceOcr` est un job unique par couple société/facture, explicitement épinglé à la connexion `database` et à la file `ocr`, avec trois tentatives et des délais de 30 puis 120 secondes. Les erreurs temporaires sont relancées ; les erreurs non récupérables et les tentatives épuisées mettent la facture en échec.
- `InvoiceTotalsConsistencyChecker` compare en millimes, sans flottants et sans correction. Il signale les totaux discordants ou non vérifiables.
- Une transaction enregistre champs facture, données OCR, réponse brute, usage, modèle, avertissements et lignes `invoice_lines`. La réponse brute est également conservée si sa structure est rejetée.
- Les migrations `2026_09_28_000015`–`...000017` créent les factures/lignes et leurs métadonnées OCR. `...000020` ajoute `ocr_reviewed_at` et `ocr_reviewed_by`; `Invoice::ocrReviewer()` expose l’utilisateur ayant marqué l’extraction vérifiée.

## Parcours HTTP et autorisation

- `GET /invoices` (`invoices.index`) est l’espace global relié à la navigation fixe. Sans sélection, il affiche un sélecteur des sociétés accessibles ; avec `company_id`, il n’affiche que les factures de cette société. Une sélection hors périmètre retourne 404.
- `GET /companies/{company}/invoices` (`companies.invoices.index`) est conservé comme historique complet de la société, en consultation/téléchargement.
- `POST /companies/{company}/invoices/upload` (`companies.invoices.upload`) reçoit un fichier par requête. Le Form Request exige un administrateur ou gestionnaire affecté et valide PDF/JPG/JPEG/PNG, 20 Mo maximum.
- L’interface traite séquentiellement jusqu’à 300 fichiers. Les erreurs sont attribuées au bon fichier ; une défaillance n’annule pas les imports réussis. Un doublon SHA-256 par société retourne `409` avant stockage ; `confirm_duplicate` autorise explicitement un nouvel enregistrement.
- `StoreInvoiceUpload` écrit sur le disque privé `local` sous `companies/{id}/invoices`. Le téléchargement exige l’accès société et un rattachement facture/société correspondant.
- Dans l’espace global, l’utilisateur peut exporter en CSV uniquement les lignes sélectionnées de la page courante. Un gestionnaire peut marquer en lot les extractions OCR terminées comme vérifiées ; le serveur contrôle le rôle, la société et le statut avant d’enregistrer l’utilisateur et l’horodatage. Ce statut ne constitue pas une approbation comptable.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` (`companies.invoices.ocr.retry`) autorise les gestionnaires à mettre en file un échec ou une facture `uploaded` historique. Le contrôleur vérifie la société et le statut avant de modifier la facture.
- `POST /companies/{company}/invoices/bulk-review` (`companies.invoices.bulk-review`) valide un lot de 1 à 100 identifiants distincts, puis verrouille et valide toutes les factures dans une transaction avant de les marquer vérifiées.

## Cycle OCR

L’upload met la facture en `ocr_queued` et dispatch `ProcessInvoiceOcr(invoiceId, companyId)` sur la connexion `database` après persistance. Le job est unique par société/facture, utilise la file `ocr`, met à jour progression/tentatives, et appelle le contrat `OcrProvider`. `MistralOcrProvider` lit le PDF/image uniquement sur `local`, envoie une data URL base64 au endpoint `POST /v1/ocr` avec l’annotation `InvoiceOcrSchema`, puis passe la réponse par une validation Laravel stricte. La requête actuelle demande des tableaux Markdown et ne redemande pas les images base64 de sortie (`table_format=markdown`, `include_image_base64=false`). La comparaison demandée (`table_format=html`, `include_image_base64=true`) peut être faite une fois le transport joignable ; ces options modifient la réponse, pas la connectivité. Garder `include_image_base64=false` par défaut évite d’alourdir la réponse brute persistée.

Les données valides, modèle, usage, annotation, réponse brute et lignes sont sauvegardés dans une transaction. Les montants sont conservés tels que lus. `InvoiceTotalsConsistencyChecker` n’écrit aucun montant : quand toutes les composantes existent, il compare exactement en millimes la formule du total ; sinon il signale que le contrôle est non vérifiable. Les erreurs fournisseur sont mappées vers des codes/messages sûrs, retentées si temporaires et visibles dans la liste. Les erreurs de connexion journalisent un diagnostic court après retrait du bearer token et de l’URL ; ne jamais journaliser ni communiquer `MISTRAL_API_KEY`.

La page `Invoices/Index` actualise les statuts en cours, indique les erreurs et incohérences/non-vérifications, permet une relance autorisée, l’export CSV, la vérification de l’extraction et le téléchargement. Une vérification OCR n’est ni une proposition comptable ni une écriture validée.

## Configuration et worker

Dans `.env`, renseigner une clé serveur `MISTRAL_API_KEY`, éventuellement `MISTRAL_BASE_URL`, `MISTRAL_OCR_MODEL` et `MISTRAL_OCR_TIMEOUT`. Appliquer les migrations (dont `jobs` et `failed_jobs`) et garder un worker actif :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

`ProcessInvoiceOcr` sélectionne explicitement `database`; modifier `QUEUE_CONNECTION=sync` ne rend donc pas ces jobs OCR synchrones. Vérifier le worker, la connexion base de données et l’accès sortant au endpoint Mistral séparément. Garder `DB_QUEUE_RETRY_AFTER=300`, supérieur au délai d’expiration du job. Un diagnostic `provider_unreachable` apparaît après qu’un worker a pris le job mais n’a pas pu joindre le fournisseur ; cela ne prouve pas que le PNG ou les options `table_format`/`include_image_base64` sont en cause. Aucun test runtime n’est fait avec une clé réelle.

Vérifier les exigences de confidentialité, région, conservation et traitement des factures avant d’activer la clé en production. Les documents quittent le stockage privé uniquement dans la requête du fournisseur configuré.

## Vérifications

- `tests/Feature/InvoiceOcrProcessingTest.php` utilise `Http::fake()` pour succès structuré, réponse invalide, incohérence/non-vérifiabilité des montants et absence de clé.
- `tests/Feature/InvoiceIntakeTest.php` couvre intake, queue, isolation, historique/workspace, doublons, relance et revue groupée.
- `composer test` nécessite PHP 8.3+ et Composer ; `npm run build` valide l’interface.

La clé Mistral ne doit jamais être fournie dans le navigateur ou ajoutée au dépôt. Cette phase ne génère aucun plan comptable, proposition, écriture, validation humaine d’écriture ni export Sage : ces travaux appartiennent à la Phase 5 ou restent différés.
