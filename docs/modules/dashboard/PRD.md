# PRD — Tableau de bord cabinet (Phase 1)

## Objectif

Donner à l’utilisateur une vue de son périmètre multi-société sans révéler d’entreprise non autorisée.

## Comportement

- Un administrateur voit toutes les sociétés de son cabinet.
- Un membre voit uniquement les sociétés liées à son compte dans `company_user_access`.
- Chaque carte affiche les informations de profil disponibles et le rôle/volume d’accès pertinent, et permet d’ouvrir les données comptables de la société.
- La déconnexion affiche un toast de succès/échec après la réponse serveur.
- Les états vides orientent un administrateur vers la création de société et un collaborateur vers une demande d’accès.
- Le nombre de factures, les scores OCR, les économies de temps et les écritures seront ajoutés avec les phases facture/IA ; ils ne sont pas simulés dans les métriques serveur de Phase 1.
