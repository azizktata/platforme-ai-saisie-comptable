# PRD — Documents facture et workflow IA (Phases 3–5)

## Objectif

Importer des factures fournisseur de façon sécurisée, suivre chaque étape de traitement et exposer un résultat compréhensible/auditable aux utilisateurs autorisés. Le flux OCR → extraction → proposition comptable est distinct de toute validation finale.

## Comportement livré

- Import privé de PDF/JPG/JPEG/PNG, validation côté serveur, empreinte SHA-256 par société et confirmation explicite des doublons.
- OCR.space Engine 3 Free est le défaut et produit la transcription texte. Mistral reste sélectionnable et fournit directement une annotation structurée.
- Pour le texte OCR, le modèle d’extraction configurable par défaut `qwen/qwen-2.5-7b-instruct:free` reçoit un JSON Schema strict. La requête demande seulement le JSON ; les explications, raisonnements, calculs ou champs inventés sont interdits. Laravel valide et normalise les valeurs avant persistance. OCR brut, texte, JSON extrait et réponse d’extraction sont conservés séparément.
- Un rejet de JSON mal formé, hors schéma, tronqué ou de type safety-classifier ne modifie pas l’extraction valide existante. La relance d’extraction utilise le texte OCR déjà stocké ; elle n’exécute pas l’OCR.
- Les informations obligatoires sont contrôlées ; en cas de donnée manquante, l’analyse comptable est bloquée et un gestionnaire peut corriger les champs. Les contrôles déterministes ne sont pas délégués au modèle et les totaux ne sont ni calculés ni réconciliés automatiquement.
- La liste Factures ouvre une page de revue dédiée : viewer du PDF/image à côté du panneau éditable sur desktop, empilés sur petit écran. Le viewer propose zoom, ajustement, rotation des images, plein écran et navigation PDF ; la rotation PDF native dépend du navigateur.
- Après complétude, une proposition comptable est établie uniquement avec les référentiels et l’historique de la société. L’utilisateur voit les avertissements et peut corriger, rejeter ou explicitement valider.
- Aucune écriture finale n’est créée avant validation humaine. L’écriture approuvée est liée à la facture, scoped au journal et aux comptes de la société.

## Routes clés

`GET /invoices` (workspace) ; `GET /companies/{company}/invoices/{invoice}` (revue) ; `GET .../preview` (original privé) ; `GET .../details` (données à la demande) ; `POST .../extraction/retry` (réextraction depuis OCR conservé) ; `POST .../ocr/retry` (OCR) ; `PUT .../extraction` et `PUT .../proposal` (corrections) ; `POST .../proposal/reject` et `/proposal/approve` (décision humaine). Toutes sont authentifiées, autorisées et scoppées à la société.

## États visibles

`ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated`; les erreurs gardent une étape dédiée et une relance ciblée. Le workspace actualise la progression des jobs en cours.

## Sécurité

Les originaux restent sur le disque privé. Le téléchargement/aperçu, la transcription et la proposition sont protégés par l’accès société. Les comptes, journaux, tiers et axes choisis sont revalidés côté serveur. Les clés OpenRouter/OCR ne quittent jamais le serveur ; les corps de requête ne sont pas journalisés.

## Limites

OCR.space Free limite les fichiers à 1 Mo et les PDF à trois pages. La disponibilité des modèles gratuits peut varier. L’import Sage, l’export et l’apprentissage des corrections sont différés. Voir `docs/modules/invoice-processing/PRD.md` et `README.md` pour l’implémentation détaillée.
