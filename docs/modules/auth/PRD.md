# PRD — Authentification et accès cabinet

## Objectif

Protéger l’accès à la plateforme multi-cabinet et garantir qu’un compte authentifié reste rattaché à son cabinet et aux sociétés autorisées.

## Comportement

- Connexion par e-mail et mot de passe via session Laravel ; les e-mails sont normalisés en minuscules.
- Routes métier réservées aux utilisateurs authentifiés ; l’identifiant de session est renouvelé à la connexion et invalidé à la déconnexion.
- Les tentatives de connexion sont limitées.
- Chaque compte appartient à un cabinet (`users.cabinet_id`). Les rôles cabinet et société sont contrôlés séparément.
- Les comptes sont provisionnés par un administrateur de cabinet ou par commande sécurisée ; aucune inscription publique n’est prévue dans le périmètre de Phase 1.
- Les rôles d’accès société sont `invoice_manager` et `company_user`; `cabinet_admin` gère le cabinet.

## Hors périmètre actuel

Invitations par e-mail, récupération de mot de passe, SSO, MFA et gestion avancée du cycle de vie des sessions. Ils ne doivent pas être déclarés disponibles tant qu’ils ne sont pas implémentés.
