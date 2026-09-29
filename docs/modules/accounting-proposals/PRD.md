# PRD — Extraction et propositions comptables (Phase 5)

## Objectif

Structurer la transcription OCR en données facture conformes au contrat de l’application, puis produire une proposition comptable contextualisée à la société. L’IA n’invente ni compte ni journal et ne crée pas d’écriture définitive. Un gestionnaire autorisé peut corriger la facture et la proposition, les rejeter ou explicitement les valider.

## Parcours livré

1. OCR.space Engine 3 conserve son texte et sa réponse brute. La file `ocr` enchaîne avec le modèle d’extraction configurable, `qwen/qwen-2.5-7b-instruct:free` par défaut, via OpenRouter avec réponse JSON Schema stricte et plafond par défaut de 2 500 tokens. La consigne demande uniquement le JSON ; les explications, inférences non supportées et calculs sont interdits. Laravel normalise et valide les valeurs avant persistance. Avec `OCR_PROVIDER=mistral`, Mistral retourne déjà les champs structurés ; l’appel de structuration OpenRouter est sauté.
2. Les champs validés sont enregistrés dans les colonnes `invoices`, `invoice_lines` et `ocr_data`. Le texte/résultat OCR reste distinct de l’audit OpenRouter : `ocr_text`, `ocr_response`, `extraction_response`, `extraction_model` et `extraction_usage`.
3. Les données obligatoires sont contrôlées côté serveur : fournisseur, numéro, date, devise, total et description ou ligne décrite. Si elles sont manquantes, l’état `invoice_incomplete` bloque l’analyse comptable. Un gestionnaire peut corriger le formulaire ; une sauvegarde complète démarre automatiquement l’analyse.
4. L’analyse comptable est un second appel OpenRouter `openrouter/free` à réponse structurée. Son contexte est lu exclusivement dans la société de la facture : activité/secteur, plan comptable actif, journaux, tiers fournisseurs, axes analytiques et jusqu’à 20 écritures récentes de cette société. Les codes dans le schéma JSON sont des énumérations dynamiques construites depuis ces référentiels.
5. La proposition est stockée dans `accounting_proposals` et `accounting_proposal_lines`, avec le modèle réellement retourné, l’usage et la réponse brute. Les vérifications déterministes signalent l’équilibre, l’écart au total attendu, la confiance faible et l’absence de tiers fournisseur associé.
6. Le gestionnaire peut corriger journal, comptes, tiers, analytique, libellés et montants. Les références sont revérifiées côté serveur et doivent rester actives et appartenir à la société. Les avertissements bloquants empêchent la validation. Une nouvelle analyse conserve les propositions précédentes (réponse brute comprise) et crée une nouvelle version au lieu d’écraser l’audit.
7. Le gestionnaire peut rejeter la proposition sans créer d’écriture. Seule l’action explicite « Valider et créer l’écriture » crée une `journal_entry` équilibrée, liée à la facture, puis marque la facture `accounting_validated`. Il n’y a ni comptabilisation automatique, ni synchronisation Sage.

## États visibles

`data_extraction` → `invoice_incomplete` ou `accounting_analysis` → `proposal_ready` → `accounting_validated` / `proposal_rejected`.
Les erreurs sont propres à l’étape (`data_extraction_failed`, `accounting_analysis_failed`, `ocr_failed`) et peuvent être relancées sans refaire les étapes amont inutilement. Les états et erreurs sont exposés dans la liste Factures et une actualisation suit les jobs en cours.

## Sécurité et audit

- Toutes les requêtes, données comptables, options de formulaire et IDs sélectionnés sont limités à la société autorisée.
- Les réponses OCR et OpenRouter sont conservées séparément. Les requêtes, clés API et contenus sensibles ne sont pas journalisés dans les logs.
- Les comptes/journaux/tiers/axes sont contrôlés côté serveur, même si le schéma LLM les limite déjà par énumération.
- L’approbation enregistre l’utilisateur, l’horodatage, la proposition et le lien vers l’écriture. L’unicité `journal_entries.invoice_id` empêche une double validation de la même facture.
- Le modèle n’exécute jamais de SQL et n’écrit jamais directement dans le journal définitif.

## Limites et décisions

- `openrouter/free` sélectionne dynamiquement un modèle gratuit compatible avec la réponse structurée ; l’identifiant réellement renvoyé est conservé pour audit. La disponibilité et le modèle gratuit peuvent varier.
- L’analyse produit des propositions d’achats à partir des référentiels existants. Les règles complexes de retenue, devises, multi-exercices, avoirs et transfert Sage restent à compléter/valider avec l’expert-comptable.
- Les corrections ne nourrissent pas encore une mémoire apprenante. L’export réel vers Sage et l’import réel d’un `.mae` restent différés.

## Critères d’acceptation

1. OCR.space brut, OCR structuré, réponse OpenRouter et usage sont enregistrés sans écrasement mutuel.
2. Les données obligatoires manquantes empêchent l’appel d’analyse comptable jusqu’à correction autorisée.
3. L’analyse ne reçoit que le contexte comptable de la société liée à la facture et ne peut sélectionner que ses références actives.
4. Une proposition est visible et corrigeable ; l’équilibre, les références et le total sont revérifiés avant création d’écriture.
5. Le rejet ne crée rien ; seule une confirmation humaine explicite crée une écriture une seule fois.
6. Les tests HTTP OpenRouter sont simulés et couvrent les états, erreurs, audit, isolation et validation.
