# PRD — Traitement et revue des factures

## Objectif

Importer chaque facture dans un stockage privé, conserver les sources d’audit et dérouler OCR → extraction structurée → contrôles déterministes → proposition comptable. Une personne reste responsable de la revue : aucune écriture comptable définitive n’est créée sans validation humaine explicite.

## Parcours livré

1. Un PDF/JPG/JPEG/PNG est autorisé dans le périmètre société, hashé et enregistré dans `companies/{company}/invoices`. L’import planifie `ProcessInvoiceOcr` automatiquement ; l’utilisateur n’a pas à démarrer l’analyse.
2. OCR.space Free Engine 3 est le fournisseur OCR par défaut. Il fournit le texte conservé dans `ocr_text` et la réponse brute dans `ocr_response`. Mistral demeure sélectionnable via `OCR_PROVIDER=mistral` et retourne une annotation structurée, soumise à la même validation Laravel.
3. Pour le texte OCR, `ExtractInvoiceData` utilise le fournisseur et le modèle d’extraction configurés séparément du fournisseur OCR et du modèle comptable. Le défaut OpenRouter est `qwen/qwen-2.5-7b-instruct:free`. La requête contient le JSON Schema strict de `InvoiceOcrSchema` et une consigne JSON uniquement : aucun champ inventé, explication, raisonnement, calcul, réconciliation ou décision comptable. La réponse doit être un objet JSON conforme et fini normalement ; contenu mal formé, incomplet ou de type safety-classifier est refusé. Laravel normalise et valide dates, devises, taux et montants avant toute persistance.
4. La réponse OCR et la réponse d’extraction sont auditées séparément (`ocr_response`/`ocr_text` et `extraction_response`/`extraction_model`/`extraction_usage`). L’extraction normalisée est synchronisée de manière transactionnelle dans `ocr_data`, les colonnes de `invoices` et `invoice_lines`. Un échec de nouvelle extraction ne remplace pas les dernières données facture valides ni les lignes, et ne supplante pas la proposition prête antérieure.
5. Les contrôles déterministes de complétude et de cohérence sont exécutés côté Laravel, séparément de l’extraction. Le TTC est le total brut HT + TVA + FODEC + autres taxes + timbre ; la retenue n’est pas soustraite du TTC, mais est déduite séparément du net à payer. Les incohérences sont signalées sans modifier les champs extraits ; l’analyse comptable reste bloquée tant que les champs requis ne sont pas complets.
6. Le gestionnaire dispose d’un bouton explicite « Recalculer à partir de ces montants ». Il calcule localement, en millimes et sans appel LLM, les taux/TVA, FODEC, TTC brut et net à payer à partir des champs saisis. Les composants vides demandent confirmation avant d’être traités comme zéro ; le calcul ne s’applique jamais automatiquement et les montants restent à enregistrer/vérifier par l’humain.
7. Lorsque la facture est complète, `AnalyzeAccountingProposal` utilise uniquement le contexte comptable autorisé de la société (profil, référentiels actifs et historique récent). La proposition inclut une catégorie de facture persistée parmi les choix définis ; ses avertissements et contrôles restent modifiables et audités.
8. La liste propose `Tout analyser` pour `uploaded` et les statuts d’échec OCR/extraction/analyse. L’action relance l’étape appropriée, reste scoped à la société et ne change pas le démarrage automatique des nouveaux imports.
9. Le gestionnaire peut enregistrer les corrections, régénérer une proposition prête/rejetée ou une analyse échouée (en conservant les versions précédentes), rejeter ou valider explicitement. Le rejet ne crée aucune écriture ; seule la validation crée une écriture liée à la facture. L’approbation bloque les incohérences TTC/TVA/net, l’équilibre, les références actives ou la devise sans conversion.
10. `Export CSV` télécharge un brouillon de proposition ou une écriture déjà validée. L’export de l’écriture finale inscrit `accounting_exported_at` et `accounting_exported_by`, puis l’état `accounting_exported`. Le fichier est un CSV interne ; il ne constitue pas un export certifié ou une synchronisation Sage.

## Workspace et composants

