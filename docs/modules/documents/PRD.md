# PRD — Documents facture et workflow IA (Phases 3–5)

## Objectif

Importer des factures fournisseur de façon sécurisée, suivre chaque étape de traitement et exposer un résultat compréhensible/auditable aux utilisateurs autorisés. Le flux OCR → extraction → proposition comptable est distinct de toute validation finale.

## Comportement livré

- Import privé de PDF/JPG/JPEG/PNG, validation côté serveur, empreinte SHA-256 par société et confirmation explicite des doublons.
- OCR.space Engine 3 Free est le défaut et renvoie la transcription textuelle, affichée dans une boîte de dialogue dédiée. Mistral demeure sélectionnable et fournit directement les champs structurés.
- Pour OCR.space, OpenRouter `openrouter/free` structure le texte en JSON strict conforme au contrat facture. OCR brut, texte, JSON extrait et réponse OpenRouter sont conservés séparément.
- Les informations obligatoires sont contrôlées ; en cas de donnée manquante, l’analyse comptable est bloquée et un gestionnaire peut corriger les champs.
- Après complétude, OpenRouter établit une proposition en se limitant aux référentiels et à l’historique de la société. L’utilisateur voit les avertissements et peut corriger, rejeter ou explicitement valider.
- Aucune écriture finale n’est créée avant validation humaine. L’écriture approuvée est liée à la facture, scoped au journal et aux comptes de la société.

## États visibles

`ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated`; les erreurs gardent une étape dédiée et une relance ciblée. Le workspace actualise la progression des jobs toutes les cinq secondes.

## Sécurité

Les originaux restent sur le disque privé. Le téléchargement, la transcription et la proposition sont protégés par l’accès société. Les comptes, journaux, tiers et axes choisis sont revalidés côté serveur. Les clés OpenRouter/OCR ne quittent jamais le serveur ; les corps de requête ne sont pas journalisés.

## Limites

OCR.space Free limite les fichiers à 1 Mo et les PDF à trois pages. Le routeur gratuit OpenRouter et les modèles disponibles peuvent varier. L’import Sage, l’export et l’apprentissage des corrections sont différés. Voir `docs/modules/invoice-processing/PRD.md` et `docs/modules/accounting-proposals/PRD.md` pour le workflow détaillé.
