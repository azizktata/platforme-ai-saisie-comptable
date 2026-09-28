# PRD — Sociétés et profil entreprise (Phase 1)

## Objectif

Fournir un périmètre société explicite pour les référentiels comptables et traitements IA, et permettre de retrouver les activités de chaque entreprise via un catalogue cohérent.

## Champs initiaux

Nom d’usage, raison sociale, matricule fiscal, activité, secteur, code pays, devise et identifiant externe Sage facultatif. Les informations peuvent être incomplètes au démarrage et enrichies avant les phases comptables.

## Comportement et accès

- Un administrateur crée une société dans son cabinet et peut modifier son profil ; la suppression reste désactivée.
- Les membres ne voient que les sociétés affectées ; les noms d’usage sont uniques par cabinet.
- Le champ « Activité principale » est un sélecteur alimenté par les choix de `config/company_activities.php` et les activités personnalisées du cabinet.
- Seul un administrateur de cabinet peut ajouter un choix personnalisé. Celui-ci devient disponible pour les sociétés de ce cabinet, mais pas pour les autres cabinets.
- Le serveur n’accepte que les choix prédéfinis, les options propres au cabinet et, lors de la modification, l’activité déjà enregistrée de cette société.
- Les codes ISO pays/devise sont normalisés en majuscules.
- Les créations/modifications affichent un toast de succès ou d’échec. Les utilisateurs disposant d’un accès société peuvent ouvrir « Données comptables » et « Factures » depuis la carte de profil.
- Le connecteur Sage et l’import comptable réel restent différés jusqu’à inspection du format disponible.
