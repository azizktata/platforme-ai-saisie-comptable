# PRD — Extraction et propositions comptables (Phase 5)

## Objectif

Structurer la transcription OCR en données facture conformes au contrat de l’application, puis produire une proposition comptable contextualisée à la société. L’IA n’invente ni compte ni journal et ne crée pas d’écriture définitive. Un gestionnaire autorisé peut corriger la facture et la proposition, les rejeter, les valider ou exporter un CSV comptable interne.

## Parcours livré

1. OCR.space Engine 3 conserve son texte et sa réponse brute. La file `ocr` enchaîne automatiquement avec le modèle d’extraction configurable, `qwen/qwen-2.5-7b-instruct:free` par défaut, via OpenRouter avec réponse JSON Schema stricte et plafond par défaut de 2 500 tokens. Les explications, inférences non supportées et calculs sont interdits. Laravel normalise et valide avant persistance. Avec `OCR_PROVIDER=mistral`, Mistral retourne les champs structurés ; l’appel de structuration OpenRouter est sauté.
2. Les champs validés sont enregistrés dans `invoices`, `invoice_lines` et `ocr_data`. Le texte/résultat OCR reste distinct de l’audit OpenRouter : `ocr_text`, `ocr_response`, `extraction_response`, `extraction_model` et `extraction_usage`.
3. Les données obligatoires sont contrôlées côté serveur : fournisseur, numéro, date, devise, total et description ou ligne décrite. Si elles sont manquantes, `invoice_incomplete` bloque l’analyse comptable. Une correction complète autorisée démarre l’analyse automatiquement.
4. L’analyse comptable est un second appel OpenRouter `openrouter/free` à réponse structurée. Son contexte est lu exclusivement dans la société de la facture : activité/secteur, plan comptable actif, journaux, tiers fournisseurs, axes analytiques et jusqu’à 20 écritures récentes de cette société. Le JSON Schema limite les catégories aux valeurs persistées de `InvoiceType`.
5. La proposition est stockée dans `accounting_proposals` et `accounting_proposal_lines`, avec son type, modèle, usage et réponse brute. Les vérifications déterministes signalent l’équilibre, l’écart de débit au TTC brut, la confiance faible et l’absence de tiers fournisseur associé.
6. Le gestionnaire peut corriger le type, le journal, les comptes, tiers, analytique, libellés et montants, ajouter/supprimer des lignes et visualiser les totaux débit/crédit. Les références sont revérifiées côté serveur. La régénération est une action POST protégée, limitée aux états prêts/rejetés/échoués, et conserve l’ancienne version.
7. Le bouton de recalcul des montants est explicite et déterministe, sans LLM : il reprend le HT imprimé sans déduire une seconde fois la remise séparée, ajoute TVA + FODEC + autres taxes + timbre pour le TTC brut, puis déduit la retenue uniquement pour le net à payer. Les valeurs vides exigent confirmation avant d’être traitées comme zéro. Le rejet ne crée aucune écriture. Seule l’approbation humaine, après sélection d’un type, crée une `journal_entry` équilibrée et liée à la facture.
8. La validation bloque les incohérences de TTC, TVA ou net à payer, le déséquilibre, les références inactives et les devises différentes lorsqu’aucun taux de conversion n’est configuré.
9. `Export CSV` peut télécharger le brouillon prêt sans le valider. Après validation, l’export du journal final enregistre `accounting_exported_at`/`accounting_exported_by` et passe la facture à `accounting_exported`. Réexporter met à jour ces informations. Le CSV est interne : aucune synchronisation Sage n’a lieu.

## États visibles

`data_extraction` → `invoice_incomplete` ou `accounting_analysis` → `proposal_ready` → `accounting_validated` / `proposal_rejected` → éventuellement `accounting_exported`.
Les erreurs sont propres à l’étape (`data_extraction_failed`, `accounting_analysis_failed`, `ocr_failed`) et peuvent être relancées sans répéter les étapes amont inutilement. L’écran détail affiche le progrès automatique.

## Espace de revue

La confiance globale et les barres par dimension ne montrent que les scores effectivement fournis ; fournisseur, montants, TVA et catégorie restent `N/D` tant qu’aucun score calibré n’existe. La confiance comptable est la moyenne des confiances réelles des lignes.

