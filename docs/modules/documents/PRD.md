# PRD — Intake et extraction des factures (Phases 3–4)

## Objectif

Permettre aux membres autorisés d’importer des factures dans une société, de conserver l’original en privé, puis de lancer automatiquement une extraction OCR indépendante par document. Les champs, lignes et contrôles de cohérence doivent être traçables ; aucune proposition ni écriture comptable n’est créée à cette étape.

## Phase 3 — Intake livré

- **Schéma :** `invoices` et `invoice_lines`, reliés aux sociétés, tiers éventuels, utilisateurs importateurs et facture parente. `third_party_id` reste nullable avant tout rapprochement.
- **Espaces :** le lien fixe « Factures » ouvre `/invoices`, un workspace avec sélecteur de société limité aux accès de l’utilisateur. `/companies/{company}/invoices` reste disponible comme historique complet société.
- **Formats :** PDF, JPG, JPEG et PNG contrôlés côté serveur ; maximum 20 Mo par fichier et 300 fichiers par sélection UI.
- **Traitement indépendant :** un fichier par requête et opération de stockage ; une erreur n’annule pas les fichiers acceptés et ne bloque pas les suivants.
- **Stockage privé :** disque Laravel `local`, chemin propre à la société, pas de chemin privé dans les props Inertia. Le téléchargement est authentifié et limité à la société concernée.
- **Autorisations :** administrateurs de cabinet et gestionnaires affectés peuvent importer ; les autres membres autorisés peuvent consulter et télécharger.
- **Doublons :** comparaison SHA-256 par société, sous verrou pour les requêtes concurrentes. Un doublon retourne `409` et ne persiste qu’après confirmation (`confirm_duplicate`). Aucun conflit inter-sociétés.

## Phase 4 — OCR livré

- Un upload enregistré passe à `ocr_queued` puis planifie un job indépendant sur la file `ocr`. `ProcessInvoiceOcr` est idempotent par société/facture.
- Le contrat `OcrProvider` isole `MistralOcrProvider`, qui utilise `/v1/ocr` avec un schéma strict d’annotation JSON. Les réponses sont validées avant écriture ; les données incomplètes restent nulles et les réponses invalides ne créent pas d’extraction partielle.
- Les champs d’en-tête et `invoice_lines` sont persistés dans une transaction. Le JSON brut, le JSON validé, le modèle, l’usage, les tentatives et horodatages sont conservés.
- Les états visibles sont `ocr_queued`, `ocr_processing`, `ocr_completed` et `ocr_failed`. Les erreurs temporaires sont réessayées ; les échecs exposent un message sûr et les gestionnaires peuvent relancer. Les factures `uploaded` du prototype Phase 3 peuvent être mises en file manuellement.
- Les totaux ne sont pas corrigés : la formule HT + TVA + FODEC + autres taxes + timbre − retenue est contrôlée seulement si ses sept montants sont extraits. L’interface signale un désaccord ou l’impossibilité de conclure si une composante manque.
- L’interface actualise les factures en cours et affiche état, erreurs et avertissements. Toute extraction reste à vérifier par un humain.

## Workspace et revue de l’extraction

- L’espace global propose un import et une liste récente après choix d’une société ; les rôles sont revérifiés côté serveur.
- Les factures sélectionnées sur la page courante peuvent être exportées en CSV.
- Un gestionnaire peut marquer comme vérifiées uniquement des extractions OCR terminées. Le statut/horodatage et l’utilisateur vérificateur sont conservés pour la traçabilité ; cette action ne valide pas une écriture.

## Hors périmètre

- Aucun appel Mistral Small, analyse du contexte comptable, rapprochement fournisseur, compte/journal proposé, écriture, export Sage ou validation comptable (Phase 5 / intégration future).
- Aucun déplacement ni suppression des anciennes tables `documents` et `accounting_entries` ; aucune donnée historique n’est migrée implicitement.

## Critères d’acceptation

1. Un gestionnaire autorisé peut importer et retrouver ses factures ; les fichiers restent privés et les downloads respectent le périmètre société.
2. Chaque import accepté est mis en file automatiquement et indépendamment ; un doublon nécessite une confirmation explicite.
3. Les réponses OCR valides sont persistées avec leurs lignes et éléments d’audit ; une réponse invalide devient une erreur visible, pas une extraction partielle.
4. L’état, les tentatives, erreurs et avertissements de totaux sont visibles et récupérables.
5. Les montants extraits ne sont jamais silencieusement recalculés/corrigés et aucune écriture comptable n’est créée.
6. Les tests utilisent des réponses HTTP simulées et ne nécessitent pas de clé fournisseur réelle.
