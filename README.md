# Plateforme AI de saisie comptable automatisée

Application de traitement comptable des factures fournisseurs, destinée aux cabinets gérant plusieurs sociétés. Le cahier des charges et le prompt technique du client sont la source de vérité : [`docs/requirements.md`](docs/requirements.md). La mise en œuvre suit les phases indiquées dans ce document.

## État d’implémentation

Les **Phases 1 à 4** sont implémentées : fondation multi-cabinet, authentification, rôles/affectations société, données comptables scoppées, intake privé des factures et extraction Mistral OCR asynchrone, structurée et auditable. La Phase 4 ajoute files Laravel, états/tentatives, lignes extraites, messages d’échec/relance et avertissements explicites de cohérence des totaux. Les migrations et tests Laravel doivent être exécutés localement avec PHP 8.3+/Composer.

La Phase 2 utilise un jeu de démonstration déterministe de type Sage (12 comptes généraux, 3 comptes analytiques, 3 tiers, 4 journaux et 2 écritures équilibrées par société). Les codes sont stockés en texte, et les montants utilisent trois décimales pour les millimes tunisiens. Aucune base Sage ni aucun fichier `.mae` réel n’a été fourni : la synchronisation/import réel reste différé.

La Phase 4 s’arrête à l’extraction OCR : aucune analyse Mistral Small, proposition comptable en partie double, validation humaine ou écriture comptable n’est encore implémentée (Phase 5). L’OCR utilise une clé Mistral serveur configurable ; les documents ne quittent le stockage privé que dans une requête vers le endpoint configuré. Le connecteur et l’export Sage réel attendent l’inspection du fichier `.mae` et la confirmation de la version Sage.

## Stack

- PHP 8.3+, Laravel 13, Composer
- MySQL pour l’application ; SQLite en mémoire pour les tests
- Inertia.js, React 19, TypeScript, Tailwind CSS
- La Phase 4 utilise la queue Laravel `database` et l’API Mistral OCR (`mistral-ocr-latest` par défaut) ; une clé serveur et un worker actif sont nécessaires pour traiter les jobs.

## Prérequis

- PHP 8.3+ et extensions `pdo_mysql`, `pdo_sqlite` (tests), `mbstring`, `fileinfo`, `openssl` ; Composer
- MySQL 8+ (ou une version compatible avec Laravel 13)
- Node.js 18+ et npm

## Démarrage local

Créez une base MySQL vide (`comptaflow` par défaut), puis depuis la racine :

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
```

Le seeder crée un cabinet d’exemple, trois sociétés fictives, un administrateur et leurs référentiels/écritures comptables de démonstration en environnement `local` ou `testing` uniquement. Une nouvelle société peut charger le même jeu depuis « Données comptables » en environnement local/test, avec un rôle administrateur de cabinet ou gestionnaire de factures. Cette action est masquée et refusée hors de ces environnements. Valeurs locales par défaut : `demo@example.test` / `password`. Changez-les avant d’exposer un environnement ; ne réutilisez jamais ces identifiants en production.

Pour activer l’OCR, configurez `MISTRAL_API_KEY` côté serveur dans `.env`, puis démarrez un worker Laravel dans un terminal distinct :

```bash
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
```

`DB_QUEUE_RETRY_AFTER=300` doit rester supérieur au timeout du job. Sans clé Mistral, les uploads restent privés mais les jobs échouent rapidement avec une erreur visible et peuvent être relancés après configuration. N’exposez jamais la clé au navigateur. Consultez [`docs/modules/invoice-processing/README.md`](docs/modules/invoice-processing/README.md) pour le détail de l’API, des états et du contrôle des totaux.

Lancez Vite et Laravel dans deux terminaux :

```bash
npm run dev
php artisan serve --host=0.0.0.0
```

Ouvrez l’URL affichée par Artisan. L’administrateur peut créer des sociétés et des comptes depuis l’interface. Pour créer le premier cabinet en production, utilisez `php artisan cabinet:create` (invite interactive, mot de passe masqué et minimum 12 caractères). Pour ajouter un utilisateur en ligne de commande à un cabinet existant : `php artisan users:create {slug-du-cabinet}`.

#### Dépannage Vite (`ERR_ADDRESS_INVALID`)

Le serveur Vite de l’application écoute sur `127.0.0.1:5173` en port strict. `VITE_DEV_SERVER_ORIGIN` configure l’origine des assets et vaut `http://127.0.0.1:5173` par défaut ; cette valeur doit être joignable par le navigateur (ne définissez pas l’origine à `http://0.0.0.0:5173`). Après changement, arrêtez Vite ; si `public/hot` subsiste alors que Vite est arrêté, supprimez ce fichier puis relancez `npm run dev`. L’aperçu `preview:ui` est un serveur séparé et écoute sur `0.0.0.0`.

