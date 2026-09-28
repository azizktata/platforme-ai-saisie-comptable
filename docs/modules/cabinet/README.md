# Cabinets et utilisateurs — documentation technique

- Modèles : `Cabinet`, `User`; fabrique de tests : `CabinetFactory` et `UserFactory`.
- La relation `User::companies()` utilise la table pivot `company_user_access`, avec `role` et timestamps.
- `User::cabinet_role` porte `cabinet_admin` ou `member`. `CompanyPolicy` centralise l’accès au cabinet/société.
- `CabinetUserController` expose la liste, l’ajout et la mise à jour des membres ; `StoreCabinetUserRequest` et `UpdateCabinetUserRequest` valident les rôles et refusent une société d’un autre cabinet.
- `resources/js/Pages/Cabinet/Users/Index.tsx` masque le fieldset « Accès société » lorsque le rôle choisi est `cabinet_admin`; les contrôleurs vident également les affectations de cet administrateur côté serveur. Un membre peut recevoir des accès par société. Les succès/échecs des mutations sont signalés avec Sonner.
- La dernière personne administratrice ne peut pas être rétrogradée ; la mise à jour de rôle et d’accès est vérifiée côté serveur.
- `cabinet:create` crée le premier administrateur ; le seeder de développement ne crée que des données fictives.
- Tests : `tests/Feature/CompanyManagementTest.php` couvre la création, le périmètre d’accès et l’affectation de rôles.
