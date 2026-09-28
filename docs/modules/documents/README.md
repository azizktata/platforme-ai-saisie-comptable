# Factures — documentation technique (Phase 3)

## Schéma et modèles

- Migrations `2026_09_28_000015` et `2026_09_28_000016` créent `invoices` et `invoice_lines`. Les champs OCR/montants sont nullable avant extraction ; les lignes sont prévues mais ne sont pas remplies à l’upload.
- `Invoice` expose les relations `company`, `thirdParty`, `uploader` et `lines`. `Company`, `ThirdParty` et `User` exposent leurs relations facture.
- Chaque facture stocke son disque, son chemin privé, le nom original, le MIME détecté, la taille, l’empreinte SHA-256, l’état et les champs métier déjà connus. Les codes d’état actuels se limitent à `uploaded`.
- Les migrations du premier prototype (`documents`, `accounting_entries`) sont préservées sans copie ou suppression de données.

## Parcours HTTP et autorisation

- `GET /companies/{company}/invoices` (`companies.invoices.index`) affiche les factures de la société autorisée, paginées par 20. Les chemins privés ne sont pas sérialisés.
- `POST /companies/{company}/invoices/upload` (`companies.invoices.upload`) accepte un seul fichier par requête. `UploadInvoiceFileRequest` autorise les administrateurs de cabinet et gestionnaires affectés, et valide le format PDF/JPG/JPEG/PNG et la limite de 20 Mo.
- La page découpe un lot de 300 fichiers maximum en requêtes séquentielles. Les erreurs sont attribuées au fichier concerné ; les imports déjà réussis sont conservés et remontés par toast.
- `StoreInvoiceUpload` écrit sur le disque `local` (`storage/app/private` par défaut), sous `companies/{id}/invoices`, puis persiste les métadonnées. En cas d’échec pendant la persistance, le fichier est supprimé.
- Le contrôle des doublons SHA-256 est limité à la société et réalisé sous verrou de la ligne société. Sans confirmation explicite, le serveur répond `409` et ne stocke pas le nouveau fichier.
- `GET /companies/{company}/invoices/{invoice}/file` (`companies.invoices.download`) exige une session et un accès à la société, vérifie le rattachement de la facture, puis envoie le fichier en pièce jointe depuis le disque privé.

## Interface

La page `Invoices/Index` fournit une sélection multiple, une progression séquentielle, une confirmation d’import de doublon, les retours de succès/erreur et une liste paginée. Les liens sont accessibles depuis les cartes des sociétés, le tableau de bord et la navigation contextuelle des pages d’une société. Les valeurs extraites sont indiquées comme « à identifier » et l’état rappelle que l’OCR est à venir.

## Tests et contrôles

`tests/Feature/InvoiceIntakeTest.php` couvre l’upload privé, la persistance du hash, l’affichage sans chemin de fichier, le téléchargement, le doublon confirmé/refusé, le périmètre inter-sociétés, les rôles et le rejet d’un format non admis. Exécuter avec `composer test` dans un environnement PHP/Composer installé. `npm run build` valide l’interface TypeScript/Inertia.

Aucun OCR, service Mistral, job, traitement `.mae` ou écriture comptable définitive n’est implémenté dans cette phase.
