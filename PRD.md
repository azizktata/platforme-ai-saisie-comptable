# Product Requirements — Plateforme AI de saisie comptable automatisée

## Source de vérité et état

Le cahier des charges fourni par le client est consigné dans [`docs/requirements.md`](docs/requirements.md). La dernière demande remplace la proposition initiale Mistral Small : OCR.space Free Engine 3 reste l’OCR par défaut, Qwen3.8 27B gratuit via OpenRouter structure le texte avec le raisonnement désactivé, et OpenRouter `openrouter/free` produit l’analyse comptable ; Mistral OCR reste sélectionnable pour fournir directement les données structurées.

**État actuel : Phases 1 à 5 implémentées au niveau applicatif ; les migrations/tests Laravel demandent une validation dans un environnement PHP/Composer.** La Phase 5 ajoute extraction structurée, complétude et correction humaine, propositions tenant-scoped, avertissements et validation/rejet humains. Le build TypeScript passe ; PHP/Composer ne sont pas installés dans l’environnement de travail courant.

## Vision

Transformer une facture fournisseur en proposition comptable vérifiable, contextualisée aux données comptables de la société. Une personne reste responsable de la revue. L’IA ne modifie jamais directement une écriture définitive ; une écriture n’est créée qu’après confirmation humaine explicite.

## Personnes et accès

- **Administrateur de cabinet (`cabinet_admin`)** : gère le cabinet, ses sociétés et ses utilisateurs ; accès administratif à toutes les sociétés de son cabinet.
- **Gestionnaire de factures (`invoice_manager`)** : traite les factures des sociétés qui lui sont affectées.
- **Utilisateur société (`company_user`)** : consulte les sociétés affectées sans pouvoir gérer le cabinet.
- `users.cabinet_id` rattache un utilisateur à son cabinet ; les accès société résident dans `company_user_access`.
- Toute donnée facture, comptable, option de formulaire ou mutation est limitée à la société autorisée.

## Parcours fonctionnel livré

1. L’utilisateur autorisé importe un document dans le stockage privé ; chaque facture possède son état et son job.
2. OCR.space Engine 3 Free transcrit la facture par défaut ; Mistral demeure configurable. Le texte OCR et la réponse brute sont consultables/audités.
3. Qwen3.8 27B gratuit via OpenRouter structure la transcription OCR.space au moyen du JSON Schema strict ; le raisonnement est désactivé, la sortie plafonnée à 2 500 tokens et la normalisation/validation effectuée dans Laravel. Les champs incomplets sont visibles et corrigibles ; ils bloquent l’analyse comptable. Le chemin Mistral conserve sa structure directe.
4. Une fois complète, une seconde requête OpenRouter analyse la facture en utilisant uniquement les référentiels actifs, le profil et l’historique récent de la société.
5. Les données et la proposition sont affichées avec les avertissements, les codes autorisés et les contrôles d’équilibre/total. Un gestionnaire peut corriger ou rejeter. Les corrections sont auditées et chaque nouvelle analyse conserve la version précédente.
6. Seule la validation humaine explicite crée une écriture comptable liée à la facture. L’export Sage réel est différé jusqu’à inspection du format `.mae` et de la version Sage.

## Règles produit

- Import PDF/JPG/JPEG/PNG en lot ; un fichier échoué ne bloque pas les autres.
- Un fournisseur non apparié reste possible (`invoices.third_party_id` nullable) ; aucun tiers ou compte n’est créé automatiquement.
- Les comptes/codes sont des chaînes ; l’IA ne peut sélectionner que les référentiels actifs de la société liée.
- Les montants sont stockés à trois décimales et vérifiés au millime, sans correction automatique. Les erreurs/incohérences restent visibles.
- OCR brut, transcription, réponses de structuration et de proposition sont conservés séparément ; les secrets et prompts ne sont pas écrits dans les logs.
- Une validation de proposition revérifie l’équilibre, le total, les champs requis, l’état des références et le tenant, et ne peut créer qu’une écriture liée par facture.
- La mémoire des corrections, l’import/export Sage réel et les règles exhaustives de fiscalité/avoirs restent différés.

## Statut des fonctionnalités

| Phase | Périmètre | État |
|---|---|---|
| 1 | Auth, inscription, cabinets, sociétés, utilisateurs, rôles, catalogue d’activités et contrôle d’accès | Implémentée ; tests Laravel à exécuter |
| 2 | Référentiels comptables et données d’exemple par société, navigation globale | Implémentée avec données fictives ; intégration Sage réelle différée |
| 3 | Schéma facture/lignes, intake multi-fichier, stockage privé, doublons, workspace et historique | Implémentée ; tests Laravel à exécuter |
| 4 | OCR interchangeable, états, erreurs, audit OCR et contrôles de totaux | OCR.space Free Engine 3 par défaut ; Mistral reste sélectionnable ; tests Laravel à exécuter |
| 5 | OpenRouter extraction, blocage/correction des champs incomplets, analyse contextualisée, proposition, revue/rejet/validation humaine | Implémentée ; migrations et tests Laravel à exécuter avec PHP/Composer |

Les PRD/README module se trouvent sous `docs/modules/`. La Phase 2 utilise un jeu fictif de données type Sage ; aucune base réelle ni fichier `.mae` n’a été fourni.
