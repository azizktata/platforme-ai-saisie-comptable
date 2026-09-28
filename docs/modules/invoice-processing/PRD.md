# PRD — Traitement OCR des factures (Phase 4)

Après la Phase 3, chaque facture persistée déclenche automatiquement un job Laravel OCR utilisant Mistral OCR. Le prompt demande la structure JSON canonique ; si la réponse respecte le contrat, elle est validée et enregistrée directement, sans normaliseur ni second appel LLM. Les erreurs, états, durée, modèle et tokens disponibles sont traçables sans journaliser de données sensibles inutiles.

Les jobs sont idempotents, indépendants par facture et gèrent délai dépassé, réponse invalide et indisponibilité du fournisseur. La clé API reste hors de Git. La page liste expose progression et erreurs récupérables.