- `GET /invoices` présélectionne une société accessible ; l’utilisateur peut sélectionner une autre société autorisée. Le workspace affiche des compteurs par état, une zone de dépôt glisser-déposer et un tableau avec document, fournisseur, numéro, date, TTC, confiance de proposition et statut. Les actions de chaque ligne ouvrent, téléchargent ou relancent selon l’état.
- La confiance de liste/détail est la moyenne des confiances des lignes comptables fournies par la proposition. Elle est intitulée « confiance de proposition » ; aucun score OCR ou score numérique non fourni n’est inventé.
- Aucun jeu de factures d’exemple n’est seedé et aucun document fictif n’est injecté automatiquement dans une société. Le PDF statique `public/examples/facture-exemple.pdf`, explicitement marqué fictif, peut être téléchargé ou ajouté à la sélection ; il n’est importé/traité qu’après action de l’utilisateur et passe alors par le même workflow que les factures réelles.
- `GET /companies/{company}/invoices/{invoice}` rend `resources/js/Pages/Invoices/Show.tsx`. Sur grand écran, le document et le résumé extrait/financier sont à gauche ; le workspace IA/comptabilité obligatoire est à droite. Sur petit écran, les colonnes s’empilent.
- `InvoiceDocumentViewer` affiche l’original depuis une route privée d’aperçu. Il prend en charge image/PDF, zoom, ajustement, rotation des images, plein écran et navigation des pages PDF ; les contrôles natifs PDF dépendent du navigateur.
- `InvoiceReviewPanel` présente en tête la confiance globale et les barres par champ fondées uniquement sur les scores effectivement fournis ; les scores absents sont `N/D`. Le workspace ordonne cinq cartes : (1) données extraites modifiables/recalcul explicite, (2) type de facture avec profil réel de la société, (3) écriture brouillon éditable, (4) contrôles verticaux, (5) décision du comptable. L’import en file reste automatiquement traité.
- Le recalcul explicite des montants est déterministe, local et sans appel IA ; il ne remplace pas l’extraction ni la validation serveur. Les contrôles distinguent vérifié, avertissement, bloquant, attente et indisponible. Une référence de facture n’est pas déclarée contrôlée contre les doublons faute de registre de numéros ; l’exercice fiscal reste « non configuré ».
- Le texte OCR est disponible dans un panneau repliable. Les succès et erreurs d’action produisent des toasts.

## Routes et actions

- `GET /invoices` : workspace multi-société.
- `GET /companies/{company}/invoices` : historique d’une société.
- `GET /companies/{company}/invoices/{invoice}` : page de revue dédiée.
- `GET /companies/{company}/invoices/{invoice}/preview` : aperçu privé PDF/image.
- `GET /companies/{company}/invoices/{invoice}/details` : détails et contrôles JSON privés, utilisés par le workspace.
- `POST /companies/{company}/invoices/upload` : import privé et dispatch automatique de l’OCR.
- `POST /companies/{company}/invoices/analyze-all` : relance groupée des seuls statuts éligibles.
- `PUT /companies/{company}/invoices/{invoice}/extraction` : enregistrer les champs facture corrigés ; les contrôles et le workflow existants s’appliquent.
- `POST /companies/{company}/invoices/{invoice}/extraction/retry` : relancer uniquement l’extraction à partir de `ocr_text` déjà enregistré ; ne dispatch jamais OCR.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` : relancer la prochaine étape éligible après échec.
- `PUT .../proposal`, `POST .../proposal/regenerate`, `POST .../proposal/reject`, `POST .../proposal/approve` : correction, régénération gardée, rejet et validation humaine de la proposition.
- `POST .../proposal/export-csv` : export du brouillon prêt ou de l’écriture finalisée.

Toutes les routes contrôlent le rôle et l’accès société, y compris l’aperçu et les mutations. Un identifiant appartenant à une autre société ne permet pas de lire ou modifier le document.

## États et erreurs

Les états métier incluent `ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated`, `accounting_exported` et les erreurs dédiées (`ocr_failed`, `data_extraction_failed`, `accounting_analysis_failed`). `ocr_completed` reste un état historique. La liste réduit les libellés en étapes compréhensibles (`À analyser`, `En analyse`, `À vérifier`, `Validée`, `Exportée`) tout en conservant les statuts métier.

Une réponse d’extraction invalide ou safety-classifier n’est pas normalisée ni persistée. Un échec conserve la transcription OCR, les lignes et les derniers champs structurés valides ; l’erreur exploitable est affichée dans la revue et les informations techniques restent auditées sans exposer de secret.

## Critères d’acceptation

1. Les factures restent isolées par société/cabinet ; les lignes de liste ouvrent la route de revue dédiée.
2. L’import lance automatiquement OCR puis le workflow IA ; l’action groupée ne prend que les états en échec/non démarrés.
3. La liste expose les colonnes demandées, une confiance seulement lorsqu’elle existe et des statuts compréhensibles.
4. Le viewer et le workspace ont des défilements distincts sur desktop et s’empilent sur mobile ; les contrôles disponibles sont accessibles.
5. Les données fournisseur, client, métadonnées, lignes, taxes/totaux et coordonnées bancaires restent éditables par un rôle autorisé.
6. Aucun total n’est recalculé ou écrasé implicitement ; un bouton explicite calcule localement et de façon déterministe, sans LLM, et les champs vides exigent confirmation avant d’être traités comme zéro.
7. Le rejet ne crée rien ; seules les validations humaines explicites créent une écriture. La validation bloque les incohérences TTC/TVA/net, déséquilibres, références invalides et devises sans conversion.
8. L’exercice fiscal et le contrôle anti-doublon par numéro absents ne sont pas présentés comme réussis ; l’export CSV interne n’est pas présenté comme une intégration Sage.
9. Les actions et erreurs affichent un retour clair/toast ; les tests HTTP utilisent des réponses simulées, sans clé fournisseur réelle.
