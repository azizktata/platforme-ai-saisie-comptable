# Authentification — documentation technique

- `AuthenticatedSessionController` et `LoginRequest` gèrent la session, la normalisation d’e-mail et le rate limit.
- `HandleInertiaRequests` partage l’utilisateur, le cabinet et le droit d’administration sans exposer de secrets.
- `ToastHost` est monté au niveau de l’entrée Inertia ; `RequestToastEvents` affiche les exceptions réseau et réponses invalides. Login/logout affichent un toast après chaque réponse via leurs callbacks d’action.
- Les routes métier sont groupées derrière `auth` dans `routes/web.php`.
- `cabinet:create` crée transactionnellement un cabinet et son premier administrateur ; `users:create {slug}` provisionne un compte dans un cabinet connu.
- Les factories/tests associent chaque compte à un `Cabinet`.
- Les règles d’accès société résident dans `CompanyPolicy` et les requêtes scoped, pas dans la seule interface React.
