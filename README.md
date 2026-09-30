# Plateforme AI de saisie comptable automatisée

Application de traitement de factures fournisseurs pour cabinets gérant plusieurs sociétés. Le cahier des charges client est la source de vérité : [`docs/requirements.md`](docs/requirements.md). La décision pipeline est OCR.space Free Engine 3 par défaut, le modèle OpenRouter configurable `qwen/qwen-2.5-7b-instruct:free` pour convertir le texte OCR en JSON facture, `openrouter/free` pour l’analyse comptable, et Mistral OCR comme fournisseur sélectionnable.

## État d’implémentation

Les **Phases 1 à 5** sont implémentées au niveau applicatif : fondation multi-cabinet, référentiels comptables par société, intake et stockage privé, OCR, extraction structurée, contrôle de complétude, propositions comptables, correction/rejet et validation humaine. Une proposition ne crée aucune écriture automatiquement ; l’écriture est créée seulement après validation explicite par un rôle autorisé. OCR brut, transcription et réponses OpenRouter sont audités séparément. Le build TypeScript passe. PHP/Composer ne sont pas disponibles dans l’environnement courant : les migrations, tests et lint PHP doivent être exécutés avant déploiement.

La Phase 2 dispose de données de démonstration déterministes de type Sage. Aucune base Sage ni aucun fichier `.mae` réel n’a été fourni ; import/synchronisation/export Sage reste différé. Les règles fiscales complexes, mémoire apprenante et les avoirs restent à compléter avec un expert-comptable.

## Stack et prérequis

- PHP 8.3+, Laravel 13, Composer, extensions `pdo_mysql`, `pdo_sqlite`, `mbstring`, `fileinfo`, `openssl`.
- MySQL 8+ (SQLite en mémoire pour les tests).
- Node.js 18+ et npm ; React 19, Inertia.js, TypeScript, Tailwind CSS.
- Laravel Queue `database` ; OCR et IA sont exécutés côté serveur sur la file `ocr`.

## Démarrage local

Créez une base MySQL vide (`comptaflow` par défaut), puis :

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
```

Le seeder crée un cabinet d’exemple, trois sociétés fictives, un administrateur et des référentiels/écritures de démonstration en environnement `local`/`testing` uniquement. Valeurs locales par défaut : `demo@example.test` / `password`. Changez-les avant d’exposer un environnement ; ne réutilisez jamais ces identifiants en production.

Configurez les clés fournisseur côté serveur dans `.env` :

```dotenv
# OCR par défaut
OCR_PROVIDER=ocr_space
OCR_SPACE_API_KEY=...
OCR_SPACE_ENGINE=3

# Structuration du texte OCR.space et proposition comptable
INVOICE_EXTRACTION_PROVIDER=openrouter
OPENROUTER_API_KEY=...
OPENROUTER_MODEL=openrouter/free
OPENROUTER_EXTRACTION_MODEL=qwen/qwen-2.5-7b-instruct:free
OPENROUTER_EXTRACTION_MAX_TOKENS=2500
OPENROUTER_ENDPOINT=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_TIMEOUT=120
OPENROUTER_MAX_OCR_CHARS=100000

