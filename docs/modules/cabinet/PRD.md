# PRD — Cabinets et utilisateurs (Phase 1)

## Objectif

Permettre à un cabinet de gérer plusieurs sociétés et comptes utilisateur, sans imposer une relation utilisateur–société unique.

## Règles fonctionnelles

- Un cabinet possède plusieurs utilisateurs et plusieurs sociétés.
- Un utilisateur est rattaché à un cabinet et ne peut accéder qu’aux sociétés de ce cabinet.
- `cabinet_admin` peut créer des sociétés, provisionner/modifier les comptes utilisateur et affecter les rôles du cabinet.
- Un membre reçoit une ou plusieurs lignes `company_user_access`, chacune avec le rôle `invoice_manager` ou `company_user`; l’administrateur peut modifier ces affectations.
- Le dernier administrateur d’un cabinet ne peut pas être rétrogradé tant qu’un autre administrateur n’a pas été désigné.
- La suppression de société/utilisateur n’est pas offerte dans cette phase afin de ne pas créer de problème de rétention ou de perte de données.
- La création de comptes via l’interface impose un mot de passe initial de 12 caractères minimum. Une livraison d’invitation/remplacement de mot de passe est à spécifier.
