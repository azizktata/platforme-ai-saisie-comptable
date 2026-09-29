# Traitement OCR des factures — Phase 4

## Architecture et données

- `App\Contracts\OcrProvider` isole l’accès au fournisseur. `OCR_PROVIDER=ocr_space` active OCR.space Free par défaut ; `OCR_PROVIDER=mistral` conserve l’intégration Mistral pour un futur abonnement Pro.
- `InvoiceOcrSchema` construit le schéma JSON strict envoyé à Mistral et valide indépendamment la réponse. Les montants, quantités et taux restent des chaînes décimales jusqu’à validation.
- `InvoiceOcrResult` transporte réponse brute, données validées, modèle et usage.
- `ProcessInvoiceOcr` est un job unique par couple société/facture, explicitement épinglé à la connexion `database` et à la file `ocr`, avec trois tentatives et des délais de 30 puis 120 secondes. Les erreurs temporaires sont relancées ; les erreurs non récupérables et les tentatives épuisées mettent la facture en échec.
- `InvoiceTotalsConsistencyChecker` compare en millimes, sans flottants et sans correction. Il signale les totaux discordants ou non vérifiables.
- Une transaction enregistre champs facture, données OCR, réponse brute, usage, modèle, avertissements et lignes `invoice_lines`. La réponse brute est également conservée si sa structure est rejetée.
- Les migrations `2026_09_28_000015`–`...000017` créent les factures/lignes et leurs métadonnées OCR. `...000020` ajoute `ocr_reviewed_at` et `ocr_reviewed_by`; `Invoice::ocrReviewer()` expose l’utilisateur ayant marqué l’extraction vérifiée.

## Parcours HTTP et autorisation

- `GET /invoices` (`invoices.index`) est l’espace global relié à la navigation fixe. La première société accessible (triée par nom) est sélectionnée par défaut ; `company_id` permet d’en choisir une autre. Une sélection hors périmètre retourne 404.
- `GET /companies/{company}/invoices` (`companies.invoices.index`) est conservé comme historique complet de la société, en consultation/téléchargement.
- `POST /companies/{company}/invoices/upload` (`companies.invoices.upload`) reçoit un fichier par requête. Le Form Request exige un administrateur ou gestionnaire affecté et valide PDF/JPG/JPEG/PNG ; la taille maximale dépend du fournisseur actif (1 Mo OCR.space Free, 20 Mo Mistral).
- L’interface traite séquentiellement jusqu’à 300 fichiers. Les erreurs sont attribuées au bon fichier ; une défaillance n’annule pas les imports réussis. Un doublon SHA-256 par société retourne `409` avant stockage ; `confirm_duplicate` autorise explicitement un nouvel enregistrement.
- `StoreInvoiceUpload` écrit sur le disque privé `local` sous `companies/{id}/invoices`. Le téléchargement exige l’accès société et un rattachement facture/société correspondant.
- Dans l’espace global, l’utilisateur peut exporter en CSV uniquement les lignes sélectionnées de la page courante. Un gestionnaire peut marquer en lot les extractions OCR terminées comme vérifiées ; le serveur contrôle le rôle, la société et le statut avant d’enregistrer l’utilisateur et l’horodatage. Ce statut ne constitue pas une approbation comptable.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` (`companies.invoices.ocr.retry`) autorise les gestionnaires à mettre en file un échec ou une facture `uploaded` historique. Le contrôleur vérifie la société et le statut avant de modifier la facture.
- `POST /companies/{company}/invoices/bulk-review` (`companies.invoices.bulk-review`) valide un lot de 1 à 100 identifiants distincts, puis verrouille et valide toutes les factures dans une transaction avant de les marquer vérifiées.

## Cycle OCR

L’upload met la facture en `ocr_queued` et dispatch `ProcessInvoiceOcr(invoiceId, companyId)` sur la connexion `database` après persistance. Le job est unique par société/facture, utilise la file `ocr`, met à jour progression/tentatives et appelle le contrat `OcrProvider` sélectionné via `OCR_PROVIDER`. Par défaut, `OcrSpaceProvider` envoie le fichier privé en multipart à `POST https://api.ocr.space/parse/image`, place la clé dans l’en-tête `apikey` et demande `OCREngine=3`, `language=auto` et `isTable=true`. Il conserve la réponse brute et la transcription dans la description OCR, sans inventer fournisseur, dates, montants ou lignes structurées ; le texte est consultable dans la ligne Factures. Mistral reste disponible avec `OCR_PROVIDER=mistral` : `MistralOcrProvider` envoie une data URL au `POST /v1/ocr` avec `InvoiceOcrSchema` et valide les champs structurés comme auparavant.

Les données valides, modèle, usage, annotation, réponse brute et lignes sont sauvegardés dans une transaction. Les montants sont conservés tels que lus. `InvoiceTotalsConsistencyChecker` n’écrit aucun montant : quand toutes les composantes existent, il compare exactement en millimes la formule du total ; sinon il signale que le contrôle est non vérifiable. Les erreurs fournisseur sont mappées vers des codes/messages sûrs, retentées si temporaires et visibles dans la liste. Les erreurs de connexion journalisent un diagnostic court après retrait des secrets et de l’URL ; ne jamais journaliser ni communiquer les clés OCR.space/Mistral.

