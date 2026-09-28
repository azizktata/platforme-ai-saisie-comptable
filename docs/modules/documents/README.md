# Module Documents & écritures

## Rôle
Gérer les fichiers source, leurs métadonnées comptables et le passage atomique à une écriture synthétique.

## Structure

- `app/Models/Document.php` : source, valeurs de revue, statuts et relation `accountingEntry()`.
- `app/Models/AccountingEntry.php` : écriture synthétique créée à la validation.
- `app/Http/Controllers/DocumentController.php` : liste filtrée/paginée, import, détail, sauvegarde, comptabilisation et diffusion du fichier privé.
- `app/Http/Requests/StoreDocumentRequest.php`, `UpdateDocumentRequest.php` et `PostDocumentRequest.php` : validation serveur et autorisation. La mise à jour peut rester partielle ; le posting exige les champs complets.
- `app/Policies/DocumentPolicy.php` : séparation des données entre utilisateurs.
- `app/Services/PostDocumentToLedger.php` : validation finale, verrouillage, transaction et création unique de l’écriture.
- `database/migrations/*_create_documents_table.php` et `*_create_accounting_entries_table.php` : schéma et contrainte d’unicité.
- `resources/js/Pages/Documents/*` : adaptateurs Inertia.
- `resources/js/Components/DocumentsView.tsx`, `DocumentReviewView.tsx` et `UploadDocumentDialog.tsx` : vues d’import, de liste et de revue partagées par l’aperçu local.

## Routes protégées

| Méthode | Route | Fonction |
| --- | --- | --- |
| GET | `/documents` | Liste, recherche, statut et pagination |
| POST | `/documents` | Import multipart |
| GET | `/documents/{document}` | Formulaire de revue |
| PUT | `/documents/{document}` | Enregistrement des corrections |
| POST | `/documents/{document}/post` | Comptabilisation |
| GET | `/documents/{document}/file` | Lecture inline du fichier privé |

## Stockage et données

Les fichiers sont conservés sous `storage/app/private/documents`; aucun lien public n’est créé. Le détail expose une URL protégée par authentification et policy. Les documents du seeder n’ont pas de fichier associé : leur aperçu visuel est un placeholder, alors qu’un import réel affiche le PDF ou l’image source.

`AccountingEntry.document_id` est unique et cascade lors de la suppression du document. Les montants utilisent `DECIMAL(12,2)`. Le service vérifie `HT + TVA = TTC` à 0,01 près et écrit la ligne avec la date de facture.

## Limite fonctionnelle explicite

Le nom du produit évoque l’IA, mais les fichiers de spécification n’ont pas défini de fournisseur, de modèle ou de schéma d’extraction. Aucun service d’IA ou résultat simulé n’est inclus. L’import mène à une revue manuelle jusqu’à clarification de cette exigence.

## Tests

`tests/Feature/DocumentWorkflowTest.php` couvre l’import, l’écriture unique, le contrôle des totaux et l’isolement par propriétaire. Exécuter avec `composer test` dans un environnement PHP configuré.
