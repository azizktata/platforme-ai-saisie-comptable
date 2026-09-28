# PRD — Traitement OCR des factures (Phase 4)

## Objectif

Après l’enregistrement privé d’une facture, extraire ses informations et lignes avec Mistral OCR, valider strictement la réponse, conserver la preuve brute utile à l’audit et exposer progression, erreurs et incohérences sans générer d’écriture comptable.

## Parcours livré

1. L’upload crée la facture en `ocr_queued`, puis planifie un job indépendant sur la file `ocr` après la transaction de persistance.
2. Le job est idempotent par société et facture. Il passe en `ocr_processing`, incrémente le nombre de tentatives et enregistre l’horodatage de démarrage.
3. Le contrat `OcrProvider` reçoit la facture autorisée à traiter. `MistralOcrProvider` lit le fichier du disque privé `local` et envoie un PDF ou une image encodée en data URL à `/v1/ocr`. Aucun contrôleur n’appelle directement le fournisseur et aucun fichier n’est publié.
4. Mistral retourne une annotation JSON contrainte par un schéma strict. `InvoiceOcrSchema` vérifie champs, types, longueurs, dates, devises, taux, montants décimaux et lignes. Les sorties inconnues, tronquées ou invalides ne sont pas persistées comme données de facture.
5. Dans une transaction, les champs validés, le JSON structuré, la réponse Mistral complète, le modèle, les informations d’usage et les lignes numérotées sont enregistrés ensemble. Le succès passe à `ocr_completed`.
6. Les totaux ne sont jamais recalculés ou corrigés silencieusement. Si les sept montants nécessaires sont présents, le contrôle exact au millime vérifie `HT + TVA + FODEC + autres taxes + timbre − retenue = total`. Un désaccord crée `invoice_total_mismatch`; un composant manquant crée `invoice_totals_unverified`. Ces avertissements ne modifient aucune valeur extraite.
7. Une erreur non récupérable passe à `ocr_failed` immédiatement. Les erreurs réseau, limitations de débit et erreurs 5xx sont réessayées trois fois avec délais croissants ; après épuisement, le job marque l’échec et conserve un code/message sûr. Un document défaillant n’empêche pas les autres jobs de la file.

## États et visibilité

- `ocr_queued` : accepté dans la file, traitement en attente.
- `ocr_processing` : une tentative est active.
- `ocr_completed` : annotation structurée et lignes enregistrées ; extraction à vérifier par un humain.
- `ocr_failed` : traitement arrêté ou tentatives épuisées ; message et tentative visibles, relance manuelle disponible aux gestionnaires autorisés.
- `uploaded` : état historique de factures Phase 3. Un gestionnaire peut démarrer manuellement l’OCR depuis la liste.

La page actualise les éléments en cours toutes les cinq secondes, affiche les erreurs, avertissements de totaux et permet la relance. L’annotation reste une extraction et n’est ni une proposition comptable ni une écriture validée. Mistral Small, l’analyse comptable et la revue d’écriture restent en Phase 5.

## Limites et décisions de Phase 4

- Entrées : PDF/JPG/JPEG/PNG privés, 20 Mo maximum par fichier.
- Transport vers Mistral : data URL encodée en base64 ; aucun upload préalable dans l’espace Fichiers Mistral.
- Les valeurs manquantes, ambiguës ou illisibles restent `null`. Les montants/taux sont stockés avec trois décimales ; les taux globaux ne sont retournés que lorsqu’un taux unique est explicitement imprimé. Les taux par ligne restent dans `invoice_lines`.
- Le contrôle de total n’affirme pas qu’une composante absente vaut zéro : il signale que le total ne peut pas être vérifié.
- La réponse brute est conservée pour l’audit, y compris lorsqu’une annotation JSON est rejetée. Les secrets et corps de réponse ne sont pas écrits dans les logs.
- Les factures `uploaded` préexistantes ne sont pas retraitées par une migration ; elles peuvent être mises en file depuis l’interface.

## Critères d’acceptation

1. Chaque nouveau fichier accepté déclenche un job OCR distinct après sa persistance.
2. PDF/image, métadonnées, annotation JSON et schéma de requête sont envoyés au provider depuis un service remplaçable.
3. Les champs validés et toutes les lignes sont enregistrés atomiquement avec modèle, usage, horodatages, statut et réponse brute.
4. Une réponse non valide ou une configuration manquante n’écrit aucune extraction partielle et présente un échec exploitable.
5. Les erreurs temporaires sont réessayées sans bloquer la file ; la relance est limitée aux factures historiques/en échec et soumise au rôle société.
6. Une incohérence ou un contrôle impossible est explicitement visible, sans réécriture des valeurs OCR.
7. Le périmètre société et le stockage privé sont préservés, et aucun compte, journal, proposition ou écriture comptable n’est créé.
8. Les appels HTTP des tests sont simulés ; aucun secret Mistral réel n’est requis.
