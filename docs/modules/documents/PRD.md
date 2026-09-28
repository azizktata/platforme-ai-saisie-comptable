# PRD — Factures et intake des documents (Phase 3)

## Objectif

Permettre aux membres autorisés d’importer des factures dans une société, de conserver chaque document source dans un stockage privé et de suivre son état initial. Les données OCR, les lignes de facture exploitables et l’analyse comptable sont produites dans les phases ultérieures, pas au moment de l’upload.

## Exigences et état

- **Schéma en place :** `invoices` et `invoice_lines`, reliés aux sociétés, tiers éventuels, utilisateurs ayant importé le document et factures parentes. `third_party_id` reste nullable tant que le fournisseur n’est pas rapproché.
- **Formats :** PDF, JPG, JPEG et PNG, contrôlés côté serveur ; limite de 20 Mo par fichier et lot UI de 300 fichiers maximum.
- **Traitement indépendant :** la sélection multiple est découpée en une requête et une opération de stockage par fichier. Une erreur de validation, réseau ou stockage ne supprime pas les fichiers déjà acceptés et ne bloque pas les suivants.
- **Stockage privé :** les sources sont conservées sur le disque Laravel `local`, sous un chemin propre à la société. Aucun chemin de stockage n’est exposé dans les props Inertia. Le téléchargement passe par une route authentifiée, vérifie l’accès à la société et refuse un identifiant de facture appartenant à une autre société.
- **Autorisations :** seuls les administrateurs du cabinet et les gestionnaires de factures ayant accès à la société peuvent importer. Tout utilisateur ayant accès à la société peut consulter la liste et télécharger ses documents.
- **Doublons :** une empreinte SHA-256 est calculée sur le contenu et comparée aux factures de la même société. Une requête concurrente est sérialisée au niveau de la société. Un doublon retourne `409` et n’est enregistré qu’après confirmation explicite (`confirm_duplicate`). Les doublons inter-sociétés ne se déclenchent pas.
- **État visible :** un document importé porte l’état `uploaded`, affiché « Reçue · OCR à venir ». Les champs fournisseur, référence, dates et montants restent vides tant qu’aucune extraction n’a eu lieu.
- **Portée différée :** aucun job OCR, appel Mistral, JSON extrait, rapprochement fournisseur, calcul fiscal, ligne OCR ou proposition comptable n’est créé dans cette phase. L’interface explique cette limite.
- **Compatibilité prototype :** les migrations historiques `documents` et `accounting_entries` restent intactes. Aucune donnée de ces tables n’est déplacée ni supprimée implicitement.

## Critères d’acceptation

1. Un gestionnaire autorisé peut importer des PDF/images et les retrouver dans la liste de sa société.
2. Une facture importée est stockée sur le disque privé ; elle n’est téléchargeable qu’après autorisation pour cette société.
3. Une société ne peut lire, télécharger ou détecter les doublons d’une autre société.
4. Les formats non autorisés, les fichiers dépassant la limite ou les uploads non autorisés sont rejetés sans persistance.
5. Un doublon dans la même société exige une confirmation explicite avant la création d’un second document.
6. Le succès et l’échec de chaque fichier sont visibles ; un fichier en échec ne bloque pas les suivants.
7. Aucun écran ou message ne prétend qu’un OCR ou une proposition comptable a été exécuté.

## Hors périmètre — phases suivantes

- Phase 4 : contrat OCR Mistral, jobs/queues, reprise sur erreur, données extraites, persistance structurée et états OCR.
- Phase 5 : contexte comptable, proposition Mistral Small, contrôles déterministes, revue et validation humaine.
- Migration ou suppression des tables historiques, après analyse et sauvegarde explicites.
