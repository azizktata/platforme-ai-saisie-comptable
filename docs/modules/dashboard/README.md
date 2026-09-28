# Tableau de bord cabinet — documentation technique

- `DashboardController` construit la liste via `cabinet_id` pour un admin ou la relation many-to-many `companies()` pour un membre.
- `CompanyPolicy::viewAny` vérifie qu’un cabinet existe ; la liste est filtrée avant sérialisation Inertia.
- `resources/js/Pages/Dashboard.tsx` affiche les sociétés, états vides et navigation vers les données comptables par société.
- La déconnexion déclenche un toast de résultat via Sonner.
- `resources/js/Components/AppShell.tsx` expose des liens fixes vers le tableau de bord, les sociétés, les factures et les données comptables ; les liens d’administration du cabinet/utilisateurs ne sont visibles que par un admin.
- Les espaces `/invoices` et `/accounting-data` appliquent leur propre sélecteur de société à partir des sociétés autorisées, sans transformer l’accès à la navigation en autorisation métier.
- `tests/Feature/DashboardTest.php` vérifie la portée société pour membres et administrateurs.
