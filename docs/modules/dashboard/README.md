# Module Vue d’ensemble

## Rôle
Page d’accueil Inertia montrant les mesures calculées côté Laravel, les six factures récentes et des raccourcis vers la revue et l’import.

## Structure

- `app/Http/Controllers/DashboardController.php` effectue les agrégations par utilisateur et prépare les points mensuels.
- `resources/js/Pages/Dashboard.tsx` adapte les props Inertia et branche l’import à `POST /documents`.
- `resources/js/Components/DashboardView.tsx` contient la mise en page, les KPI, le graphique et l’état d’import.
- `resources/js/Components/DocumentTable.tsx` et `StatusBadge.tsx` sont partagés avec le module Documents.

## Données renvoyées

`stats` comprend `to_review`, `posted_this_month`, `expenses_this_month` et `documents_this_month`. Les mesures d’écriture et la courbe mensuelle utilisent `accounting_entries.created_at` (date de comptabilisation) ; la date d’écriture comptable `entry_date` reste celle de la facture. `recentDocuments` contient jusqu’à six documents ; `monthlySpend` contient six paires `{ label, amount }`.

Les agrégations d’écritures filtrent par `document.user_id` afin de ne pas exposer les données d’un autre compte. Le graphique est un composant CSS/React léger ; aucune bibliothèque de graphiques ou requête cliente séparée n’est requise.

## Aperçu autonome

`resources/js/PreviewApp.tsx` monte les mêmes composants visuels avec `demoData.ts` et un état React local. Cet aperçu sert à valider l’interface, mais ne teste pas les agrégations Laravel et ne conserve pas les changements au rechargement.
