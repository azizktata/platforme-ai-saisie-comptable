<?php

namespace App\Services\Ocr;

use App\Contracts\OcrProvider;
use App\Data\InvoiceOcrResult;
use App\Exceptions\OcrProviderException;
use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OcrSpaceProvider implements OcrProvider
{
    public function __construct(private readonly InvoiceOcrSchema $schema) {}

    public function extract(Invoice $invoice): InvoiceOcrResult
    {
        $apiKey = config('services.ocr_space.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new OcrProviderException(
                'configuration_missing',
                false,
                'La clé API OCR.space n’est pas configurée sur le serveur.',
            );
        }

        $maxFileSize = max(1, (int) config('services.ocr_space.max_file_size_bytes', 1024 * 1024));

        if ((int) $invoice->size_bytes > $maxFileSize) {
            throw new OcrProviderException(
                'provider_file_too_large',
                false,
                'Le fichier dépasse la limite de taille du forfait OCR.space Free.',
                diagnostic: 'OCR.space free file-size limit exceeded.',
            );
        }

        if ($invoice->storage_disk !== 'local') {
            throw new OcrProviderException(
                'unsupported_storage_disk',
                false,
                'Le document source n’est pas disponible sur le stockage privé attendu.',
            );
        }

        $expectedPrefix = "companies/{$invoice->company_id}/invoices/";

        if (! str_starts_with($invoice->file_path, $expectedPrefix)) {
            throw new OcrProviderException(
                'source_file_invalid_path',
                false,
                'Le chemin du document source ne correspond pas à sa société.',
            );
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($invoice->file_path)) {
            throw new OcrProviderException(
                'source_file_missing',
                false,
                'Le document source privé est introuvable.',
            );
        }

        try {
            $contents = $disk->get($invoice->file_path);
        } catch (Throwable) {
            throw new OcrProviderException(
                'source_file_unreadable',
                false,
                'Le document source privé n’a pas pu être lu.',
            );
        }

        if (! is_string($contents) || $contents === '') {
            throw new OcrProviderException(
                'source_file_unreadable',
                false,
                'Le document source privé n’a pas pu être lu.',
            );
        }

        if (strlen($contents) > $maxFileSize) {
            throw new OcrProviderException(
                'provider_file_too_large',
                false,
                'Le fichier dépasse la limite de taille du forfait OCR.space Free.',
                diagnostic: 'OCR.space free file-size limit exceeded.',
            );
        }

        $endpoint = (string) config('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            throw new OcrProviderException(
                'configuration_invalid',
                false,
                'Le endpoint OCR.space doit utiliser une URL HTTPS valide.',
            );
        }

        $timeout = max(1, (int) config('services.ocr_space.timeout', 180));
        $engine = (int) config('services.ocr_space.ocr_engine', 3);

        if (! in_array($engine, [1, 2, 3], true)) {
            throw new OcrProviderException(
                'configuration_invalid',
                false,
                'Le moteur OCR.space configuré n’est pas valide.',
            );
        }

        $verify = true;
        $caBundle = config('services.ocr_space.ca_bundle');

        if (is_string($caBundle) && trim($caBundle) !== '') {
            $caBundle = trim($caBundle);

            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new OcrProviderException(
                    'tls_ca_bundle_invalid',
                    false,
                    'Le fichier de certificats TLS configuré pour OCR.space est absent ou illisible.',
                );
            }

            $verify = $caBundle;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => trim($apiKey)])
                ->connectTimeout(min(30, $timeout))
                ->timeout($timeout)
                ->withoutRedirecting()
                ->withOptions(['verify' => $verify])
                ->attach(
                    'file',
                    $contents,
                    basename($invoice->original_filename),
                    ['Content-Type' => $invoice->mime_type],
                )
                ->post($endpoint, [
                    'language' => 'fre',
                    'isOverlayRequired' => 'false',
                    'isTable' => 'true',
                    'OCREngine' => (string) $engine,
                ]);
        } catch (ConnectionException $exception) {
            if ($this->isCertificateVerificationFailure($exception->getMessage())) {
                throw new OcrProviderException(
                    'tls_certificate_verification_failed',
                    false,
                    'Échec de vérification TLS. Vérifiez le bundle CA de PHP ou OCR_SPACE_CA_BUNDLE.',
                    diagnostic: 'TLS certificate verification failed; check PHP CA settings or OCR_SPACE_CA_BUNDLE.',
                );
            }

            throw new OcrProviderException(
                'provider_unreachable',
                true,
                'Le service OCR.space est temporairement injoignable.',
                diagnostic: $this->safeTransportDiagnostic($exception->getMessage()),
            );
        }

        if (! $response->successful()) {
            $status = $response->status();
            $retryable = $status === 429 || $status >= 500;
            $errorCode = match (true) {
                $status === 401 || $status === 403 => 'provider_authentication_failed',
                $status === 429 => 'provider_rate_limited',
                $status >= 500 => 'provider_unavailable',
                default => 'provider_rejected_request',
            };

            throw new OcrProviderException(
                $errorCode,
                $retryable,
                $retryable
                    ? 'Le service OCR.space a temporairement refusé la demande.'
                    : 'Le service OCR.space n’a pas accepté le document ou la requête.',
                diagnostic: 'OCR.space returned HTTP '.$status.'.',
            );
        }

        $providerResponse = $response->json();

        if (! is_array($providerResponse) || ! is_array($providerResponse['ParsedResults'] ?? null)) {
            throw new OcrProviderException(
                'invalid_provider_response',
                false,
                'La réponse OCR.space ne contient pas de résultats exploitables.',
                is_array($providerResponse) ? $providerResponse : null,
                'OCR.space response is missing ParsedResults.',
            );
        }

        if ((int) ($providerResponse['OCRExitCode'] ?? 0) === 2) {
            throw new OcrProviderException(
                'provider_partial_result',
                false,
                'OCR.space n’a pas traité toutes les pages. Le forfait gratuit limite les PDF à trois pages.',
                $providerResponse,
                'OCR.space returned a partial result.',
            );
        }

        $parsedPages = [];

        foreach ($providerResponse['ParsedResults'] as $page) {
            if (! is_array($page)
                || (int) ($page['FileParseExitCode'] ?? 0) !== 1
                || ! is_string($page['ParsedText'] ?? null)
                || trim($page['ParsedText']) === '') {
                continue;
            }

            $parsedPages[] = trim($page['ParsedText']);
        }

        $extractedText = trim(implode("\n\n", $parsedPages));

        if ($extractedText === '') {
            throw new OcrProviderException(
                'ocr_text_unavailable',
                false,
                'OCR.space n’a extrait aucun texte lisible. Vérifiez la qualité ou le format du document.',
                $providerResponse,
                'OCR.space returned no successfully parsed text pages.',
            );
        }

        // OCR.space returns text, not invoice JSON; do not guess structured fields from it.
        $invoiceData = $this->schema->emptyAnnotation();

        return new InvoiceOcrResult(
            response: $providerResponse,
            invoiceData: $this->schema->validate($invoiceData),
            model: 'ocr.space-engine-'.$engine,
            usage: [
                'pages_processed' => count($parsedPages),
                'processing_time_ms' => is_numeric($providerResponse['ProcessingTimeInMilliseconds'] ?? null)
                    ? (int) $providerResponse['ProcessingTimeInMilliseconds']
                    : null,
            ],
            text: $extractedText,
            hasStructuredData: false,
        );
    }

    private function isCertificateVerificationFailure(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'curl error 60:')
            || str_contains($message, 'curl error 77:')
            || str_contains($message, 'ssl certificate problem')
            || str_contains($message, 'unable to get local issuer certificate')
            || str_contains($message, 'certificate verify failed')
            || str_contains($message, 'error setting certificate file');
    }

    private function safeTransportDiagnostic(string $message): string
    {
        $message = preg_replace('/apikey\s*[:=]\s*\S+/i', 'apikey: [redacted]', $message) ?? $message;
        $message = preg_replace('/https?:\/\/[^\s]+/i', '[provider URL]', $message) ?? $message;

        return mb_substr(trim(strip_tags($message)), 0, 300);
    }
}
