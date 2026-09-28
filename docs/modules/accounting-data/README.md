# Référentiels comptables — Phase 2

À implémenter : modèles et migrations `ChartAccount`, `AnalyticalAccount`, `ThirdParty`, `Journal`, `JournalEntry`, `JournalEntryLine`, scopes société, factories/seeders de données Sage fictives. Préserver les codes en chaînes et ajouter les index utiles au rapprochement.

Une source externe de données doit passer par un service d’import isolé ; aucun contrôleur ne doit lire directement un `.mae` ou une base Sage.
