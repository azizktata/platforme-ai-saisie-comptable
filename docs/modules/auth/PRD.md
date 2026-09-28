# PRD — Authentification

## Objectif
Protéger le tableau de bord, les documents et les factures stockées, sans inscription publique dans ce premier lot.

## Parcours et comportement

1. Un invité qui demande une route métier est redirigé vers `/login`.
2. L’utilisateur saisit son e-mail et son mot de passe. L’e-mail est normalisé en minuscules.
3. Après cinq échecs, les tentatives sont limitées temporairement par clé e-mail + IP.
4. Une connexion valide régénère l’identifiant de session, puis redirige vers la page demandée ou le tableau de bord.
5. La déconnexion invalide la session et renouvelle le jeton CSRF.
6. Chaque document appartient au compte qui l’a importé. Un autre compte reçoit une réponse interdite sur les actions de lecture, modification, téléchargement et comptabilisation.

## Règles métier

- Aucun endpoint public de création de compte.
- Les comptes sont créés par `php artisan users:create` ; le prompt masque le mot de passe et impose 12 caractères minimum.
- Le compte de démonstration `demo@example.test` / `password` n’est créé que par le seeder en `local` et `testing`. Il doit être remplacé avant toute utilisation partagée.
- Aucun rôle, invitation, MFA ou processus de récupération de mot de passe n’est inclus.

## Critères d’acceptation

- Les invités ne peuvent pas accéder aux routes métier.
- Les identifiants corrects ouvrent une session ; les identifiants incorrects produisent une erreur de formulaire.
- La déconnexion rend la session inutilisable.
- Les documents d’un autre utilisateur ne peuvent pas être consultés.
