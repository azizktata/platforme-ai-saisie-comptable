# Factures — intake et OCR (Phases 3–4)

## Schéma et stockage

- Les migrations `2026_09_28_000015` et `...000016` créent `invoices` et `invoice_lines`. `...000017` ajoute les états/métadonnées OCR et `...000020` la traçabilité de revue (`ocr_reviewed_at`, `ocr_reviewed_by`).
- `Invoice` expose la société, le tiers éventuel, l’utilisateur importateur, le vérificateur OCR et les lignes.
- Les migrations historiques `documents` et `accounting_entries` sont préservées sans copie ni suppression de données.
- `StoreInvoiceUpload` écrit sur le disque privé `local` sous `companies/{id}/invoices`; aucun chemin privé ni réponse brute fournisseur ne sont envoyés aux pages Inertia. La transcription OCR (`description`) est affichée aux utilisateurs autorisés dans l’espace Factures. Le téléchargement est authentifié, scoped à la société et servi sans cache.

## Parcours et autorisations

- `GET /invoices` (`invoices.index`) est le workspace global, accessible depuis le lien fixe « Factures ». Sans `company_id`, il présélectionne la première société accessible triée par nom ; `company_id` choisit une autre société et l’écran affiche seulement ses imports récents.
- `GET /companies/{company}/invoices` (`companies.invoices.index`) reste l’historique complet en lecture/téléchargement pour une société.
- `POST /companies/{company}/invoices/upload` reçoit un fichier par requête. Le Form Request exige un administrateur ou gestionnaire affecté et valide PDF/JPG/JPEG/PNG ; la limite suit le fournisseur actif (1 Mo OCR.space Free, 20 Mo Mistral).
- L’interface envoie séquentiellement jusqu’à 300 fichiers. Les erreurs sont attribuées au bon fichier ; une défaillance n’annule pas les imports réussis. Un doublon SHA-256 par société retourne `409` avant stockage ; `confirm_duplicate` permet explicitement de le conserver également.
- Les autres membres autorisés peuvent consulter/télécharger sans importer. Les permissions sont contrôlées par les policies et Form Requests, pas seulement masquées dans l’interface.
- La sélection de la page courante peut être téléchargée au format CSV. Seuls les rôles de gestion autorisés peuvent marquer en lot des extractions `ocr_completed` comme vérifiées ; le contrôleur vérifie l’appartenance des factures à la société et leur statut dans une transaction. Cette revue ne valide pas d’écriture comptable.

## Traitement OCR

`ProcessInvoiceOcr` est dispatché après la persistance sur la connexion `database` et la file `ocr`. Le contrat configurable `OcrProvider` utilise OCR.space Free Engine 3 par défaut (POST multipart, clé dans l’en-tête `apikey`, tables activées) ou Mistral via `OCR_PROVIDER=mistral`. OCR.space conserve une transcription textuelle sans inventer de champs structurés ; Mistral valide son annotation JSON avec `InvoiceOcrSchema`. Les données normalisées disponibles, lignes, avertissements, réponse brute, modèle et usage sont enregistrés transactionnellement.

Les erreurs non récupérables mettent la facture en échec ; les erreurs temporaires sont réessayées. Les diagnostics de connexion sont raccourcis et nettoyés avant journalisation ; les clés API ne sont jamais placées dans l’URL ni journalisées. Le texte OCR est consultable dans la ligne Factures ; vérifier les champs structurés avant tout traitement comptable.

La liste actualise les factures en cours, montre les états/erreurs/avertissements et permet une relance autorisée. L’historique société n’est pas supprimé par le nouveau workspace.

## Vérification

- `tests/Feature/InvoiceIntakeTest.php` couvre upload, stockage privé, rôles, accès société, historique/workspace, doublons, téléchargement, relance et revue groupée.
- `tests/Feature/InvoiceOcrProcessingTest.php` simule le fournisseur via `Http::fake()`.
- Exécuter `composer test` avec PHP 8.3+ et Composer ; `npm run build` valide l’interface.
