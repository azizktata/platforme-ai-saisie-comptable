# PRD — Traitement des factures (Phases 3–5)

## Objectif

Importer chaque facture en stockage privé, conserver ses sources d’audit et dérouler OCR → structuration/validation → analyse comptable. Le traitement est indépendant par document et société. Aucune écriture définitive n’est créée sans revue et validation humaine.

## Parcours livré

1. PDF/JPG/JPEG/PNG est validé, hashé et enregistré en privé dans `companies/{company}/invoices`. L’upload crée `ocr_queued` et planifie `ProcessInvoiceOcr` sur la file `ocr`.
2. OCR.space Free Engine 3 est le fournisseur actif par défaut. Il renvoie la transcription, conservée séparément dans `ocr_text`, et sa réponse brute dans `ocr_response`. Mistral reste sélectionnable (`OCR_PROVIDER=mistral`) et retourne déjà une annotation facture structurée validée.
3. Pour OCR.space, `ExtractInvoiceData` appelle par défaut `qwen/qwen3.8-27b:free` via OpenRouter avec `reasoning.effort=none`, le JSON Schema strict de `InvoiceOcrSchema` et une limite de 2 500 tokens de sortie. La consigne demande uniquement le JSON, sans explication ni calcul. Laravel normalise et valide les dates, devises et montants avant persistance. La réponse brute, le modèle réellement retourné et l’usage sont sauvegardés dans les champs `extraction_*`, sans écraser la réponse OCR. Pour Mistral, les champs structurés passent par la même normalisation/validation Laravel, sans second appel de structuration OpenRouter.
4. Les colonnes facture, `ocr_data` et `invoice_lines` sont atomiquement synchronisées. Les totaux manquants/incohérents sont signalés sans correction automatique.
5. L’analyse comptable ne démarre que si les champs obligatoires (fournisseur, numéro, date, devise, total et description ou ligne décrite) sont complets. Sinon, `invoice_incomplete` affiche les corrections nécessaires et bloque l’analyse.
6. Une extraction complète déclenche automatiquement `AnalyzeAccountingProposal`. Le contexte OpenRouter est limité à la société liée : référentiels actifs, profil et historique comptable récent. La proposition et ses lignes sont auditées, contrôlées, modifiables et visibles.
7. Un gestionnaire peut rejeter la proposition ou la valider explicitement. Seule la validation crée une écriture liée à la facture ; aucun export Sage n’est effectué.

## États

- `uploaded`, `ocr_queued`, `ocr_processing` : réception et OCR.
- `data_extraction` : structuration OpenRouter du texte OCR.space.
- `invoice_incomplete` : JSON enregistré mais champs requis à corriger ; pas d’analyse.
- `accounting_analysis` : seconde analyse OpenRouter, limitée au cabinet/société autorisés.
- `proposal_ready` : lignes proposées, avertissements et contrôles disponibles à la revue.
- `proposal_rejected`, `accounting_validated` : décision humaine ; seule la seconde a une écriture créée.
- `ocr_failed`, `data_extraction_failed`, `accounting_analysis_failed` : erreur propre à l’étape ; relance ciblée.
- `ocr_completed` demeure un état historique de la Phase 4, sans être le résultat final du nouveau workflow.

Les totaux utilisent l’arithmétique au millime et ne sont jamais corrigés silencieusement. Une réponse invalide ne devient pas une extraction partielle exploitable ; le JSON brute IA et les avertissements restent séparés.

## Espace Factures

`/invoices` présélectionne la première société accessible et permet d’en choisir une autre autorisée. Le texte OCR s’affiche dans une boîte de dialogue, avec les données structurées, les avertissements et la proposition. Les rôles en lecture seule peuvent consulter ; seuls les administrateurs de cabinet/gestionnaires affectés peuvent corriger ou valider. Le suivi actualise chaque cinq secondes tant qu’un job est en cours. L’historique société et le téléchargement privé restent scoppés.

## Critères d’acceptation

1. Un document accepté a ses états, erreurs et audits propres et ne bloque aucun autre fichier.
2. OCR brut et réponses OpenRouter sont préservés distinctement ; les contrats de sortie sont validés côté serveur.
3. Les champs requis absents empêchent l’appel de proposition comptable jusqu’à correction complète.
4. Toute donnée/référence est scoppée à la société et revalidée côté serveur.
5. Les contrôles signalent totaux incohérents, propositions déséquilibrées et avertissements de rapprochement ; aucun montant n’est auto-corrigé.
6. Rejet humain ne crée aucune écriture ; validation humaine explicite crée au plus une écriture équilibrée liée à la facture.
7. Le texte OCR et la proposition sont consultables dans une boîte de dialogue avec messages/toasts après actions.
8. Les tests HTTP utilisent des réponses simulées et n’exigent aucune clé réelle.
