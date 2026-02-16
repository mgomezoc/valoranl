<?php

namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;

class OpenAiValuationService
{
    private CURLRequest $httpClient;

    /** @var array<string, mixed> */
    private array $lastAttempt = [
        'attempted' => false,
        'status' => 'not_called',
        'detail' => null,
    ];

    public function __construct(?CURLRequest $httpClient = null)
    {
        $this->httpClient = $httpClient ?? service('curlrequest');
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>|null
     */
    public function estimateWithoutComparables(array $subject, string $locationScope, int $rawCount, int $usefulCount): ?array
    {
        $this->lastAttempt = [
            'attempted' => false,
            'status' => 'not_called',
            'detail' => null,
        ];

        if (! $this->isEnabled()) {
            $this->lastAttempt['status'] = 'disabled';

            return null;
        }

        $apiKey = (string) env('OPENAI_API_KEY', '');
        if ($apiKey === '') {
            $this->lastAttempt['status'] = 'missing_api_key';

            return null;
        }

        $this->lastAttempt['attempted'] = true;
        $this->lastAttempt['status'] = 'request_started';

        $model = (string) env('OPENAI_VALUATION_MODEL', 'gpt-4o-mini');
        $temperature = (float) env('OPENAI_VALUATION_TEMPERATURE', 0.2);
        $maxTokens = (int) env('OPENAI_VALUATION_MAX_TOKENS', 900);
        $timeoutSeconds = (int) env('OPENAI_VALUATION_TIMEOUT', 15);

        $systemPrompt = 'Eres un analista inmobiliario experto en Nuevo León. Devuelve únicamente JSON válido sin markdown.';

        $userPayload = [
            'task' => 'Generar estimación de valuación cuando no hay comparables locales.',
            'constraints' => [
                'No hay comparables útiles de colonia/municipio.',
                'La salida debe incluir advertencia de baja confianza.',
                'Devuelve montos en MXN.',
                'No inventes comparables específicos.',
            ],
            'subject' => $subject,
            'location_scope_checked' => $locationScope,
            'records_found' => $rawCount,
            'records_used' => $usefulCount,
            'output_schema' => [
                'estimated_value' => 'number',
                'estimated_low' => 'number',
                'estimated_high' => 'number',
                'confidence_score' => 'integer_0_100',
                'confidence_reasons' => ['string'],
                'human_steps' => ['string'],
                'advisor_detail_steps' => ['string'],
                'ai_disclaimer' => 'string',
            ],
        ];

        try {
            $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE)],
                    ],
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => $timeoutSeconds,
            ]);
        } catch (\Throwable $exception) {
            $this->lastAttempt['status'] = 'request_exception';
            $this->lastAttempt['detail'] = $exception->getMessage();

            log_message('error', 'OpenAI valuation request failed: {message}', ['message' => $exception->getMessage()]);

            return null;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $this->lastAttempt['status'] = 'non_2xx_status';
            $this->lastAttempt['detail'] = 'HTTP ' . $statusCode;

            log_message('error', 'OpenAI valuation response status {status}', ['status' => $statusCode]);

            return null;
        }

        $body = json_decode($response->getBody(), true);
        if (! is_array($body)) {
            $this->lastAttempt['status'] = 'invalid_json_response';

            return null;
        }

        $content = $body['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || trim($content) === '') {
            $this->lastAttempt['status'] = 'empty_message_content';

            return null;
        }

        $parsed = $this->decodeModelJson($content);
        if ($parsed === null) {
            $this->lastAttempt['status'] = 'invalid_model_payload';

            return null;
        }

        $estimatedValue = $this->normalizeMoney($parsed['estimated_value'] ?? null);
        $estimatedLow = $this->normalizeMoney($parsed['estimated_low'] ?? null);
        $estimatedHigh = $this->normalizeMoney($parsed['estimated_high'] ?? null);

        if ($estimatedValue === null || $estimatedLow === null || $estimatedHigh === null) {
            $this->lastAttempt['status'] = 'invalid_amounts';

            return null;
        }

        $confidenceScore = (int) ($parsed['confidence_score'] ?? 18);
        $confidenceScore = max(5, min(45, $confidenceScore));

        $confidenceReasons = $this->normalizeStringArray($parsed['confidence_reasons'] ?? []);
        $humanSteps = $this->normalizeStringArray($parsed['human_steps'] ?? []);
        $advisorSteps = $this->normalizeStringArray($parsed['advisor_detail_steps'] ?? []);
        $aiDisclaimer = trim((string) ($parsed['ai_disclaimer'] ?? 'Estimación orientativa generada con IA por falta de comparables locales.'));

        if ($confidenceReasons === []) {
            $confidenceReasons = [
                'No se encontraron comparables útiles dentro de la misma colonia o municipio.',
                'Se usó estimación de apoyo potenciada por IA.',
                'Este resultado es orientativo y de baja confiabilidad.',
            ];
        }

        if ($humanSteps === []) {
            $humanSteps = [
                sprintf('No hubo comparables locales útiles (%d encontrados / %d usados).', $rawCount, $usefulCount),
                'Se usó un modelo de IA para estimar un valor orientativo.',
                'Este resultado debe tomarse como referencia inicial y validarse con avalúo profesional.',
            ];
        }

        if ($advisorSteps === []) {
            $advisorSteps = [
                sprintf('1) Muestra local insuficiente (%d/%d).', $rawCount, $usefulCount),
                '2) Se consultó motor de IA con datos del sujeto y restricción de baja confianza.',
                sprintf('3) Valor estimado IA: $%s MXN.', number_format($estimatedValue, 0)),
            ];
        }

        $requestId = isset($body['id']) ? (string) $body['id'] : null;

        $this->lastAttempt['status'] = 'success';
        $this->lastAttempt['detail'] = $requestId;

        return [
            'ok' => true,
            'message' => 'No hubo comparables útiles en colonia/municipio; se muestra una estimación potenciada por IA con baja confianza.',
            'subject' => $subject,
            'estimated_value' => $estimatedValue,
            'estimated_low' => $estimatedLow,
            'estimated_high' => $estimatedHigh,
            'ppu_base' => null,
            'comparables_count' => 0,
            'comparables' => [],
            'confidence_score' => $confidenceScore,
            'confidence_reasons' => $confidenceReasons,
            'location_scope' => $locationScope,
            'calc_breakdown' => [
                'method' => 'openai_local_fallback',
                'scope_used' => $locationScope,
                'used_properties_database' => false,
                'ai_powered' => true,
                'data_origin' => [
                    'source' => 'openai_api',
                    'source_label' => 'Estimación generada por IA (OpenAI) por falta de comparables locales útiles.',
                    'used_for_calculation' => true,
                    'records_found' => $rawCount,
                    'records_used' => $usefulCount,
                ],
                'comparables_raw' => $rawCount,
                'comparables_useful' => $usefulCount,
                'ppu_stats' => [
                    'ppu_promedio' => null,
                    'ppu_aplicado' => null,
                ],
                'valuation_factors' => [
                    'ai_disclaimer' => $aiDisclaimer,
                ],
                'human_steps' => $humanSteps,
                'advisor_detail_steps' => $advisorSteps,
                'ai_metadata' => [
                    'provider' => 'openai',
                    'model' => $model,
                    'request_id' => $requestId,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastAttemptMeta(): array
    {
        return $this->lastAttempt;
    }

    private function isEnabled(): bool
    {
        $value = strtolower(trim((string) env('OPENAI_VALUATION_ENABLED', 'false')));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeModelJson(string $content): ?array
    {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches) !== 1) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        return ceil($number / 1000) * 1000;
    }

    /**
     * @param mixed $items
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            $text = trim((string) $item);
            if ($text !== '') {
                $result[] = $text;
            }
        }

        return $result;
    }
}
