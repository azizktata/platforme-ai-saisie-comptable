# PRD — Analyse et propositions comptables (Phase 5)

Mistral Small reçoit les données OCR et le contexte Sage strictement limité à la société. Il retourne une proposition structurée (journal, tiers, comptes, lignes débit/crédit, TVA, analytique, libellés, explication et niveaux de confiance). Les comptes ne peuvent pas être inventés.

La réponse est validée contre un DTO, contrôlée par des règles déterministes et persistée sans modifier le journal définitif. L’utilisateur compare la facture et la proposition côte à côte, puis accepte, modifie ou rejette. Seule une validation humaine peut finaliser l’écriture. Les corrections confirmées peuvent alimenter une mémoire comptable configurable par société.
