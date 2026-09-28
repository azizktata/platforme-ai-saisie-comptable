# Authentification — documentation technique

- `AuthenticatedSessionController` et `LoginRequest` gèrent la session, la normalisation d’e-mail et la limitation des tentatives de connexion.
- `RegisteredCabinetController` et `RegisterCabinetRequest` exposent `GET/POST /register` derrière le middleware `guest`. La création du cabinet et de son premier administrateur est transactionnelle ; l’inscription est limitée à cinq requêtes par minute et exige un mot de passe confirmé de 12 caractères minimum.
- Les slugs générés à l’inscription sont uniques ; un suffixe numérique est choisi si le slug de base est déjà utilisé.
- `HandleInertiaRequests` partage l’utilisateur, le cabinet et le droit d’administration sans exposer de secrets.
- `ToastHost` est monté au niveau de l’entrée Inertia ; `RequestToastEvents` affiche les exceptions réseau et réponses invalides. Login/logout/inscription affichent aussi un toast via leurs callbacks.
- Les routes métier sont groupées derrière `auth` dans `routes/web.php`.
- `cabinet:create` crée également un cabinet et son premier administrateur depuis la ligne de commande ; `users:create {slug}` provisionne un compte dans un cabinet existant.
- Les règles d’accès société résident dans `CompanyPolicy` et les requêtes scoped, pas dans la seule interface React.
- Tests associés : `tests/Feature/AuthenticationTest.php` et `tests/Feature/CabinetRegistrationTest.php`.
