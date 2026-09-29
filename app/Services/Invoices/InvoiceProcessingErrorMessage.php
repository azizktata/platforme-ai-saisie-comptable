<?php

namespace App\Services\Invoices;

class InvoiceProcessingErrorMessage
{
    public function for(string $errorCode): string
    {
        return match ($errorCode) {
            'configuration_missing' => 'La clé API du fournisseur OCR actif n’est pas configurée sur le serveur.',
            'openrouter_configuration_missing' => 'La clé API OpenRouter n’est pas configurée sur le serveur.',
            'openrouter_tls_ca_bundle_invalid', 'tls_ca_bundle_invalid' => 'Le bundle de certificats TLS configuré est absent ou illisible.',
            'tls_certificate_verification_failed', 'openrouter_tls_certificate_verification_failed' => 'Échec de vérification TLS. Vérifiez les certificats CA de PHP ou le bundle CA du fournisseur.',
            'provider_file_too_large' => 'Le fichier dépasse la limite de taille du fournisseur OCR actif. Réduisez sa taille puis relancez le traitement.',
            'provider_partial_result' => 'OCR.space n’a pas traité toutes les pages. Le forfait gratuit accepte au maximum trois pages PDF.',
            'ocr_text_unavailable' => 'Aucun texte lisible n’a été détecté. Vérifiez la qualité et l’orientation du document.',
            'provider_authentication_failed' => 'Le fournisseur OCR a refusé l’authentification. Vérifiez la configuration du serveur.',
            'openrouter_authentication_failed' => 'OpenRouter a refusé l’authentification. Vérifiez la clé API configurée sur le serveur.',
            'configuration_invalid', 'openrouter_configuration_invalid' => 'La configuration du fournisseur actif est invalide. Vérifiez les paramètres du serveur.',
            'provider_rejected_request', 'invalid_provider_response', 'invalid_structured_annotation' => 'La réponse OCR n’a pas pu être validée. Le document peut être vérifié ou relancé.',
            'openrouter_rejected_request', 'openrouter_invalid_response', 'openrouter_invalid_json_response', 'openrouter_invalid_invoice_data', 'openrouter_invalid_accounting_proposal', 'openrouter_response_truncated' => 'La réponse OpenRouter n’a pas pu être validée. Vérifiez le document puis relancez cette étape.',
            'openrouter_input_too_large' => 'Le texte OCR dépasse la limite configurée pour l’analyse OpenRouter.',
            'accounting_context_incomplete' => 'Ajoutez un journal et un compte comptable actifs à la société avant de relancer l’analyse.',
            'source_file_missing', 'source_file_unreadable', 'source_file_invalid_path' => 'Le document privé n’a pas pu être lu pour le traitement.',
            'unsupported_document_type', 'unsupported_storage_disk' => 'Le format ou le stockage du document ne peut pas être traité.',
            'provider_rate_limited', 'provider_unavailable', 'provider_unreachable' => 'Le service OCR n’a pas abouti après plusieurs tentatives. Vous pouvez relancer cette étape.',
            'openrouter_rate_limited', 'openrouter_unavailable', 'openrouter_unreachable' => 'OpenRouter n’a pas abouti après plusieurs tentatives. Vous pouvez relancer cette étape.',
            'processing_attempts_exhausted' => 'Le traitement n’a pas abouti après plusieurs tentatives. Vous pouvez relancer l’étape concernée.',
            'invoice_data_incomplete' => 'Les données obligatoires de la facture sont incomplètes. Corrigez les champs signalés avant l’analyse comptable.',
            default => 'Le traitement de la facture a échoué. Vous pouvez relancer l’étape concernée.',
        };
    }
}
