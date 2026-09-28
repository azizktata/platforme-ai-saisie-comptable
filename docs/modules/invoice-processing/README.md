# Traitement OCR — Phase 4

## Architecture

- `App\Contracts\OcrProvider` isole l’accès au fournisseur. Le binding Laravel courant est `MistralOcrProvider`.
- `InvoiceOcrSchema` construit le schéma JSON strict envoyé à Mistral et valide indépendamment la réponse. Les montants, quantités et taux restent des chaînes décimales jusqu’à validation.
- `InvoiceOcrResult` transporte réponse brute, données validées, modèle et usage.
- `ProcessInvoiceOcr` est un job unique par couple société/facture, sur la file `ocr`, avec 3 tentatives et des délais de 30 puis 120 secondes. Les erreurs temporaires sont relancées ; les erreurs non récupérables et les tentatives épuisées mettent la facture en échec.
- `InvoiceTotalsConsistencyChecker` compare en millimes, sans flottants et sans correction. Il signale les totaux discordants ou non vérifiables.
- Une transaction enregistre champs facture, données OCR, réponse brute, usage, modèle, avertissements et lignes `invoice_lines`. La réponse brute est également conservée si sa structure est rejetée.
- La liste Inertia affiche les états, messages et avertissements. Les factures encore en cours sont actualisées toutes les cinq secondes ; seuls les gestionnaires autorisés peuvent relancer un échec ou démarrer l’OCR d’une facture Phase 3 historique.

## Configuration / exploitation

Dans `.env`, définir côté serveur :

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=300
MISTRAL_API_KEY=<secret>
MISTRAL_BASE_URL=https://api.mistral.ai
MISTRAL_OCR_MODEL=mistral-ocr-latest
MISTRAL_OCR_TIMEOUT=180
```

La migration `2026_09_28_000018` crée `jobs` et `failed_jobs`. Exécuter `php artisan migrate`, puis laisser tourner un worker séparé :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

Le worker doit rester en service en production (superviseur/process manager). `DB_QUEUE_RETRY_AFTER` doit rester supérieur au délai d’expiration du job. En local/test, `QUEUE_CONNECTION=sync` peut exécuter les jobs immédiatement.

Les documents sont lus uniquement depuis le disque privé `local`, puis transmis en data URL à Mistral. Vérifier les exigences de confidentialité, région, conservation et traitement des factures avant d’activer la clé en production. Aucun secret ou corps de facture ne doit être journalisé.

## Vérifications

- `tests/Feature/InvoiceOcrProcessingTest.php` utilise `Http::fake()` pour succès structuré, réponse invalide, incohérence/non-vérifiabilité des montants et absence de clé.
- `tests/Feature/InvoiceIntakeTest.php` vérifie la planification après upload, l’isolation société, les doublons et la relance autorisée.
- `composer test` nécessite PHP 8.3+ et Composer ; `npm run build` valide l’interface.

La clé `MISTRAL_API_KEY` ne doit jamais être fournie dans le navigateur ou ajoutée au dépôt. Cette phase ne génère aucun plan comptable, proposition, écriture, validation humaine ni export Sage : ces travaux appartiennent à la Phase 5 ou restent différés.
