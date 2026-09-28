# Sociétés — documentation technique

- `Company` appartient à `Cabinet`; les utilisateurs autorisés sont obtenus via `company_user_access`.
- La migration ajoute un index unique `(cabinet_id, name)` et des champs profil/Sage externes sans imposer de format propriétaire.
- `CompanyController@index` choisit une requête cabinet pour les admins et une relation pivot pour les membres.
- `StoreCompanyRequest` / `UpdateCompanyRequest` autorisent uniquement l’admin, normalisent les codes ISO et valident l’unicité par cabinet.
- La page Inertia `Companies/Index.tsx` expose la liste et la création ; `CompanyProfileCard` permet l’édition du profil. La suppression n’est pas exposée.
