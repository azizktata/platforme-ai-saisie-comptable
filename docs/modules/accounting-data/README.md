# Référentiels comptables — documentation technique (Phase 2)

## Schéma et modèles

- Migrations `000009`–`000014` créent `chart_accounts`, `analytical_accounts`, `third_parties`, `journals`, `journal_entries` et `journal_entry_lines`.
- Modèles Eloquent : `ChartAccount`, `AnalyticalAccount`, `ThirdParty`, `Journal`, `JournalEntry`, `JournalEntryLine`; `Company` expose les relations dédiées.
- Les codes externes sont `string` et indexés/uniques par `company_id`. Les montants débit/crédit sont `DECIMAL(18,3)` pour conserver les millimes tunisiens.
- Les clés étrangères limitent les suppressions orphelines et les écritures chargent leurs lignes, comptes, tiers et axes analytiques sans N+1.

## Parcours et autorisation

- `GET /companies/{company}/accounting-data` (`companies.accounting-data`) rend `AccountingData/Show`. La policy `CompanyPolicy::view` protège la société avant toute lecture.
- Les requêtes sont construites depuis les relations de la société autorisée ; une société non affectée ou située dans un autre cabinet retourne `403`.
- Les cinq listes sont paginées avec des noms de paramètres indépendants et `withQueryString` (25 références par page, 10 écritures) ; les relations d’écritures sont eager-loadées.
- `POST /companies/{company}/accounting-data/demo` (`companies.accounting-data.demo`) appelle `SeedDemoAccountingData`. `manageAccountingData` réserve l’action aux admins/gestionnaires autorisés et l’application refuse la route hors de `local`/`testing`.
- La page est consultable par les utilisateurs société mais les référentiels et écritures restent en lecture seule à cette phase.

## Données d’exemple

`SeedDemoAccountingData` fonctionne dans une transaction et utilise `updateOrCreate` sur les clés société/code, société/journal/référence et écriture/numéro de ligne. Il conserve les références textuelles telles que `000012` et `6A1000` et crée des tiers tunisiens, des journaux ACH/VTE/BQ/OD et deux écritures équilibrées en TND.

`AccountingDataSeeder` charge le jeu dans les sociétés du `demo-cabinet`; `DatabaseSeeder` ne s’exécute que dans les environnements `local`/`testing`. La route de démonstration permet de peupler une nouvelle société locale sans importer de fichier réel.

## Vérifications

`tests/Feature/AccountingDataTest.php` couvre la répétabilité, les codes avec zéros initiaux, l’équilibre, la consultation scoppée et les droits de chargement du jeu d’exemple. Les tests Laravel doivent être exécutés depuis un environnement avec PHP/Composer installés.

Aucun lecteur `.mae`, pilote Sage, export ou service IA n’est inclus.