La page `Invoices/Index` actualise les statuts en cours, indique les erreurs et incohérences/non-vérifications, permet une relance autorisée, l’export CSV, la vérification de l’extraction et le téléchargement. Une vérification OCR n’est ni une proposition comptable ni une écriture validée.

## Configuration et worker

Dans `.env`, `OCR_PROVIDER=ocr_space` est le choix par défaut. Obtenir une clé gratuite depuis [OCR.space](https://ocr.space/ocrapi/freekey) et renseigner `OCR_SPACE_API_KEY` ; le endpoint est `https://api.ocr.space/parse/image`, le moteur par défaut est 3 et `OCR_SPACE_CA_BUNDLE` est un chemin PEM absolu optionnel. Pour revenir à Mistral, configurer `OCR_PROVIDER=mistral` et `MISTRAL_API_KEY` (ainsi que, si nécessaire, `MISTRAL_BASE_URL`, `MISTRAL_OCR_MODEL`, `MISTRAL_OCR_TIMEOUT` et `MISTRAL_CA_BUNDLE`). Appliquer les migrations (dont `jobs` et `failed_jobs`) et garder un worker actif :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

`ProcessInvoiceOcr` sélectionne explicitement `database`; modifier `QUEUE_CONNECTION=sync` ne rend donc pas ces jobs OCR synchrones. Vérifier le worker, la connexion base de données et l’accès HTTPS au fournisseur actif séparément. Le forfait OCR.space Free limite un fichier à 1 Mo et les PDF à 3 pages ; le quota global annoncé est de 500 requêtes/jour par IP et 25 000/mois pour les moteurs 1/2, plus 2 500 conversions Engine 3/mois. Les fichiers plus grands sont refusés dès la validation. Garder `DB_QUEUE_RETRY_AFTER=300`, supérieur au délai d’expiration du job. Aucun test runtime n’est fait avec une clé réelle.

### Windows / cURL error 60

`SSL certificate problem: unable to get local issuer certificate` (cURL 60) indique que le PHP utilisé par le worker ne dispose pas d’un bundle CA approprié pour vérifier le certificat HTTPS. La négociation TLS échoue avant que le endpoint puisse traiter le fichier ; ce n’est pas une erreur de format OCR. L’API OCR.space accepte les PDF et images dont PNG via son paramètre `file` multipart ([documentation OCR.space](https://ocr.space/ocrapi#PostParameters)).

Solutions sûres :

1. Télécharger le bundle CA PEM actuel depuis [curl.se/caextract](https://curl.se/docs/caextract.html), le sauvegarder localement, puis configurer son chemin absolu dans `.env`, par exemple `OCR_SPACE_CA_BUNDLE=C:/php/extras/ssl/cacert.pem`. L’option est appliquée uniquement à la requête OCR.space et conserve la vérification TLS.
2. Ou configurer le `php.ini` utilisé par le PHP en ligne de commande :

```ini
curl.cainfo = "C:/php/extras/ssl/cacert.pem"
openssl.cafile = "C:/php/extras/ssl/cacert.pem"
```

Pour identifier le bon fichier, lancer `php --ini` dans le même terminal/environnement que le worker. Après changement de `.env`, vider le cache de configuration si nécessaire (`php artisan config:clear`) et redémarrer entièrement `queue:work`; un worker déjà lancé conserve son processus/configuration. Le chemin déclaré via `OCR_SPACE_CA_BUNDLE` doit exister et être lisible. Le code traite maintenant les erreurs de certificat comme non réessayables, avec un message de configuration explicite, au lieu de brûler les trois tentatives. Après correction, relancer la facture en échec depuis l’espace Factures ; les anciens jobs échoués ne remettent pas automatiquement l’état facture à `ocr_queued`.

Ne désactivez pas la vérification TLS (`verify=false`, `CURLOPT_SSL_VERIFYPEER=false`) et n’ajoutez pas de certificats non fiables. Garder `OCR_SPACE_API_KEY` et `MISTRAL_API_KEY` secrètes.

Vérifier les exigences de confidentialité, région, conservation et traitement des factures avant d’activer la clé en production. Les documents quittent le stockage privé uniquement dans la requête du fournisseur configuré.

## Vérifications

- `tests/Feature/InvoiceOcrProcessingTest.php` utilise `Http::fake()` pour Mistral, OCR.space Engine 3, transcription texte, erreurs de configuration/transport et rejets de documents.
- `tests/Feature/InvoiceIntakeTest.php` couvre intake, queue, isolation, historique/workspace, doublons, relance et revue groupée.
- `composer test` nécessite PHP 8.3+ et Composer ; `npm run build` valide l’interface.

Les clés OCR.space et Mistral ne doivent jamais être fournies dans le navigateur ou ajoutées au dépôt. Cette phase ne génère aucun plan comptable, proposition, écriture, validation humaine d’écriture ni export Sage : ces travaux appartiennent à la Phase 5 ou restent différés.
