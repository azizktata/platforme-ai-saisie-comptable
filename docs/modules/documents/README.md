# Factures — documentation technique (Phase 3)

À construire après la Phase 2. Modèles ciblés : `Invoice`, `InvoiceLine`, Form Requests, Policies, service de stockage privé et Jobs de traitement.

- Les requêtes doivent être scoped par société autorisée et toutes les pièces restent privées.
- Le stockage OCR ne doit commencer qu’après validation du contrat JSON demandé à Mistral OCR.
- Un job par document assure l’isolation des erreurs ; la persistance facture précède le job d’analyse comptable.
- La page Inertia sera une vue côte à côte du document et des données/proposition.
- Les contrôles et tests sont listés dans [`../../requirements.md`](../../requirements.md) et dans le PRD du module.
