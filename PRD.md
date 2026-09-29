# Product Requirements — Plateforme AI de saisie comptable automatisée

## Source de vérité et état

Le cahier des charges et le prompt d’implémentation fournis par le client sont désormais consignés dans [`docs/requirements.md`](docs/requirements.md) et pilotent les décisions du produit. Ils remplacent le périmètre provisoire de saisie manuelle livré dans le premier commit.

**État actuel : les Phases 1 à 4 sont implémentées ; leur exécution Laravel reste à vérifier dans un environnement PHP/Composer.** La fondation multi-cabinet inclut l’inscription d’un cabinet et de son premier administrateur, les paramètres de cabinet, les rôles/affectations, un catalogue d’activités prédéfinies et personnalisées par cabinet, ainsi que des retours toast. La Phase 2 fournit les référentiels comptables par société et des données d’exemple. Les Phases 3–4 fournissent l’intake privé des factures, l’OCR asynchrone configurable (OCR.space Engine 3 par défaut, Mistral conservé pour l’offre Pro), les états/erreurs, les avertissements de totaux, les espaces globaux « Factures » et « Données comptables », l’historique facture par société, l’export CSV de la sélection et le suivi de vérification de l’extraction OCR. OCR.space Free fournit actuellement une transcription textuelle sans structuration automatique des champs facture. Aucune base Sage ni fichier `.mae` réel n’a été fourni. PHP/Composer ne sont pas disponibles dans l’environnement de travail actuel : les migrations et tests Laravel n’y ont pas été exécutés.

## Vision

Transformer une facture fournisseur en proposition comptable vérifiable et contextualisée, en tenant compte du profil de l’entreprise et des comptes réellement présents dans son dossier Sage. Une personne reste responsable de la revue et de la validation. L’IA ne modifie jamais directement les écritures finales.

## Personnes et accès

- **Administrateur de cabinet (`cabinet_admin`)** : gère le cabinet, ses sociétés et ses utilisateurs ; accès administratif à toutes les sociétés de son cabinet.
- **Gestionnaire de factures (`invoice_manager`)** : traite les factures des sociétés qui lui sont affectées.
- **Utilisateur société (`company_user`)** : consulte/revoit les sociétés affectées sans pouvoir gérer le cabinet.
- `users.cabinet_id` rattache un utilisateur à son cabinet ; `users` ne porte pas un `company_id` unique.
- Les droits au niveau société sont stockés dans `company_user_access`; toute requête métier doit vérifier cabinet **et** accès société.

## Parcours fonctionnel cible

1. L’administrateur configure les sociétés, les membres et les accès.
2. Les données de référence comptables de chaque société sont importées ou mockées à partir de Sage.
3. Un membre téléverse un ou plusieurs fichiers ; chaque fichier est persisté en privé et traité dans son propre job.
4. Le provider OCR configurable transcrit le document ; OCR.space Free Engine 3 est actif par défaut et conserve le texte brut, tandis que Mistral est gardé pour l’extraction structurée de l’offre Pro.
5. L’analyse Mistral Small utilise seulement le contexte comptable de la société concernée.
6. La proposition est validée par schéma et contrôles déterministes, puis affichée avec le document source, les scores/confiance et les alertes.
7. Le comptable modifie/accepte/rejette la proposition. Seule une validation humaine crée/finalise l’écriture.
8. Les corrections confirmées peuvent enrichir la mémoire configurable de cette société.

## Règles produit

- Import simultané PDF, JPG/JPEG et PNG ; un échec de fichier ne bloque pas les autres.
- Un fournisseur non apparié reste possible (`invoices.third_party_id` nullable).
- Les comptes et codes sont des chaînes, jamais convertis en entiers ; l’IA ne propose que des comptes existants de la société.
- L’écriture comptable est à lignes débit/crédit ; l’équilibre, les références, la TVA, la devise et les doublons sont vérifiés côté serveur.
- Les appels OCR/analyse sont asynchrones et isolés derrière des services fournisseurs remplaçables.
- L’extraction OCR peut être marquée « vérifiée » pour la traçabilité ; cette action n’est pas une validation comptable et ne crée pas d’écriture.
- Les interactions IA ne conservent que les éléments nécessaires à la traçabilité ; pas de prompts ou données sensibles complets sans nécessité.
- L’intégration Sage réelle et le traitement `.mae` restent à préciser après inspection du fichier et de la version de Sage.

## Statut des fonctionnalités

| Phase | Périmètre | État |
|---|---|---|
| 1 | Auth, inscription, cabinets, sociétés, utilisateurs, rôles, catalogue d’activités et contrôle d’accès | Implémentée ; tests Laravel à exécuter |
| 2 | Référentiels comptables et données d’exemple par société, navigation globale | Implémentée avec données fictives ; intégration Sage réelle différée |
| 3 | Schéma facture/lignes, intake multi-fichier, stockage privé, doublons, workspace et historique | Implémentée ; tests Laravel à exécuter |
| 4 | OCR provider interchangeable, jobs, états, revue de l’extraction et cohérence des totaux | OCR.space Free Engine 3 est actif par défaut (texte brut) ; Mistral structuré reste sélectionnable pour l’offre Pro ; tests Laravel à exécuter |
| 5 | Proposition comptable, contrôles et validation humaine d’écriture | À faire |

Les critères détaillés, champs OCR, diagramme ERD, contrôles et stack sont dans [`docs/requirements.md`](docs/requirements.md). Chaque module conserve son PRD et son README technique sous `docs/modules/`.
