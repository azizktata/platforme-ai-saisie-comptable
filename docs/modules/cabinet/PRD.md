# PRD — Cabinets et utilisateurs (Phase 1)

## Objectif

Permettre à un cabinet de gérer plusieurs sociétés et comptes utilisateur, sans imposer une relation utilisateur–société unique, et de modifier les informations de son propre espace.

## Règles fonctionnelles

- Un cabinet possède plusieurs utilisateurs, sociétés et activités personnalisées.
- Un utilisateur est rattaché à un cabinet et ne peut accéder qu’aux sociétés de ce cabinet.
- Un `cabinet_admin` peut créer des sociétés, provisionner/modifier les comptes utilisateur et affecter les rôles du cabinet. L’administration ne dépend pas d’affectations société.
- Un membre reçoit une ou plusieurs lignes `company_user_access`, chacune avec le rôle `invoice_manager` ou `company_user`; l’administrateur peut modifier ces affectations. Lorsque le rôle choisi est `cabinet_admin`, le formulaire masque entièrement la section « Accès société » et les affectations résiduelles sont effacées côté serveur.
- Le dernier administrateur d’un cabinet ne peut pas être rétrogradé tant qu’un autre administrateur n’a pas été désigné.
- Un administrateur peut modifier le nom et le slug du cabinet depuis « Cabinet ». Le slug est normalisé et reste unique ; les autres champs ne sont pas modifiables dans cette phase.
- Un administrateur peut ajouter des activités spécifiques à son cabinet depuis le sélecteur d’activité d’une société. Les doublons des choix prédéfinis ou du catalogue du même cabinet sont refusés. Ces options ne sont pas partagées entre cabinets.
- Les créations/modifications et leurs erreurs affichent un toast après la réponse du serveur.
- La suppression de cabinet, société ou utilisateur n’est pas offerte afin de ne pas créer de problème de rétention ou de perte de données.
- L’inscription d’un cabinet et de son premier administrateur est décrite dans le module Authentification.

## Hors périmètre actuel

Facturation des cabinets, invitations par e-mail, permissions personnalisables, historique complet d’audit des changements et gestion avancée du cycle de vie des comptes.
