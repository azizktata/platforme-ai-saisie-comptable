# PRD — Sociétés et profil entreprise (Phase 1)

## Objectif

Fournir un périmètre société explicite pour les futurs référentiels comptables et traitements IA.

## Champs initiaux

Nom d’usage, raison sociale, matricule fiscal, activité, secteur, code pays, devise et identifiant externe Sage facultatif. Les informations peuvent être incomplètes au démarrage et enrichies avant les phases comptables.

## Comportement et accès

- Un administrateur crée une société dans son cabinet.
- Les membres ne voient que les sociétés affectées.
- Un nom de société doit être unique au sein de son cabinet ; un autre cabinet peut réutiliser le même nom.
- L’administrateur peut créer et modifier le profil d’une société ; la suppression reste désactivée pour éviter la perte de données comptables futures.
- Le connecteur Sage et l’import comptable seront ajoutés après inspection du format disponible.
