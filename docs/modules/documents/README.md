# Factures — intake et OCR (Phases 3–4)

## Schéma et modèles

- Les migrations `2026_09_28_000015` et `...000016` créent `invoices` et `invoice_lines`. La migration `...000017` ajoute taux, données structurées/brutes OCR, avertissements, modèle/usage, tentatives, horodatages et erreurs sûres.
- `Invoice` expose `company`, `thirdParty`, `uploader` et `lines`. Les champs extraits et les lignes sont nuls/absents avant le succès OCR.
- Les migrations historiques `documents` et `accounting_entries` sont préservées sans copie ni suppression de données.

## Parcours HTTP et autorisation

- `GET /companies/{company}/invoices` (`companies.invoices.index`) liste les factures scoppées à la société et paginées. Seuls les champs d’affichage sont exposés ; ni chemin privé ni réponse OCR brute ne sont envoyés à la page.
- `POST /companies/{company}/invoices/upload` (`companies.invoices.upload`) reçoit un fichier par requête. Le Form Request exige un administrateur ou gestionnaire affecté et valide PDF/JPG/JPEG/PNG, 20 Mo maximum.
- L’interface traite séquentiellement jusqu’à 300 fichiers. Les erreurs sont attribuées au bon fichier ; une défaillance n’annule pas les imports réussis. Un doublon SHA-256 par société retourne `409` avant stockage ; `confirm_duplicate` autorise explicitement un nouvel enregistrement.
- `StoreInvoiceUpload` écrit sur le disque privé `local` sous `companies/{id}/invoices`. Le téléchargement exige l’accès société et un rattachement facture/société correspondant.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` (`companies.invoices.ocr.retry`) autorise les gestionnaires à mettre en file un échec ou une facture `uploaded` historique. Le contrôleur vérifie la société et le statut avant de modifier la facture.

## Cycle OCR

L’upload met la facture en `ocr_queued` et dispatch `ProcessInvoiceOcr(invoiceId, companyId)` après commit. Le job est unique par société/facture, utilise la file `ocr`, met à jour progression/tentatives, et appelle le contrat `OcrProvider`. `MistralOcrProvider` lit le PDF/image uniquement sur `local`, envoie la donnée base64 au endpoint Mistral OCR avec le schéma `InvoiceOcrSchema`, puis passe la réponse par une validation Laravel stricte.

Les données valides, modèle, usage, annotation, réponse brute et lignes sont sauvegardés dans une transaction. Les montants sont conservés tels que lus. `InvoiceTotalsConsistencyChecker` n’écrit aucun montant : quand toutes les composantes existent, il compare exactement en millimes la formule du total ; sinon il signale que le contrôle est non vérifiable. Les erreurs fournisseur sont mappées vers des codes/messages sûrs, retentées si temporaires et visibles dans la liste.

La page `Invoices/Index` actualise les statuts en cours, indique les erreurs et incohérences/non-vérifications, permet une relance autorisée et conserve le téléchargement. La Phase 4 s’arrête à l’extraction ; aucune proposition ou écriture comptable n’est créée.

## Configuration et worker

Dans `.env`, renseigner une clé serveur `MISTRAL_API_KEY`, éventuellement `MISTRAL_BASE_URL`, `MISTRAL_OCR_MODEL` et `MISTRAL_OCR_TIMEOUT`. Pour un traitement asynchrone, appliquer toutes les migrations (dont la table de queue `jobs`) et garder un worker actif :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

Le défaut `QUEUE_CONNECTION=database` est adapté aux déploiements simples ; conserver `DB_QUEUE_RETRY_AFTER=300`, supérieur au timeout du job. En local/test, `sync` reste utilisable.

## Tests

`InvoiceIntakeTest` couvre upload, queue, confidentialité, autorisations, doublons et relance. `InvoiceOcrProcessingTest` simule Mistral via `Http::fake()` et couvre extraction/lignes, conservation brute, schéma invalide, avertissements de total et absence de clé. Exécuter avec `composer test`; `npm run build` vérifie React/TypeScript.
