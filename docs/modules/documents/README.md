# Factures — intake et OCR (Phases 3–4)

## Schéma et stockage

- Les migrations `2026_09_28_000015` et `...000016` créent `invoices` et `invoice_lines`. `...000017` ajoute les états/métadonnées OCR et `...000020` la traçabilité de revue (`ocr_reviewed_at`, `ocr_reviewed_by`).
- `Invoice` expose la société, le tiers éventuel, l’utilisateur importateur, le vérificateur OCR et les lignes.
- Les migrations historiques `documents` et `accounting_entries` sont préservées sans copie ni suppression de données.
- `StoreInvoiceUpload` écrit sur le disque privé `local` sous `companies/{id}/invoices`; ni chemin privé ni réponse OCR brute ne sont envoyés aux pages Inertia. Le téléchargement est authentifié, scoped à la société et servi sans cache.

## Parcours et autorisations

- `GET /invoices` (`invoices.index`) est le workspace global, accessible depuis le lien fixe « Factures ». Sans `company_id`, il laisse choisir une société ; avec sélection, il affiche les imports récents de cette seule société.
- `GET /companies/{company}/invoices` (`companies.invoices.index`) reste l’historique complet en lecture/téléchargement pour une société.
- `POST /companies/{company}/invoices/upload` reçoit un fichier par requête. Le Form Request exige un administrateur ou gestionnaire affecté et valide PDF/JPG/JPEG/PNG, 20 Mo maximum.
- L’interface envoie séquentiellement jusqu’à 300 fichiers. Les erreurs sont attribuées au bon fichier ; une défaillance n’annule pas les imports réussis. Un doublon SHA-256 par société retourne `409` avant stockage ; `confirm_duplicate` permet explicitement de le conserver également.
- Les autres membres autorisés peuvent consulter/télécharger sans importer. Les permissions sont contrôlées par les policies et Form Requests, pas seulement masquées dans l’interface.
- La sélection de la page courante peut être téléchargée au format CSV. Seuls les rôles de gestion autorisés peuvent marquer en lot des extractions `ocr_completed` comme vérifiées ; le contrôleur vérifie l’appartenance des factures à la société et leur statut dans une transaction. Cette revue ne valide pas d’écriture comptable.

## Traitement OCR

`ProcessInvoiceOcr` est dispatché après la persistance sur la connexion `database` et la file `ocr`. Il appelle `OcrProvider`, actuellement lié à `MistralOcrProvider`, lequel lit un PDF/image sur le disque privé et l’envoie au endpoint `POST /v1/ocr` avec un schéma d’annotation JSON strict. Les champs validés, lignes, avertissements, réponse brute et usage sont enregistrés transactionnellement.

Les erreurs non récupérables mettent la facture en échec ; les erreurs temporaires sont réessayées. Les diagnostics de connexion sont raccourcis et nettoyés des URL et Bearer tokens avant journalisation. Les paramètres `table_format` et `include_image_base64` ne changent pas la connectivité HTTP. Ne jamais exposer ou journaliser `MISTRAL_API_KEY`.

La liste actualise les factures en cours, montre les états/erreurs/avertissements et permet une relance autorisée. L’historique société n’est pas supprimé par le nouveau workspace.

## Vérification

- `tests/Feature/InvoiceIntakeTest.php` couvre upload, stockage privé, rôles, accès société, historique/workspace, doublons, téléchargement, relance et revue groupée.
- `tests/Feature/InvoiceOcrProcessingTest.php` simule le fournisseur via `Http::fake()`.
- Exécuter `composer test` avec PHP 8.3+ et Composer ; `npm run build` valide l’interface.