# Facultatif, Mistral fournit directement les données structurées
# OCR_PROVIDER=mistral
# MISTRAL_API_KEY=...
```

L’extraction facture utilise par défaut `qwen/qwen-2.5-7b-instruct:free`, configuré séparément de l’OCR et des propositions comptables par `INVOICE_EXTRACTION_PROVIDER` et `OPENROUTER_EXTRACTION_MODEL`. Le service envoie le JSON Schema strict et exige uniquement le JSON ; il n’ajoute aucun champ de raisonnement. La sortie est plafonnée à 2 500 tokens (configurable de 2 000 à 3 000), puis Laravel normalise et valide les valeurs avant persistance. Une réponse invalide n’écrase pas les données facture déjà valides. `openrouter/free` reste le modèle des propositions comptables. Les modèles gratuits et leur disponibilité peuvent changer. OCR.space Free limite un fichier à 1 Mo et les PDF à trois pages. Mistral reste disponible via `OCR_PROVIDER=mistral` ; son annotation structurée évite l’étape de structuration OpenRouter, mais la proposition comptable utilise OpenRouter.

Démarrez un worker dans un terminal distinct :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

`DB_QUEUE_RETRY_AFTER=300` doit rester supérieur au timeout des jobs. Les états d’avancement sont actualisés dans le workspace Factures. Après toute modification `.env`, redémarrez le worker.

### TLS et clés API

La validation TLS reste activée pour OCR.space, Mistral et OpenRouter. Sur Windows ou un serveur configuré sans CA système, fournissez un chemin absolu PEM dans `OCR_SPACE_CA_BUNDLE`, `MISTRAL_CA_BUNDLE` ou `OPENROUTER_CA_BUNDLE` (ou configurez `curl.cainfo` / `openssl.cafile` dans le `php.ini` CLI). Ne définissez jamais `verify=false`. Les clés API ne doivent jamais être envoyées au navigateur ni ajoutées au dépôt. Vérifiez les exigences de confidentialité, région et conservation avant production ; les factures sont transmises au fournisseur configuré.

Démarrez Vite et Laravel dans deux terminaux :

```bash
npm run dev
php artisan serve --host=0.0.0.0
```

Ouvrez l’URL affichée par Artisan. Un visiteur peut créer un cabinet et son premier administrateur depuis `/register` ; les administrateurs peuvent ensuite gérer sociétés, activités et utilisateurs. `php artisan cabinet:create` et `php artisan users:create {slug-du-cabinet}` restent disponibles pour le provisionnement CLI.

## Modules livrés

### Cabinets et données comptables (Phases 1–2)

Les rôles sont `cabinet_admin`, `invoice_manager` et `company_user`. Un utilisateur accède aux sociétés via `company_user_access`; toute requête revalide le cabinet et la société côté serveur. Les plans comptables, journaux, tiers, axes et écritures sont propres à chaque société. Les données de démonstration sont fictives ; aucune intégration réelle Sage n’est activée.

### Intake, OCR et extraction facture (Phases 3–4)

Le lien Factures ouvre `/invoices`, qui présélectionne la première société accessible ; les autres choix restent limités aux accès de l’utilisateur. Les gestionnaires importent des PDF/JPG/JPEG/PNG privés, jusqu’à 300 fichiers par sélection UI. Chaque fichier est traité indépendamment, hashé, et un doublon de la même société exige confirmation. L’historique complet reste disponible à `/companies/{company}/invoices`.

`ProcessInvoiceOcr` conserve le texte OCR dans `ocr_text` et la réponse brute dans `ocr_response`. OCR.space Engine 3 retourne du texte ; OpenRouter produit le JSON facture validé contre `InvoiceOcrSchema`. Mistral reste une alternative structurée. Les champs manquants, avertissements, erreurs et tentatives sont présentés dans l’interface. Les montants ne sont jamais recalculés/corrigés silencieusement.

### Analyse et validation humaine (Phase 5)

L’extraction facture et la proposition comptable sont deux étapes OpenRouter distinctes pour OCR.space. Les données requises sont fournisseur, numéro, date, devise, total et description ou ligne décrite. Si une donnée requise manque, l’analyse comptable ne démarre pas ; le gestionnaire peut corriger la facture dans la page de revue dédiée. Une complétude valide déclenche automatiquement l’analyse sur le contexte limité à la société : profil, référentiels actifs et écritures récentes de cette société.

La proposition et ses lignes sont persistées avec le type de facture, avertissements, réponse brute, modèle et usage. Le gestionnaire peut modifier le type, journal, comptes, tiers, axe, libellés et montants, ajouter/supprimer des lignes, régénérer un brouillon éligible ou rejeter sans écriture. Les versions restent conservées pour audit. Le serveur revalide références société, catégorie, équilibre, TTC brut et cohérence des totaux avant validation. Le TTC n’est pas réduit par la retenue ; celle-ci est prise en compte séparément pour le net à payer. Seule l’action explicite « Valider » crée une écriture liée et unique. L’export CSV est interne ; aucun export ou synchronisation Sage n’est effectué.

La page dédiée `/companies/{company}/invoices/{invoice}` affiche le document dans `InvoiceDocumentViewer` et le workspace `InvoiceReviewPanel` côte à côte avec défilement indépendant sur desktop ; les colonnes s’empilent sur petit écran. Le workspace affiche la confiance globale et les scores réels disponibles (les catégories non scorées sont `N/D`), puis cinq cartes : données éditables, type de facture, proposition brouillon, contrôles, décision. « Recalculer à partir de ces montants » est une action explicite et déterministe côté navigateur, sans LLM ; la retenue est exclue du TTC brut et réduit seulement le net à payer. Les contrôles non pris en charge, notamment les doublons par numéro et l’exercice fiscal, restent indisponibles/en attente. L’aperçu et les détails restent privés et tenant-scoped ; permissions et blocages sont revalidés au serveur. Les actions donnent des retours toast.

## Vérifications

```bash
npm run build
composer test
```

`npm run build` valide TypeScript/React. `composer test` nécessite PHP 8.3+ et Composer ; les tests Laravel configurent SQLite en mémoire. Les appels OCR et OpenRouter sont simulés avec `Http::fake()` dans les tests — aucune clé réelle n’est nécessaire.

- `tests/Feature/InvoiceIntakeTest.php` : upload, stockage privé, doublons, rôles, isolation et téléchargement.
- `tests/Feature/InvoiceOcrProcessingTest.php` : OCR.space, Mistral, schéma et erreurs OCR/TLS.
- `tests/Feature/OpenRouterInvoicePipelineTest.php` : structuration, audit, blocage complétude, analyse scoppée, corrections, équilibre et validation humaine.

## Structure utile

```text
app/Models/                   Cabinets, utilisateurs, sociétés, factures, propositions et référentiels
app/Policies/                 Autorisations et isolation société
app/Services/Ocr/             OCR.space/Mistral, contrat de facture et cohérence des totaux
app/Services/OpenRouter/      Client OpenRouter strict JSON Schema
app/Services/Invoices/         Extraction, complétude, propositions et persistance métier
app/Jobs/                     OCR, extraction et analyse comptable par facture
app/Http/Controllers/         Auth, cabinet, référentiels, factures et propositions
app/Http/Requests/            Validation serveur des imports et corrections
resources/js/Pages/Invoices/  Workspace Factures
resources/js/Components/      Coquille, InvoiceReviewPanel et InvoiceDocumentViewer
routes/web.php                Routes web authentifiées
database/migrations/          Schémas et états Phase 1–5
docs/requirements.md          Cahier des charges mis à jour
docs/modules/                 PRD/README techniques par module
```
