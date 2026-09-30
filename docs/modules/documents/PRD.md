# PRD — Documents facture et workflow IA (Phases 3–5)

## Objectif

Importer des factures fournisseur de façon sécurisée, suivre chaque étape de traitement et exposer un résultat compréhensible/auditable aux utilisateurs autorisés. Le flux OCR → extraction → proposition comptable est distinct de toute validation finale.

## Comportement livré

- Import privé de PDF/JPG/JPEG/PNG, validation côté serveur, empreinte SHA-256 par société et confirmation explicite des doublons.
- L’import lance automatiquement OCR.space Engine 3 Free puis l’extraction/proposition ; il ne devient pas une tâche à démarrer manuellement. Mistral reste sélectionnable et fournit directement une annotation structurée.
- Pour le texte OCR, le modèle d’extraction configurable par défaut `qwen/qwen-2.5-7b-instruct:free` reçoit un JSON Schema strict. La requête demande seulement le JSON ; les explications, raisonnements, calculs ou champs inventés sont interdits. Laravel valide et normalise avant persistance. OCR brut, texte, JSON extrait et réponse d’extraction sont conservés séparément.
- Un rejet de JSON mal formé, hors schéma, tronqué ou de type safety-classifier ne modifie pas l’extraction valide existante. La relance d’extraction utilise le texte OCR déjà stocké ; elle n’exécute pas l’OCR.
- Les informations obligatoires sont contrôlées ; en cas de donnée manquante, l’analyse comptable est bloquée et un gestionnaire peut corriger les champs. Les contrôles déterministes ne sont pas délégués au modèle et aucun montant n’est recalculé automatiquement. Un bouton explicite effectue localement un recalcul déterministe sans LLM ; les valeurs vides demandent confirmation avant d’être traitées comme zéro.
- La liste Factures donne les statuts, compteurs, confiance de proposition (si les lignes la fournissent), actions d’ouverture/téléchargement/relance, import par glisser-déposer et relance groupée des seuls états éligibles. Un PDF fictif d’exemple clairement étiqueté est disponible au téléchargement ou peut être ajouté volontairement à la sélection ; il n’est jamais inséré comme facture sans import explicite.
- La revue dédiée est une page à deux colonnes : original et résumé extrait/financier à gauche ; workspace IA/comptabilité obligatoire à droite. En tête, la confiance globale et les barres par champ ne montrent que des scores réellement fournis (dimensions absentes `N/D`). Cinq cartes ordonnées couvrent (1) les données extraites éditables et le recalcul explicite, (2) le type de facture et le profil d’activité réel, (3) la proposition brouillon modifiable avec totaux et régénération, (4) les contrôles verticaux et (5) les décisions humaines.
- Les contrôles verticaux distinguent vérifié, avertissement, bloquant, en attente et indisponible. Le TTC brut est contrôlé hors retenue ; le net est contrôlé séparément. Le numéro de facture sans registre anti-doublon et l’exercice fiscal sans configuration ne sont jamais présentés comme réussis. La devise différente sans conversion bloque la validation.
- Après complétude, une proposition comptable est établie uniquement avec les référentiels et l’historique de la société ; le type de facture est choisi parmi des catégories persistées et contextualisé par l’activité/secteur de l’entreprise. L’utilisateur voit les avertissements et peut corriger, régénérer un brouillon éligible, rejeter ou explicitement valider.
- Un CSV interne permet d’exporter le brouillon ou l’écriture validée. L’export final est horodaté et suivi par le statut `accounting_exported` ; il n’y a ni export certifié ni synchronisation Sage.
- Aucune écriture finale n’est créée avant validation humaine. L’écriture approuvée est liée à la facture et scoped au journal et aux comptes de la société.

## Routes clés

`GET /invoices` (workspace) ; `POST /companies/{company}/invoices/upload` (import avec traitement automatique) ; `POST /companies/{company}/invoices/analyze-all` (relance éligible) ; `GET /companies/{company}/invoices/{invoice}` (revue) ; `GET .../preview` (original privé) ; `GET .../details` (données/contrôles à la demande) ; `POST .../extraction/retry` (réextraction depuis OCR conservé) ; `POST .../ocr/retry` (reprise d’étape échouée) ; `PUT .../extraction` et `PUT .../proposal` (corrections) ; `POST .../proposal/regenerate` (régénération gardée) ; `POST .../proposal/reject` et `/proposal/approve` (décision humaine) ; `POST .../proposal/export-csv` (CSV interne). Toutes sont authentifiées, autorisées et scoppées à la société.

## États visibles

`ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated`, `accounting_exported`; les erreurs gardent une étape dédiée et une relance ciblée. Le workspace actualise la progression des jobs en cours. `accounting_exported` signifie qu’un CSV interne a été généré pour une écriture validée, sans présumer d’une intégration externe.

## Sécurité

Les originaux restent sur le disque privé. Le téléchargement/aperçu, la transcription et la proposition sont protégés par l’accès société. Les comptes, journaux, tiers et axes choisis sont revalidés côté serveur. Les clés OpenRouter/OCR ne quittent jamais le serveur ; les corps de requête ne sont pas journalisés.

## Limites

OCR.space Free limite les fichiers à 1 Mo et les PDF à trois pages. La disponibilité des modèles gratuits peut varier. L’import/export Sage réel, l’exercice fiscal configurable, la conversion multidevise et l’apprentissage des corrections sont différés. Voir `docs/modules/invoice-processing/PRD.md` et `README.md` pour les détails.
