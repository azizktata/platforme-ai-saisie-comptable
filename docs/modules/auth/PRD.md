# PRD — Authentification et accès cabinet

## Objectif

Protéger l’accès à la plateforme multi-cabinet, proposer un démarrage autonome d’un cabinet et garantir qu’un compte reste rattaché à son cabinet et aux sociétés autorisées.

## Comportement

- Connexion par e-mail et mot de passe via session Laravel ; les e-mails sont normalisés en minuscules et les tentatives de connexion sont limitées.
- Les routes métier sont réservées aux utilisateurs authentifiés ; l’identifiant de session est renouvelé à la connexion et invalidé à la déconnexion.
- Un visiteur peut s’inscrire sur `/register`. Une transaction crée le cabinet et son premier compte `cabinet_admin`, puis connecte ce compte. Le nom du cabinet détermine un slug unique ; un suffixe est ajouté si nécessaire.
- L’inscription publique exige un mot de passe confirmé d’au moins 12 caractères et est limitée à cinq tentatives par minute.
- Les autres comptes sont provisionnés par un administrateur du cabinet ou par la commande sécurisée existante.
- Chaque compte appartient à un cabinet (`users.cabinet_id`). Les rôles cabinet et société sont contrôlés séparément ; `invoice_manager` et `company_user` sont des rôles d’accès société, tandis que `cabinet_admin` gère son cabinet.
- Les mutations de connexion, inscription et déconnexion fournissent un toast de succès ou d’échec ; les erreurs réseau/Inertia globales ont également un retour visuel.

## Hors périmètre actuel

Invitations par e-mail, vérification d’adresse, récupération de mot de passe, SSO, MFA et gestion avancée du cycle de vie des sessions. Ils ne doivent pas être déclarés disponibles tant qu’ils ne sont pas implémentés.
