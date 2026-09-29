# Documents facture et workflow IA (Phases 3–5)

Le module Documents est intégré au workspace Factures. `InvoiceController` gère intake privé, historique, téléchargement, détail à la demande, correction de l’extraction et relance ciblée. `ProcessInvoiceOcr`, `ExtractInvoiceData` et `AnalyzeAccountingProposal` constituent des jobs indépendants sur la file `ocr`.

OCR.space Engine 3 est le défaut ; OCR brut et transcription restent accessibles dans la boîte de dialogue « Afficher le texte OCR ». OpenRouter `openrouter/free` structure la transcription puis produit la proposition. Mistral est sélectionnable pour produire directement les données structurées, mais les propositions comptables utilisent OpenRouter. Réponses, modèles et usage sont conservés séparément des données corrigées par l’humain.

La proposition est limitée au contexte comptable de la société et ses références sont revalidées côté serveur. L’utilisateur autorisé peut compléter les données facture, corriger les lignes comptables, rejeter la proposition sans créer d’écriture ou la valider explicitement pour créer une écriture liée à la facture. Les utilisateurs société en lecture seule peuvent consulter les détails.

Configuration OpenRouter, modèle libre, statuts, migrations, tests et limites sont documentés dans [`../invoice-processing/README.md`](../invoice-processing/README.md) et [`../accounting-proposals/README.md`](../accounting-proposals/README.md). Les originaux restent privés et aucun appel fournisseur n’est fait depuis le navigateur.
