# PRD — Traitement OCR des factures (Phase 4)

## Objectif

Après l’enregistrement privé d’une facture, traiter son OCR via un fournisseur remplaçable, conserver sa réponse brute et exposer progression/erreurs sans générer d’écriture comptable. OCR.space Free Engine 3 est le fournisseur actif par défaut ; Mistral reste disponible pour la future offre Pro et l’extraction structurée.

## Parcours livré

1. L’upload crée la facture en `ocr_queued`, puis planifie un job indépendant sur la file `ocr` après la transaction de persistance.
2. Le job est idempotent par société et facture. Il passe en `ocr_processing`, incrémente le nombre de tentatives et enregistre l’horodatage de démarrage.
3. Le contrat `OcrProvider` reçoit la facture autorisée à traiter. Par défaut, `OcrSpaceProvider` envoie le fichier privé en multipart à `POST https://api.ocr.space/parse/image`, avec la clé dans l’en-tête `apikey`, `OCREngine=3`, `language=auto` et `isTable=true`. `MistralOcrProvider` reste disponible via `OCR_PROVIDER=mistral`. Aucun contrôleur n’appelle directement un fournisseur et aucun fichier n’est publié.
4. OCR.space retourne du texte OCR par page, conservé dans la description OCR pour consultation ; les champs structurés (fournisseur, dates, montants, lignes) restent `null` tant qu’un provider ne les fournit pas explicitement. Avec Mistral, `InvoiceOcrSchema` vérifie strictement annotation, types, longueurs, dates, devises, taux, montants décimaux et lignes.
5. Dans une transaction, la transcription structurée disponible, la réponse brute, le modèle, les informations d’usage et les lignes numérotées sont enregistrés ensemble. Le succès passe à `ocr_completed`.
6. Les totaux ne sont jamais recalculés ou corrigés silencieusement. Si les sept montants nécessaires sont présents, le contrôle exact au millime vérifie `HT + TVA + FODEC + autres taxes + timbre − retenue = total`. Un désaccord crée `invoice_total_mismatch`; un composant manquant crée `invoice_totals_unverified`. Ces avertissements ne modifient aucune valeur extraite.
7. Une erreur non récupérable passe à `ocr_failed` immédiatement. Les erreurs réseau transitoires, limitations de débit et erreurs 5xx sont réessayées jusqu’à trois fois avec délais croissants ; les erreurs de configuration TLS/certificat sont non réessayables et affichent un diagnostic sûr. Après épuisement, le job marque l’échec avec un code/message sûr. Un document défaillant n’empêche pas les autres jobs de la file.

## États et visibilité

- `ocr_queued` : accepté dans la file, traitement en attente.
- `ocr_processing` : une tentative est active.
- `ocr_completed` : transcription OCR enregistrée et, si le fournisseur en retourne, champs structurés/lignes sauvegardés ; résultat à vérifier par un humain.
- `ocr_failed` : traitement arrêté ou tentatives épuisées ; message et tentative visibles, relance manuelle disponible aux gestionnaires autorisés.
- `uploaded` : état historique de factures Phase 3. Un gestionnaire peut démarrer manuellement l’OCR depuis la liste.

La page actualise les éléments en cours toutes les cinq secondes, affiche les erreurs, avertissements de totaux et permet la relance. Une transcription OCR n’est ni une proposition comptable ni une écriture validée.

## Espace Factures et vérification OCR

