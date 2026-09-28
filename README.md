# Plateforme AI de saisie comptable automatisée

Application de traitement comptable des factures fournisseurs, destinée aux cabinets gérant plusieurs sociétés. Le cahier des charges et le prompt technique du client sont la source de vérité : [`docs/requirements.md`](docs/requirements.md). La mise en œuvre suit les phases indiquées dans ce document.

## État d’implémentation

La **Phase 1 (fondation)** est implémentée : authentification, cabinets, sociétés, utilisateurs, rôles et affectations d’accès. Elle comprend un tableau de bord par cabinet, la création/modification des sociétés et la création/modification des comptes et accès. L’exécution du backend doit encore être validée localement avec la stack PHP/MySQL.

Les Phases 2 à 5 restent à implémenter : référentiels comptables mockés/importés, factures et lignes, OCR asynchrone Mistral, analyse Mistral Small, proposition en partie double et validation humaine. La présence d’une variable `MISTRAL_API_KEY` dans `.env.example` ne signifie pas qu’un appel IA est actuellement effectué. Le connecteur et l’export Sage réel attendent l’inspection du fichier `.mae` et la confirmation de la version Sage.

## Stack

- PHP 8.3+, Laravel 13, Composer
- MySQL pour l’application ; SQLite en mémoire pour les tests
- Inertia.js, React 19, TypeScript, Tailwind CSS
- Les queues Laravel et les fournisseurs Mistral seront activés aux phases de traitement asynchrone.

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

Le seeder crée un cabinet d’exemple, trois sociétés fictives et un administrateur en environnement `local` ou `testing` uniquement. Valeurs locales par défaut : `demo@example.test` / `password`. Changez-les avant d’exposer un environnement ; ne réutilisez jamais ces identifiants en production.

Lancez Vite et Laravel dans deux terminaux :

```bash
npm run dev
php artisan serve --host=0.0.0.0
```

Ouvrez l’URL affichée par Artisan. L’administrateur peut créer des sociétés et des comptes depuis l’interface. Pour créer le premier cabinet en production, utilisez `php artisan cabinet:create` (invite interactive, mot de passe masqué et minimum 12 caractères). Pour ajouter un utilisateur en ligne de commande à un cabinet existant : `php artisan users:create {slug-du-cabinet}`.

#### Dépannage Vite (`ERR_ADDRESS_INVALID`)

Vite écoute sur `0.0.0.0` afin d’accepter les connexions, mais le navigateur doit charger les assets depuis une adresse routable. `VITE_DEV_SERVER_ORIGIN` vaut `http://127.0.0.1:5173` par défaut. Ne le définissez pas à `http://0.0.0.0:5173`. Après changement de cette valeur, arrêtez Vite ; si `public/hot` subsiste alors que Vite est arrêté, supprimez ce fichier puis relancez `npm run dev`.

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

Les tests Laravel configurent SQLite en mémoire. Pour les phases futures, les tests de services/jobs IA devront utiliser des réponses HTTP simulées et ne doivent pas nécessiter une clé Mistral réelle.

## Structure utile

```text
app/Models/                 Cabinet, Company, User
app/Policies/               Autorisations d’accès aux sociétés
app/Http/Controllers/       Auth, tableau de bord, gestion cabinet/sociétés
app/Http/Requests/          Validation serveur
resources/js/Pages/         Pages Inertia React
resources/js/Components/    Coquille et composants partagés
resources/css/app.css       Tailwind et styles applicatifs
database/migrations/        Schéma et migrations de compatibilité
database/seeders/           Cabinet local fictif
docs/requirements.md        Cahier des charges fourni
docs/modules/               PRD/README techniques par module
```

Les migrations historiques créent encore les tables `documents` et `accounting_entries` du premier prototype, mais leurs modèles et routes ont été retirés. Elles ne représentent pas le futur schéma facture ; la Phase 3 introduira explicitement `invoices`/`invoice_lines` et traitera la migration ou suppression de ces anciennes données sans perte silencieuse.
