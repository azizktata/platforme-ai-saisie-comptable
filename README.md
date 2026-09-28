# ComptaFlow

Application de saisie et de contrôle des factures fournisseurs, construite avec Laravel 12, Inertia, React et TypeScript.

> **État des exigences.** Le dépôt fourni ne contient que le README initial : aucun cahier des charges, ERD ou fichier de workflow n’était présent dans le checkout. L’application ci-dessous est donc un MVP fondé sur le nom du projet et la stack explicitement demandée. Les hypothèses et limites sont consignées dans [`PRD.md`](PRD.md) et [`docs/architecture.md`](docs/architecture.md). En particulier, aucune extraction par IA n’est simulée ou présentée comme réelle : le fournisseur et le contrat d’IA n’étant pas spécifiés, les champs sont vérifiés et complétés manuellement.

## Modules livrés

- **Authentification** : connexion par session, protection des routes et isolement des documents par compte.
- **Vue d’ensemble** : indicateurs, évolution des dépenses et documents récents.
- **Documents et écritures** : import privé de factures, saisie/correction des informations, puis comptabilisation idempotente.

Chaque module possède son PRD fonctionnel et sa documentation technique dans [`docs/modules`](docs/modules/).

## Prérequis

- PHP 8.2 ou supérieur, Composer, extensions PHP `pdo_sqlite`, `fileinfo`, `mbstring` et `openssl`.
- Node.js 18 ou supérieur et npm.
- SQLite pour le démarrage local (MySQL peut être configuré via `config/database.php`).

## Démarrage local

Depuis la racine du dépôt :

```bash
cp .env.example .env
composer install
npm install
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
```

Dans un premier terminal, lancez Vite :

```bash
npm run dev
```

Dans un second terminal, lancez Laravel :

```bash
php artisan serve --host=0.0.0.0
```

Ouvrez l’URL affichée par Artisan. Le seeder local crée le compte de démonstration défini dans `.env` : `demo@example.test` / `password` par défaut. **Changez ce mot de passe avant d’exposer un environnement** ; le seeder ne crée aucun compte en environnement `production`.

Il n’y a volontairement ni inscription publique ni réinitialisation de mot de passe dans ce MVP. Pour créer un compte depuis la console, utilisez `php artisan users:create` (invite interactive, mot de passe masqué et minimum 12 caractères).

### Aperçu front-end sans PHP

Pour ouvrir uniquement l’interface de démonstration, lancez `npm run preview:ui` et ouvrez l’URL Vite. Cet aperçu React autonome utilise des données fictives en mémoire ; il permet d’essayer la navigation, les filtres, l’import simulé et la revue d’une facture sans PHP. Les changements ne sont pas persistés. `npm run dev` est le serveur Vite de l’application Laravel, à utiliser avec `php artisan serve` comme indiqué plus haut.

## Vérifications

```bash
npm run build
composer test
```

## Structure utile

```text
app/Http/Controllers/       Contrôleurs Inertia et authentification
app/Http/Requests/          Validation des formulaires
app/Models/                 User, Document et AccountingEntry
app/Policies/                Autorisations sur les documents
app/Services/                Règle transactionnelle de comptabilisation
database/migrations/        Schéma SQLite/MySQL
database/seeders/           Jeu de démonstration local
resources/js/Components/    Vues React réutilisables
resources/js/Pages/         Pages Inertia
resources/js/PreviewApp.tsx Aperçu autonome, données fictives seulement
docs/modules/               PRD et README techniques par module
```

## Limites connues du MVP

- Les documents importés sont stockés sur le disque privé local ; prévoir un stockage chiffré et sauvegardé avant une mise en production.
- L’import initialise une facture en statut **À vérifier**. OCR, extraction IA, suggestions de compte, exports et intégrations comptables ne sont pas implémentés faute de spécification.
- Une écriture représente ici une facture fournisseur résumée (HT, TVA, TTC, compte). Ce n’est pas encore un journal en partie double ni un grand livre.
- La devise est limitée à EUR dans ce MVP et le contrôle de comptabilisation vérifie `HT + TVA = TTC` à un centime près.

Voir [`docs/architecture.md`](docs/architecture.md) pour les décisions techniques et le modèle de données.
