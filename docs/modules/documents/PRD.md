# PRD — Factures (Phase 3, à implémenter)

## Objectif

Importer une ou plusieurs factures fournisseurs, les rattacher à une société et conserver le document source, les données extraites et les lignes de facture.

## Exigences

- Formats PDF/JPG/JPEG/PNG ; sélection multi-fichiers, traitement indépendant par facture et stockage privé.
- États de traitement visibles par document. Upload → OCR asynchrone → persistance de facture/lignes → lancement automatique de l’analyse comptable.
- `invoices.third_party_id` reste nullable avant rapprochement fournisseur.
- Garder le JSON OCR validé et les métadonnées utiles ; le document original s’affiche en regard des informations extraites.
- Détecter les doublons potentiels et demander confirmation ; ne pas créer silencieusement une facture déjà saisie.
- Les champs numériques et fiscaux ne sont jamais considérés fiables uniquement parce qu’ils viennent du modèle.

## État

Non implémenté. Les anciens écrans/routes `documents` du premier prototype ont été retirés. Les anciennes migrations sont conservées pour compatibilité locale ; elles ne représentent pas le futur schéma `invoices`/`invoice_lines`.
