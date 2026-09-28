# Traitement OCR — Phase 4

À implémenter derrière un contrat de service OCR remplaçable, une implémentation Mistral OCR, un DTO de données extraites et un Job par facture. Le job vérifie le schéma et rattache toutes les données par `company_id`; l’analyse comptable est dispatchée après sauvegarde de la facture OCR.

Configuration attendue via secrets d’environnement (`MISTRAL_API_KEY`, endpoint/timeout si nécessaire). Tests : `Http::fake()` pour succès, erreur HTTP, timeout simulé et JSON invalide ; aucune clé réelle en CI.
