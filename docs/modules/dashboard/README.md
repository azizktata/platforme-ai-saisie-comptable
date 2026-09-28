# Tableau de bord cabinet — documentation technique

- `DashboardController` construit la liste via `cabinet_id` pour un admin ou la relation many-to-many `companies()` pour un membre.
- `CompanyPolicy::viewAny` vérifie qu’un cabinet existe ; la liste est filtrée avant sérialisation Inertia.
- `resources/js/Pages/Dashboard.tsx` affiche les sociétés, états vides et navigation.
- `resources/js/Components/AppShell.tsx` construit les liens d’administration uniquement pour un admin.
- `tests/Feature/DashboardTest.php` vérifie la portée société pour membres et administrateurs.
