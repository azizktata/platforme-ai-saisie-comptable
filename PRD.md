# Product Requirements — Plateforme AI de saisie comptable automatisée

## Source de vérité et état

Le cahier des charges et le prompt d’implémentation fournis par le client sont désormais consignés dans [`docs/requirements.md`](docs/requirements.md) et pilotent les décisions du produit. Ils remplacent le périmètre provisoire de saisie manuelle livré dans le premier commit.

**État actuel : Phases 1 et 2 implémentées ; l’intake de la Phase 3 est en place.** La fondation multi-cabinet, les rôles/affectations, les retours toast, les référentiels comptables par société et le socle des factures sont présents. La Phase 3 fournit les tables `invoices`/`invoice_lines`, un import PDF/JPG/JPEG/PNG indépendant par fichier, un stockage privé, une détection de doublons SHA-256 avec confirmation et une liste sécurisée par société. Le traitement OCR, l’analyse comptable et la validation humaine restent différés. Aucune base Sage ni fichier `.mae` réel n’a été fourni. Les migrations et tests Laravel restent à exécuter dans un environnement avec PHP 8.3 et Composer.

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
4. Mistral OCR retourne le JSON attendu ; les données valides sont enregistrées avec leur statut et leur provenance.
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
- Les interactions IA ne conservent que les éléments nécessaires à la traçabilité ; pas de prompts ou données sensibles complets sans nécessité.
- L’intégration Sage réelle et le traitement `.mae` restent à préciser après inspection du fichier et de la version de Sage.

## Statut des fonctionnalités

| Phase | Périmètre | État |
|---|---|---|
| 1 | Auth, cabinets, sociétés, utilisateurs, rôles, contrôle d’accès et retours UI | Implémentée ; runtime/tests à valider |
| 2 | Référentiels comptables et données Sage mockées par société | Implémentée avec données fictives ; intégration Sage réelle différée |
| 3 | Schéma facture/lignes, intake multi-fichier, stockage privé, doublons et visibilité d’état | Intake implémenté ; tests Laravel à exécuter |
| 4 | Mistral OCR, jobs et états de traitement | À faire — aucun OCR déclenché |
| 5 | Proposition comptable, contrôles, validation humaine | À faire |

Les critères détaillés, champs OCR, diagramme ERD, contrôles et stack sont dans [`docs/requirements.md`](docs/requirements.md). Chaque module conserve son PRD et son README technique sous `docs/modules/`.