## Retours d’action

Les succès des mutations (connexion/déconnexion, sociétés, profils et accès utilisateurs) affichent un toast Sonner ; les erreurs de validation et les réponses réseau/serveur inattendues affichent un toast d’erreur. L’import de factures confirme les réussites et signale les fichiers refusés individuellement sans annuler les autres. La liste des utilisateurs masque entièrement la section « Accès société » lorsque le rôle choisi est `cabinet_admin`.

## Intake et OCR des factures (Phases 3–4)

Depuis « Factures » sur une société, un administrateur de cabinet ou gestionnaire autorisé peut sélectionner jusqu’à 300 fichiers. Chaque PDF/JPG/JPEG/PNG (20 Mo max.) est envoyé séparément, validé côté serveur et enregistré dans `storage/app/private` avec un SHA-256 ; un hash déjà présent dans la même société exige une confirmation. Les utilisateurs société autorisés peuvent consulter et télécharger les documents privés, sans pouvoir importer s’ils n’ont pas le rôle requis.

Chaque nouvel import est mis en file sur `ocr` après persistance. Le worker envoie le document au Mistral OCR configurable, valide le JSON strict, puis persiste la réponse brute, les champs et les lignes extraits, le modèle/usage ainsi que les erreurs/tentatives. L’interface actualise l’avancement et permet une relance autorisée. Les totaux incohérents ou non vérifiables sont signalés ; aucun montant n’est corrigé automatiquement. Les factures importées sous l’ancien état `uploaded` peuvent être lancées manuellement depuis l’interface.

Les résultats OCR nécessitent une vérification humaine ; aucune analyse Mistral Small, proposition comptable ou écriture validée n’est créée (Phase 5). Les anciennes tables du prototype sont conservées sans migration automatique vers le nouveau schéma. La documentation détaillée est dans [`docs/modules/documents/`](docs/modules/documents/) et [`docs/modules/invoice-processing/`](docs/modules/invoice-processing/).

### Aperçu UI sans PHP/MySQL

```bash
npm run preview:ui
```

Cet aperçu Vite est indépendant du backend et utilise des données fictives en mémoire pour présenter le tableau de bord multi-société et les écrans de gestion. Il ne sauvegarde rien et ne contacte ni Sage ni Mistral.

## Vérifications

```bash
npm run build
composer test
```

Les tests Laravel configurent SQLite en mémoire. Les tests OCR simulent les réponses Mistral avec `Http::fake()` et ne nécessitent aucune clé réelle. Dans cet environnement PHP/Composer doit être disponible pour exécuter `composer test`.

## Structure utile

```text
app/Models/                 Cabinet, User, Company, factures et référentiels/écritures
app/Policies/               Autorisations d’accès aux sociétés et données métier
app/Services/AccountingData/ Service idempotent des données comptables d’exemple
app/Services/Invoices/      Stockage privé et persistance des imports de facture
app/Services/Ocr/           Contrat Mistral, schéma structuré et cohérence des totaux
app/Jobs/                   Traitement OCR indépendant par facture
app/Http/Controllers/       Auth, tableau de bord, société, comptabilité et factures
app/Http/Requests/          Validation serveur des formulaires et uploads
resources/js/Pages/         Pages Inertia React, dont AccountingData/Show et Invoices/Index
resources/js/Components/    Coquille, navigation, toasts et composants partagés
resources/css/app.css       Tailwind et styles applicatifs
database/migrations/        Schémas Phase 1–3 et migrations historiques/compatibilité
database/seeders/           Cabinet et référentiels fictifs local/test
docs/requirements.md        Cahier des charges fourni
docs/modules/               PRD/README techniques par module
```

Les migrations historiques créent encore les tables `documents` et `accounting_entries` du premier prototype, mais leurs modèles et routes ont été retirés. La Phase 3 introduit le nouveau schéma `invoices`/`invoice_lines` sans modifier les anciennes tables. Aucune donnée historique n’est copiée ou supprimée automatiquement ; toute migration ou suppression future devra être explicite et précédée d’une sauvegarde.
