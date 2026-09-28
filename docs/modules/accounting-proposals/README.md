# Propositions comptables — Phase 5

À implémenter : `AccountingProposal`, `AccountingProposalLine`, DTO de sortie, Job d’analyse, service Mistral Small et validateur métier. Charger les comptes/journaux/tiers/sections existants dans le scope société, puis vérifier l’équilibre débit/crédit, cohérence taxes/totaux et références avant persistance.

L’intégration du modèle est isolée du contrôleur. Pas de SQL généré par le LLM, pas de finalisation automatique, et pas de réutilisation de données d’une autre société. Les interactions IA journalisent type/provider/modèle/statut/durée/tokens et des données structurées minimales utiles à l’audit.
