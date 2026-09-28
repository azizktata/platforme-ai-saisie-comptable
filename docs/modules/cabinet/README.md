# Cabinets et utilisateurs — documentation technique

- Modèles : `Cabinet`, `User`, `CabinetActivity`; fabrique de tests : `CabinetFactory` et `UserFactory`.
- La relation `User::companies()` utilise la table pivot `company_user_access`, avec `role` et timestamps. `User::cabinet_role` porte `cabinet_admin` ou `member`.
- `CompanyPolicy` centralise l’accès au cabinet/société ; `CabinetPolicy` réserve la mise à jour du cabinet et la gestion des utilisateurs/activités aux administrateurs de ce cabinet.
- `CabinetUserController`, `StoreCabinetUserRequest` et `UpdateCabinetUserRequest` gèrent les membres et refusent les affectations d’une société située dans un autre cabinet. `resources/js/Pages/Cabinet/Users/Index.tsx` masque entièrement « Accès société » quand le rôle courant est `cabinet_admin`; les contrôleurs effacent aussi les affectations résiduelles.
- `CabinetSettingsController` et `UpdateCabinetRequest` gèrent `GET/PATCH /cabinet/settings`. Seuls `name` et `slug` sont éditables ; le slug est validé comme identifiant alphanumérique avec tirets/underscores et unique.
- La migration `2026_09_28_000019` crée `cabinet_activities`, rattachée au cabinet et supprimée en cascade avec lui. `CabinetActivityController` / `StoreCabinetActivityRequest` permettent aux admins d’ajouter les choix personnalisés depuis le sélecteur des sociétés ; les options sont ensuite limitées à ce cabinet.
- La liste d’activités prédéfinies est dans `config/company_activities.php`. La déduplication et la validation serveur restent actives même si un client contourne le sélecteur React.
- `cabinet:create` crée le premier administrateur ; le seeder de développement ne crée que des données fictives. Les succès/échecs des mutations utilisent Sonner.
- Tests associés : `tests/Feature/CompanyManagementTest.php`, `tests/Feature/CabinetSettingsTest.php` et `tests/Feature/CabinetActivityTest.php`.
