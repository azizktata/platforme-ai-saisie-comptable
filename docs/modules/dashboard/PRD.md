# PRD — Vue d’ensemble

## Objectif
Donner à l’utilisateur connecté une lecture immédiate du travail comptable et des factures qui demandent une vérification.

## Contenu et comportement

- KPI « À vérifier » : tous les documents `needs_review` appartenant à l’utilisateur.
- KPI « Écritures comptabilisées » et « Dépenses TTC » : écritures créées (comptabilisées) pendant le mois courant.
- KPI « Documents reçus » : documents créés pendant le mois courant.
- Histogramme des dépenses TTC comptabilisées sur les six derniers mois.
- Jusqu’à quatre factures récentes en attente de revue, avec fournisseur, référence, montant et statut.
- Tableau des six documents les plus récents ; une ligne ouvre le formulaire de vérification.
- Action « Importer un document » disponible sans quitter la vue.
- Les listes vides affichent un état informatif ; aucune donnée de démonstration n’est injectée dans les requêtes backend.

## Règles métier

- Toutes les mesures sont calculées pour l’utilisateur authentifié.
- Une dépense n’est incluse dans les montants comptabilisés que si une écriture associée existe.
- Les mois sont calculés selon le fuseau de l’application (`Europe/Paris` par défaut).

## Critères d’acceptation

- Le tableau de bord est inaccessible aux invités.
- Les montants évoluent après import et comptabilisation.
- Les liens de documents pointent vers les factures du compte courant uniquement.