Le panneau est organisé en cinq cartes numérotées : données extraites modifiables avec recalcul local explicite ; type de facture sélectionnable avec activité/secteur réels de l’entreprise ; écriture proposée marquée brouillon, avec journal, libellé, lignes, comptes, mémos, montants, totaux et régénération ; contrôles serveur verticaux ; puis décision humaine. Les actions de validation/export/rejet restent soumises aux autorisations et gardes serveur.

## Contrôles affichés

- Équilibre débit/crédit et rapprochement du total des débits au TTC brut : calculs côté Laravel, sans ajouter la retenue au TTC.
- TVA, totaux et net : TVA globale comparée au taux/base lorsqu’ils sont disponibles ; TTC vérifié avec les composants ; net comparé au TTC diminué de la retenue. Une incohérence connue est bloquante ; des données manquantes restent en attente.
- Doublon : empreinte SHA-256 identique ou même numéro pour le même fournisseur dans la société, signalé comme avertissement. Le contrôle par numéro s’appuie sur les factures persistées.
- Fournisseur/tiers et comptes, journaux, tiers et axes actifs : vérifications de société et statut côté serveur ; absence de tiers signalée.
- Devise : égalité société/facture vérifiée ; si différente et sans taux de conversion, approbation bloquée.
- Exercice fiscal : si les dates de début et de fin sont configurées sur la société, la date de facture est contrôlée et l’approbation est bloquée si elle est absente ou hors période ; sans configuration, le contrôle reste indisponible.

## Sécurité et audit

- Toutes les requêtes, données comptables, options de formulaire et IDs sélectionnés sont limités à la société autorisée.
- Les réponses OCR et OpenRouter sont conservées séparément. Les requêtes, clés API et contenus sensibles ne sont pas journalisés dans les logs.
- Les comptes/journaux/tiers/axes sont contrôlés côté serveur, même si le schéma LLM les limite déjà par énumération.
- L’approbation enregistre l’utilisateur, l’horodatage, la proposition et le lien vers l’écriture. L’unicité `journal_entries.invoice_id` empêche une double validation.
- L’export final garde l’utilisateur et la date de la dernière génération CSV côté serveur ; l’export d’un brouillon ne change pas le statut métier.
- Le modèle n’exécute jamais de SQL et n’écrit jamais directement dans le journal définitif.

## Limites et décisions

- `openrouter/free` sélectionne dynamiquement un modèle gratuit compatible avec la réponse structurée ; l’identifiant renvoyé est conservé pour audit.
- La confiance affichée est la moyenne des confiances de proposition par ligne ; elle n’est pas un score OCR.
- Le workspace actuel traite les factures fournisseurs et n’implémente pas encore les règles complètes d’avoirs/pro forma ou la conversion multidevise.
- Les corrections ne nourrissent pas encore une mémoire apprenante. L’export Sage réel et l’import réel d’un `.mae` restent différés.

## Critères d’acceptation

1. OCR brut, extraction structurée, réponse OpenRouter et usage sont enregistrés sans écrasement mutuel.
2. Les données obligatoires manquantes empêchent l’appel d’analyse comptable jusqu’à correction autorisée.
3. L’analyse ne reçoit que le contexte de la société liée à la facture et ne peut sélectionner que ses références actives.
4. Le type de facture est choisi parmi une enum persistée, contextualisée par le profil d’activité, et requis pour valider.
5. Les propositions brouillon sont modifiables, affichent les totaux débit/crédit et peuvent être régénérées uniquement aux états permis, sans perte d’audit.
6. Le recalcul est explicite, local, déterministe, sans LLM, jamais automatique ; TTC reste brut hors retenue et le net est contrôlé séparément.
7. Les contrôles indisponibles/inconclusifs ne sont pas présentés comme réussis ; une devise sans conversion bloque l’approbation.
8. Le rejet ne crée rien ; seule une confirmation humaine explicite crée une écriture une seule fois.
9. L’export brouillon ne valide pas la proposition ; l’export CSV d’une écriture finalisée est audité et ne prétend pas intégrer Sage.
10. Les tests HTTP OpenRouter sont simulés et couvrent les états, erreurs, audit, isolation, régénération, validation TTC/withholding et exports.
