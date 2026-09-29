<?php

namespace App\Services\OpenRouter;

use App\Data\StructuredAiResult;
use App\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

class OpenRouterClient
{
    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed> $responseFormat
     */
    public function completeJson(
        array $messages,
        array $responseFormat,
        int $maxTokens = 5000,
        ?string $model = null,
    ): StructuredAiResult
    {
        $apiKey = config('services.openrouter.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new AiProviderException(
                'openrouter_configuration_missing',
                false,
                'La clé API OpenRouter n’est pas configurée sur le serveur.',
            );
        }

        $endpoint = (string) config('services.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions');

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            throw new AiProviderException(
                'openrouter_configuration_invalid',
                false,
                'Le point de terminaison OpenRouter doit utiliser une URL HTTPS valide.',
            );
        }

        $timeout = max(1, (int) config('services.openrouter.timeout', 120));
        $verify = true;
        $caBundle = config('services.openrouter.ca_bundle');

        if (is_string($caBundle) && trim($caBundle) !== '') {
            $caBundle = trim($caBundle);

            if (! is_file($caBundle) || ! is_readable($caBundle)) {
                throw new AiProviderException(
                    'openrouter_tls_ca_bundle_invalid',
                    false,
                    'Le bundle de certificats TLS configuré pour OpenRouter est absent ou illisible.',
                );
            }

            $verify = $caBundle;
        }

        $siteUrl = config('services.openrouter.site_url');
        $headers = [];

        if (is_string($siteUrl) && trim($siteUrl) !== '') {
            $headers['HTTP-Referer'] = trim($siteUrl);
        }

        $headers['X-OpenRouter-Title'] = (string) config('services.openrouter.app_name', config('app.name', 'ComptaFlow'));

        try {
            $payload = [
                'model' => $model ?? (string) config('services.openrouter.model', 'openrouter/free'),
                'messages' => $messages,
                'response_format' => $responseFormat,
                'temperature' => 0,
                'max_tokens' => min(max(256, $maxTokens), 12000),
            ];

            $response = Http::acceptJson()
                ->withHeaders($headers)
                ->withToken($apiKey)
                ->connectTimeout(min(30, $timeout))
                ->timeout($timeout)
                ->withOptions(['verify' => $verify])
                ->post($endpoint, $payload);
        } catch (ConnectionException $exception) {
            if ($this->isCertificateVerificationFailure($exception->getMessage())) {
                throw new AiProviderException(
                    'openrouter_tls_certificate_verification_failed',
                    false,
                    'Échec de vérification TLS avec OpenRouter. Vérifiez les certificats CA de PHP ou configurez OPENROUTER_CA_BUNDLE.',
                    diagnostic: 'TLS certificate verification failed; check PHP CA settings or OPENROUTER_CA_BUNDLE.',
                );
            }

            throw new AiProviderException(
                'openrouter_unreachable',
                true,
                'Le service OpenRouter est temporairement injoignable.',
                diagnostic: $this->safeTransportDiagnostic($exception->getMessage()),
            );
        }

        $rawResponse = $response->json();
        $rawResponse = is_array($rawResponse) ? $rawResponse : [];

        if (! $response->successful()) {
            $status = $response->status();
            $errorCode = match (true) {
                $status === 401 || $status === 403 => 'openrouter_authentication_failed',
                $status === 429 => 'openrouter_rate_limited',
                $status >= 500 => 'openrouter_unavailable',
                default => 'openrouter_rejected_request',
            };
            $retryable = $status === 429 || $status >= 500;

            throw new AiProviderException(
                $errorCode,
                $retryable,
                $retryable
                    ? 'OpenRouter a temporairement refusé la demande.'
                    : 'OpenRouter n’a pas accepté la demande. Vérifiez la configuration du serveur.',
                $rawResponse,
                'OpenRouter returned HTTP '.$status.'.',
            );
        }

        $choice = $rawResponse['choices'][0] ?? null;
        $message = is_array($choice) && is_array($choice['message'] ?? null) ? $choice['message'] : [];
        $content = $message['content'] ?? null;
        $finishReason = is_array($choice) ? ($choice['finish_reason'] ?? null) : null;
        $model = $rawResponse['model'] ?? null;
        $refusal = $message['refusal'] ?? null;

        if ($finishReason === 'length') {
            throw new AiProviderException(
                'openrouter_response_truncated',
                false,
                'La réponse OpenRouter est incomplète. Réduisez la taille du document ou relancez le traitement.',
                $rawResponse,
            );
        }

        if (in_array($finishReason, ['content_filter', 'safety', 'refusal'], true)
            || (is_string($refusal) && trim($refusal) !== '')
            || (is_string($content) && preg_match('/^\\s*(?:user\\s+safety\\s*:|safety(?:\\s+classifier)?\\s*:|refusal\\s*:)/i', $content) === 1)) {
            throw new AiProviderException(
                'openrouter_safety_response',
                false,
                'Le modèle n’a pas produit de données facture : sa réponse de sécurité/refus a été rejetée.',
                $rawResponse,
            );
        }

        if (! in_array($finishReason, ['stop', 'eos'], true)) {
            throw new AiProviderException(
                'openrouter_unsupported_finish_reason',
                false,
                'OpenRouter a terminé la réponse dans un format non pris en charge.',
                $rawResponse,
            );
        }

        if (! is_string($content) || trim($content) === '' || ! is_string($model) || trim($model) === '') {
            throw new AiProviderException(
                'openrouter_invalid_response',
                false,
                'OpenRouter n’a pas renvoyé une réponse structurée exploitable.',
                $rawResponse,
            );
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AiProviderException(
                'openrouter_invalid_json_response',
                false,
                'La réponse OpenRouter ne contient pas de JSON valide.',
                $rawResponse,
            );
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new AiProviderException(
                'openrouter_invalid_json_response',
                false,
                'La réponse OpenRouter n’est pas un objet JSON valide.',
                $rawResponse,
            );
        }

        return new StructuredAiResult(
            data: $data,
            response: $rawResponse,
            model: mb_substr($model, 0, 100),
            usage: is_array($rawResponse['usage'] ?? null) ? $rawResponse['usage'] : [],
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
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/https?:\/\/[^\s]+/i', '[provider URL]', $message) ?? $message;

        return mb_substr(trim(strip_tags($message)), 0, 300);
    }
}
