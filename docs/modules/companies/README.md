# Sociétés — documentation technique

- `Company` appartient à `Cabinet`; les utilisateurs autorisés sont obtenus via `company_user_access`.
- La migration ajoute un index unique `(cabinet_id, name)` et des champs profil/Sage externes sans imposer de format propriétaire.
- `CompanyController@index` choisit une requête cabinet pour les admins et une relation pivot pour les membres, puis fournit les options d’activité autorisées.
- `config/company_activities.php` contient les choix prédéfinis ; `CabinetActivity` ajoute les choix propres au cabinet. `StoreCompanyRequest` et `UpdateCompanyRequest` valident côté serveur l’activité et normalisent les codes ISO.
- `resources/js/Components/CompanyActivitySelect.tsx` partage le sélecteur entre création et édition. Un admin peut y ajouter une activité au catalogue ; un membre ne peut que sélectionner une option existante.
- La page Inertia `Companies/Index.tsx` expose la liste et la création ; `CompanyProfileCard` permet l’édition du profil et ouvre les espaces comptable et facture. La suppression n’est pas exposée.
- Les retours des créations et modifications utilisent Sonner (`onSuccess`/`onError`) afin de donner un feedback pour chaque action.
- Tests associés : `tests/Feature/CompanyManagementTest.php` et `tests/Feature/CabinetActivityTest.php`.
