# Product Requirements — Plateforme AI de saisie comptable automatisée

## Source de vérité et état

Le cahier des charges fourni par le client est consigné dans [`docs/requirements.md`](docs/requirements.md). OCR.space Free Engine 3 reste l’OCR par défaut ; le modèle d’extraction configurable par défaut est `qwen/qwen-2.5-7b-instruct:free`, distinct du fournisseur OCR et du modèle comptable `openrouter/free`. Mistral OCR reste sélectionnable pour fournir directement les données structurées.

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
3. `qwen/qwen-2.5-7b-instruct:free` via OpenRouter structure la transcription OCR.space avec le JSON Schema strict, JSON uniquement et 2 500 tokens par défaut. Laravel valide/normalise ; les réponses mal formées ou safety-classifier sont refusées. L’extraction est configurable séparément et n’effectue aucun calcul. Les champs incomplets sont visibles et corrigibles ; ils bloquent l’analyse comptable. Le chemin Mistral conserve sa structure directe.
4. Une fois complète, une seconde requête OpenRouter analyse la facture en utilisant uniquement les référentiels actifs, le profil et l’historique récent de la société.
5. Les données et la proposition sont affichées sur une page de revue dédiée, avec le document PDF/image dans un visualiseur indépendant, les avertissements et contrôles. L’espace de revue à droite présente la confiance réellement fournie (les sous-scores absents sont N/D) et cinq cartes : données extraites modifiables, type de facture contextualisé par l’activité, proposition brouillon éditable, contrôles verticaux et décision humaine.
6. Le gestionnaire peut corriger les montants, choisir le type, modifier la proposition, régénérer un brouillon éligible, rejeter ou valider. Les corrections et versions sont auditées. Un bouton distinct recalcule localement les totaux de manière déterministe (HT + taxes + timbre = TTC brut ; retenue déduite seulement du net), sans appel LLM et sans application automatique. Les champs vides exigent confirmation avant d’être assimilés à zéro.
7. La relance d’extraction réutilise le texte OCR stocké et ne relance pas OCR. Seule la validation humaine explicite crée une écriture comptable liée à la facture. L’export Sage réel est différé jusqu’à inspection du format `.mae` et de la version Sage.

## Règles produit

- Import PDF/JPG/JPEG/PNG en lot ; un fichier échoué ne bloque pas les autres.
- Un fournisseur non apparié reste possible (`invoices.third_party_id` nullable) ; aucun tiers ou compte n’est créé automatiquement.
- Les comptes/codes sont des chaînes ; l’IA ne peut sélectionner que les référentiels actifs de la société liée.
- Les montants sont stockés à trois décimales et vérifiés au millime, sans correction automatique. Seul un bouton explicite effectue un calcul local déterministe à partir des champs saisis ; les erreurs/incohérences restent visibles et aucune somme n’est déléguée au LLM.
- OCR brut, transcription, réponses de structuration et de proposition sont conservés séparément ; les secrets et prompts ne sont pas écrits dans les logs.
- Une validation de proposition revérifie la catégorie, les totaux HT/TVA/TTC brut/net, l’équilibre, les champs requis, l’état des références, la devise et le tenant ; la retenue ne majore pas le TTC. Une seule écriture liée peut être créée par facture.
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
