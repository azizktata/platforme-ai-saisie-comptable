# PRD — Référentiels comptables (Phase 2)

## Objectif

Fournir, pour chaque société, les référentiels et un historique d’écritures qui pourront contextualiser les phases de traitement comptable ultérieures. Les données résident dans la base de l’application ; un connecteur Sage réel n’est pas prétendu disponible.

## Données couvertes

- Plan de comptes (`ChartAccount`) avec code texte, intitulé, nature, identifiant Sage optionnel et statut actif.
- Comptes analytiques (`AnalyticalAccount`) avec code texte, intitulé et statut.
- Tiers (`ThirdParty`) client/fournisseur, matricule fiscal et comptes auxiliaires associés lorsque connus.
- Journaux (`Journal`) identifiés par un code texte et une nature.
- Historique (`JournalEntry` et `JournalEntryLine`) avec journal, référence, date, source, compte général, tiers/analytique facultatifs, débit et crédit.

## Règles fonctionnelles et d’accès

- Tous les référentiels et historiques sont rattachés à une société ; les codes sont des chaînes afin de préserver zéros initiaux et valeurs alphanumériques.
- Les montants sont enregistrés à trois décimales afin de conserver les millimes tunisiens ; la devise d’affichage provient du profil de la société.
- Un utilisateur ne voit les données que d’une société à laquelle il a accès dans son cabinet. `company_user` consulte ; `invoice_manager` et `cabinet_admin` peuvent charger le jeu d’exemple en environnement local/test.
- Chaque code de compte, analytique, tiers et journal est unique dans sa société ; une même valeur peut exister dans une autre société.
- Le jeu de démonstration est petit, déterministe et relançable sans doublons. Il contient des écritures équilibrées pour tester l’affichage des débits/crédits.
- Les écritures présentées sont en lecture seule à cette phase. Aucun import `.mae`, connexion ou export vers Sage réel n’est disponible.

## Parcours actuel

1. L’utilisateur ouvre « Données comptables » depuis le tableau de bord ou la liste des sociétés.
2. La page présente les comptes généraux/analytique, tiers, journaux et extrait historique autorisés pour cette société.
3. Si aucun référentiel n’existe, un administrateur ou gestionnaire peut charger l’exemple en environnement `local`/`testing`. Le service serveur est idempotent ; la route est refusée ailleurs.

## Hors périmètre

Import réel de base Sage ou de `.mae`, édition manuelle des référentiels/écritures, synchronisation/export, factures, OCR et propositions IA. L’intégration Sage reste conditionnée à l’examen du fichier et à l’identification de la version Sage.