- `/invoices` est l’espace global de travail, accessible depuis un lien fixe de la navigation. La première société accessible triée par nom est présélectionnée ; le sélecteur ne propose ensuite que les sociétés accessibles, et l’import/la relance restent soumis au rôle d’accès.
- `/companies/{company}/invoices` est conservé comme historique complet en lecture/téléchargement pour la société.
- Dans le workspace, l’utilisateur peut sélectionner les factures visibles de la page et exporter les champs affichés au format CSV. L’export concerne uniquement la sélection présente sur la page courante.
- Un gestionnaire autorisé peut marquer en une action les extractions `ocr_completed` sélectionnées comme vérifiées. La migration `2026_09_28_000020` conserve l’horodatage et l’utilisateur (`ocr_reviewed_at`, `ocr_reviewed_by`). Cette vérification porte sur la transcription OCR seulement : elle n’approuve pas de traitement comptable et ne crée aucune écriture.
- Mistral Small, la proposition comptable et la revue/validation d’écriture restent en Phase 5.

## Limites et décisions de Phase 4

- Entrées : PDF/JPG/JPEG/PNG privés. OCR.space Free impose 1 Mo maximum par fichier et 3 pages maximum par PDF ; Mistral conserve la limite applicative de 20 Mo.
- OCR.space utilise le endpoint POST multipart, l’en-tête `apikey`, `OCREngine=3` et la reconnaissance de tables ; le forfait gratuit publie un quota de 500 requêtes/jour par IP et 2 500 conversions Engine 3/mois. Mistral envoie une data URL encodée en base64 au endpoint `/v1/ocr`.
- Le transport HTTPS conserve la vérification des certificats. `OCR_SPACE_CA_BUNDLE` et `MISTRAL_CA_BUNDLE` acceptent des bundles CA PEM optionnels ; aucune option ne désactive la vérification.
- OCR.space ne retourne que du texte OCR : aucune valeur fournisseur/date/montant/ligne n’est devinée ou transformée en extraction structurée. Mistral demeure le fournisseur structurant pour l’offre Pro.
- Les valeurs manquantes, ambiguës ou illisibles restent `null`. Les montants/taux sont stockés avec trois décimales ; les taux globaux ne sont retournés que lorsqu’un taux unique est explicitement imprimé. Les taux par ligne restent dans `invoice_lines`.
- Le contrôle de total n’affirme pas qu’une composante absente vaut zéro : il signale que le total ne peut pas être vérifié.
- La réponse brute est conservée pour l’audit, y compris lorsqu’une annotation JSON est rejetée. Les secrets et corps de réponse ne sont pas écrits dans les logs.
- Les factures `uploaded` préexistantes ne sont pas retraitées par une migration ; elles peuvent être mises en file depuis l’interface.

## Critères d’acceptation

1. Chaque nouveau fichier accepté déclenche un job OCR distinct après sa persistance.
2. Le provider configurable envoie le fichier privé avec le protocole adapté : multipart OCR.space Engine 3 ou data URL/annotation JSON Mistral.
3. La transcription structurée disponible, les lignes (si fournies), le modèle, l’usage, les horodatages, le statut et la réponse brute sont enregistrés atomiquement.
4. Une réponse non valide ou une configuration manquante n’écrit aucune extraction partielle et présente un échec exploitable.
5. Les erreurs temporaires sont réessayées sans bloquer la file ; la relance est limitée aux factures historiques/en échec et soumise au rôle société.
6. Une incohérence ou un contrôle impossible est explicitement visible, sans réécriture des valeurs OCR.
7. Le périmètre société et le stockage privé sont préservés, et aucun compte, journal, proposition ou écriture comptable n’est créé.
8. Les appels HTTP des tests sont simulés ; aucun secret Mistral réel n’est requis.
9. Le workspace global présélectionne la première société accessible, limite le sélecteur/actions/historique au périmètre autorisé et conserve l’URL historique société.
10. L’export CSV ne comprend que la sélection de la page courante ; la revue groupée n’accepte que des extractions OCR terminées et ne réalise aucune validation comptable.
11. Le client TLS valide toujours la chaîne de certificats ; un bundle PEM optionnel peut être configuré, et une erreur de confiance CA est affichée comme un problème de configuration non réessayable.
12. OCR.space Free utilise un upload multipart avec la clé API en header et Engine 3 ; la transcription textuelle reste visible sans inventer de champs facture structurés.
