# PRD — Traitement et revue des factures

## Objectif

Importer chaque facture dans un stockage privé, conserver les sources d’audit et dérouler OCR → extraction structurée → contrôles déterministes → proposition comptable. Une personne reste responsable de la revue : aucune écriture comptable définitive n’est créée sans validation humaine explicite.

## Parcours livré

1. Un PDF/JPG/JPEG/PNG est autorisé dans le périmètre société, hashé et enregistré dans `companies/{company}/invoices`. L’import planifie `ProcessInvoiceOcr` sur la file `ocr`.
2. OCR.space Free Engine 3 est le fournisseur OCR par défaut. Il fournit le texte conservé dans `ocr_text` et la réponse brute dans `ocr_response`. Mistral demeure sélectionnable via `OCR_PROVIDER=mistral` et retourne une annotation structurée, soumise à la même validation Laravel.
3. Pour le texte OCR, `ExtractInvoiceData` utilise le fournisseur et le modèle d’extraction configurés séparément du fournisseur OCR et du modèle comptable. Le défaut OpenRouter est `qwen/qwen-2.5-7b-instruct:free`. La requête contient le JSON Schema strict de `InvoiceOcrSchema` et une consigne JSON uniquement : aucun champ inventé, explication, raisonnement, calcul, réconciliation ou décision comptable. La réponse doit être un objet JSON conforme et fini normalement ; contenu mal formé, incomplet ou de type safety-classifier est refusé. Laravel normalise et valide dates, devises, taux et montants avant toute persistance.
4. La réponse OCR et la réponse d’extraction sont auditées séparément (`ocr_response`/`ocr_text` et `extraction_response`/`extraction_model`/`extraction_usage`). L’extraction normalisée est synchronisée de manière transactionnelle dans `ocr_data`, les colonnes de `invoices` et `invoice_lines`. Un échec de nouvelle extraction ne remplace pas les dernières données facture valides ni les lignes, et ne supplante pas la proposition prête antérieure.
5. Les contrôles déterministes de complétude et de cohérence des totaux sont exécutés côté Laravel, séparément de l’extraction. Ils signalent les éléments manquants ou incohérents sans inventer, recalculer ou écraser les totaux extraits. L’analyse comptable reste bloquée tant que les champs requis ne sont pas complets.
6. Lorsque la facture est complète, `AnalyzeAccountingProposal` utilise uniquement le contexte comptable autorisé de la société (profil, référentiels actifs et historique récent). La proposition, ses avertissements et ses contrôles restent modifiables et audités.
7. Le gestionnaire peut enregistrer les corrections, rejeter la proposition ou la valider explicitement. Seule cette validation crée une écriture liée à la facture ; aucun transfert Sage n’est effectué.

## Workspace et composants

- `GET /invoices` présente le workspace et présélectionne une société autorisée ; l’utilisateur peut choisir une autre société à laquelle il a accès. Les liens de facture ouvrent une page dédiée, et non une boîte de dialogue.
- `GET /companies/{company}/invoices/{invoice}` rend `resources/js/Pages/Invoices/Show.tsx`. Sur grand écran, `InvoiceDocumentViewer` et `InvoiceReviewPanel` sont côte à côte et chacun peut défiler indépendamment ; sur petit écran, ils s’empilent.
- `InvoiceDocumentViewer` affiche l’original depuis une route privée d’aperçu. Il prend en charge image/PDF, zoom, ajustement, rotation des images, plein écran et navigation des pages PDF ; les contrôles natifs PDF (dont rotation) dépendent du navigateur. Le téléchargement reste disponible.
- `InvoiceReviewPanel` charge les détails à la demande et conserve le workflow de proposition existant. Les champs sont organisés par fournisseur, client, informations facture, lignes et totaux/taxes/coordonnées bancaires. Les totaux extraits sont édités explicitement ; l’interface ne les recalcule pas.
- Les onglets affichent le texte OCR, le résumé de facture et la proposition. L’état, le modèle et la confiance ne sont montrés que si la donnée est disponible. Les actions réussies/échouées donnent un retour toast.
- `GET /companies/{company}/invoices/{invoice}/preview` sert le document avec autorisation société et `private, no-store`. Le contenu OCR brut n’est pas transmis dans les props de la liste.

## Routes et actions

- `GET /invoices` : workspace global ; sélection d’entreprise autorisée.
- `GET /companies/{company}/invoices` : historique d’une société.
- `GET /companies/{company}/invoices/{invoice}` : page de revue dédiée.
- `GET /companies/{company}/invoices/{invoice}/preview` : aperçu privé PDF/image.
- `GET /companies/{company}/invoices/{invoice}/details` : détails JSON privés, utilisés par le panneau.
- `POST /companies/{company}/invoices/upload` : import privé, doublon contrôlé par hash au niveau société.
- `PUT /companies/{company}/invoices/{invoice}/extraction` : enregistrer les champs facture corrigés ; les contrôles et le workflow existants s’appliquent.
- `POST /companies/{company}/invoices/{invoice}/extraction/retry` : relancer uniquement l’extraction à partir de `ocr_text` déjà enregistré ; ne dispatch jamais OCR. Refusé sans texte OCR, pendant un traitement actif ou après création d’écriture.
- `POST /companies/{company}/invoices/{invoice}/ocr/retry` : relancer l’OCR dans les états où cette action est admise.
- `PUT .../proposal`, `POST .../proposal/reject`, `POST .../proposal/approve` : correction, rejet et validation humaine de la proposition.

Toutes les routes contrôlent le rôle et l’accès société, y compris l’aperçu et les mutations. Un identifiant appartenant à une autre société ne permet pas de lire le document.

## États et erreurs

Les états métier incluent `ocr_queued`, `ocr_processing`, `data_extraction`, `invoice_incomplete`, `accounting_analysis`, `proposal_ready`, `proposal_rejected`, `accounting_validated` et les erreurs dédiées (`ocr_failed`, `data_extraction_failed`, `accounting_analysis_failed`). L’interface actualise les traitements en cours.

Une réponse d’extraction invalide ou safety-classifier n’est pas normalisée ni persistée. Un échec conserve la transcription OCR, les lignes et les derniers champs structurés valides ; l’erreur exploitable est affichée dans la revue et les informations techniques restent auditées sans exposer de secret. Une extraction complète avec des champs incomplets peut être conservée pour correction, mais ne déclenche pas l’analyse comptable.

## Critères d’acceptation

1. Les factures restent isolées par société/cabinet ; les liens de liste ouvrent la route de revue dédiée.
2. Le viewer et le panneau ont des défilements distincts sur desktop et s’empilent sur mobile ; les contrôles disponibles sont accessibles.
3. Les données fournisseur, client, métadonnées, lignes, taxes/totaux et coordonnées bancaires sont éditables par un rôle autorisé.
4. Aucun total n’est recalculé ou écrasé implicitement ; les contrôles déterministes signalent les incohérences indépendamment du LLM.
5. La relance extraction réutilise `ocr_text`, ne programme pas OCR et ne détruit pas une extraction antérieure valide en cas d’échec.
6. Les réponses hors schéma, mal formées, tronquées ou de type safety-classifier ne deviennent jamais des données facture.
7. Les actions de proposition restent disponibles selon le workflow existant ; seules les validations humaines explicites créent une écriture.
8. Les mutations et erreurs affichent un retour clair/toast ; tests HTTP utilisent des réponses simulées, sans clé fournisseur réelle.
