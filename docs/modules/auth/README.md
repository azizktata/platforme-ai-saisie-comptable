# Module Authentification

## Rôle
Connexion locale par session Laravel. Il n’y a pas d’inscription publique ; les routes `/`, `/documents` et les routes de fichier sont protégées par le middleware `auth`.

## Fichiers importants

- `app/Http/Controllers/AuthenticatedSessionController.php` : affiche le formulaire, authentifie, renouvelle la session et déconnecte.
- `app/Http/Requests/LoginRequest.php` : normalise l’e-mail, valide les champs et limite les échecs à cinq tentatives.
- `app/Models/User.php` : compte et relation `documents()`.
- `app/Policies/DocumentPolicy.php` : vérifie la propriété du document.
- `app/Http/Middleware/HandleInertiaRequests.php` : partage l’utilisateur minimal (`id`, nom, e-mail) et le message flash avec React.
- `resources/js/Pages/Auth/Login.tsx` : formulaire Inertia de connexion.
- `routes/console.php` : commande interactive de provisionnement `users:create`.

## Mise en route

En local, `php artisan migrate --seed` crée un compte de démonstration à partir de `DEMO_USER_EMAIL` et `DEMO_USER_PASSWORD`. Ce compte n’est créé que pour `local` et `testing`.

Pour créer un compte d’installation depuis la ligne de commande :

```bash
php artisan users:create
```

La commande demande un nom, un e-mail unique et un mot de passe (12 caractères minimum). Il n’y a pas de mécanisme de récupération : l’administration des comptes reste volontairement limitée dans ce périmètre.

## Tests

`tests/Feature/AuthenticationTest.php` vérifie la redirection des invités, la connexion et la déconnexion. `tests/Feature/DocumentWorkflowTest.php` vérifie qu’un compte ne peut pas ouvrir la facture d’un autre.
