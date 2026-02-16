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
            log_message('info', 'OpenAI valuation skipped: service disabled by OPENAI_VALUATION_ENABLED.');

            return null;
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            $this->lastAttempt['status'] = 'missing_api_key';
            log_message('warning', 'OpenAI valuation skipped: API key is missing.');

            return null;
        }

        $this->lastAttempt['attempted'] = true;
        $this->lastAttempt['status'] = 'request_started';

        $model = (string) env('OPENAI_VALUATION_MODEL', 'gpt-4o-mini');
        $temperature = (float) env('OPENAI_VALUATION_TEMPERATURE', 0.2);
        $maxTokens = (int) env('OPENAI_VALUATION_MAX_TOKENS', 900);
        $timeoutSeconds = (int) env('OPENAI_VALUATION_TIMEOUT', 15);

        $systemPrompt = $this->buildSystemPrompt();

        log_message('info', 'OpenAI valuation request started. model={model} scope={scope} found={found} used={used}', [
            'model' => $model,
            'scope' => $locationScope,
            'found' => $rawCount,
            'used' => $usefulCount,
        ]);

        $userPayload = $this->buildUserPayload(
            subject: $subject,
            locationScope: $locationScope,
            rawCount: $rawCount,
            usefulCount: $usefulCount,
        );

        log_message('info', 'OpenAI valuation prompt payload. model={model} system={systemPrompt} user={userPayload}', [
            'model' => $model,
            'systemPrompt' => $systemPrompt,
            'userPayload' => json_encode($userPayload, JSON_UNESCAPED_UNICODE),
        ]);

        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ];

        $projectId = $this->normalizeEnvValue((string) env('OPENAI_PROJECT_ID', (string) env('OPENAI_PROJECT', '')));
        if ($projectId !== '') {
            $headers['OpenAI-Project'] = $projectId;
        }

        $organizationId = $this->normalizeEnvValue((string) env('OPENAI_ORGANIZATION', (string) env('OPENAI_ORG_ID', '')));
        if ($organizationId !== '') {
            $headers['OpenAI-Organization'] = $organizationId;
        }

        try {
            $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', [
                'headers' => $headers,
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
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            $this->lastAttempt['status'] = 'request_exception';
            $this->lastAttempt['detail'] = $exception->getMessage();

            log_message('error', 'OpenAI valuation request failed: {message}', ['message' => $exception->getMessage()]);
            log_message('info', 'OpenAI valuation request completed with status={status} detail={detail}', [
                'status' => $this->lastAttempt['status'],
                'detail' => (string) $this->lastAttempt['detail'],
            ]);

            return null;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            $errorDetail = $this->extractApiErrorDetail($response->getBody());

            $this->lastAttempt['status'] = 'non_2xx_status';
            $this->lastAttempt['detail'] = trim('HTTP ' . $statusCode . ' ' . $errorDetail);

            log_message('error', 'OpenAI valuation response status {status}: {detail}', [
                'status' => $statusCode,
                'detail' => $errorDetail !== '' ? $errorDetail : 'no-error-detail',
            ]);
            log_message('info', 'OpenAI valuation request completed with status={status} detail={detail}', [
                'status' => $this->lastAttempt['status'],
                'detail' => (string) $this->lastAttempt['detail'],
            ]);

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
        $rawEstimatedPpu = $parsed['estimated_value_per_m2'] ?? null;
        $estimatedPpu = $this->normalizePpu($rawEstimatedPpu);

        if ($estimatedPpu === null && isset($subject['area_construction_m2']) && (float) $subject['area_construction_m2'] > 0) {
            $estimatedPpu = $this->normalizePpu($estimatedValue / (float) $subject['area_construction_m2']);
        }
        $methodologySummary = trim((string) ($parsed['methodology_summary'] ?? ''));
        $appliedAdjustments = $this->normalizeAdjustments($parsed['applied_adjustments'] ?? []);

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

        log_message('info', 'OpenAI valuation request completed with status={status} request_id={requestId} estimated_value={estimatedValue} confidence={confidence} ppu={ppu} raw_ppu={rawPpu} adjustments={adjustments}', [
            'status' => $this->lastAttempt['status'],
            'requestId' => $requestId ?? 'n/a',
            'estimatedValue' => (string) $estimatedValue,
            'confidence' => (string) $confidenceScore,
            'ppu' => $estimatedPpu !== null ? (string) $estimatedPpu : 'n/a',
            'rawPpu' => $rawEstimatedPpu !== null ? (string) $rawEstimatedPpu : 'n/a',
            'adjustments' => (string) count($appliedAdjustments),
        ]);

        return [
            'ok' => true,
            'message' => 'No se encontraron comparables locales útiles; se generó una estimación de apoyo con IA usando las características del inmueble objetivo (confianza baja).',
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
                    'ppu_promedio' => $estimatedPpu,
                    'ppu_aplicado' => $estimatedPpu,
                ],
                'valuation_factors' => [
                    'ai_disclaimer' => $aiDisclaimer,
                    'methodology_summary' => $methodologySummary,
                ],
                'human_steps' => $humanSteps,
                'advisor_detail_steps' => $advisorSteps,
                'ai_metadata' => [
                    'provider' => 'openai',
                    'model' => $model,
                    'request_id' => $requestId,
                    'attempted' => true,
                    'status' => $this->lastAttempt['status'],
                    'input_summary' => sprintf(
                        'IA usó: tipo=%s, municipio=%s, colonia=%s, construcción=%s m², edad=%d años, conservación=%d/10, y resultado de búsqueda local (%d encontrados, %d útiles).',
                        (string) ($subject['property_type'] ?? 'N/D'),
                        (string) ($subject['municipality'] ?? 'N/D'),
                        (string) ($subject['colony'] ?? 'N/D'),
                        number_format((float) ($subject['area_construction_m2'] ?? 0), 2),
                        (int) ($subject['age_years'] ?? 0),
                        (int) ($subject['conservation_level'] ?? 0),
                        $rawCount,
                        $usefulCount,
                    ),
                    'applied_adjustments' => $appliedAdjustments,
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

    private function resolveApiKey(): string
    {
        $directKey = $this->normalizeApiKey((string) env('OPENAI_API_KEY', ''));
        if ($directKey !== '') {
            return $directKey;
        }

        return $this->normalizeApiKey((string) env('OPENAI_KEY', ''));
    }

    private function normalizeApiKey(string $value): string
    {
        $normalized = $this->normalizeEnvValue($value);
        if ($normalized === '') {
            return '';
        }

        // Common copy/paste typo in .env values: duplicated `sk-` prefix.
        if (str_starts_with($normalized, 'sk-sk-')) {
            $normalized = substr($normalized, 3);
        }

        return $normalized;
    }

    private function normalizeEnvValue(string $value): string
    {
        $normalized = trim($value);
        $normalized = trim($normalized, "\"'");

        return trim($normalized);
    }

    private function extractApiErrorDetail(string $responseBody): string
    {
        $decoded = json_decode($responseBody, true);
        if (! is_array($decoded)) {
            return '';
        }

        $error = $decoded['error'] ?? null;
        if (! is_array($error)) {
            return '';
        }

        $parts = [];

        $message = trim((string) ($error['message'] ?? ''));
        if ($message !== '') {
            $parts[] = $message;
        }

        $code = trim((string) ($error['code'] ?? ''));
        if ($code !== '') {
            $parts[] = 'code=' . $code;
        }

        $type = trim((string) ($error['type'] ?? ''));
        if ($type !== '') {
            $parts[] = 'type=' . $type;
        }

        return implode(' | ', $parts);
    }

    private function buildSystemPrompt(): string
    {
        return 'Actúa como valuador inmobiliario certificado en México, especialista en mercado residencial de Nuevo León. Devuelve exclusivamente JSON válido, sin markdown ni texto adicional.';
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    private function buildUserPayload(array $subject, string $locationScope, int $rawCount, int $usefulCount): array
    {
        return [
            'task' => 'Calcular una estimación de valor de mercado para una vivienda con base técnica, usando enfoque comparativo y ajustes explicados.',
            'context' => [
                'location_scope_checked' => $locationScope,
                'records_found' => $rawCount,
                'records_used' => $usefulCount,
                'note' => 'No hay comparables locales útiles de colonia/municipio; debes emitir una estimación de apoyo de baja confianza sin inventar comparables.',
            ],
            'subject' => $subject,
            'calculation_instructions' => [
                'Estima un valor por m² de construcción para el contexto local.',
                'Multiplica por el área de construcción del sujeto para un valor base.',
                'Aplica ajustes porcentuales por atributos relevantes (estado, equipamiento, ubicación) y explica su impacto monetario.',
                'Entrega rango conservador, rango competitivo y valor recomendado.',
            ],
            'constraints' => [
                'Devuelve montos en MXN.',
                'No inventes comparables puntuales ni direcciones.',
                'Mantén baja confianza por insuficiencia de muestra local.',
            ],
            'output_schema' => [
                'estimated_value' => 'number',
                'estimated_low' => 'number',
                'estimated_high' => 'number',
                'estimated_value_per_m2' => 'number',
                'applied_adjustments' => [[
                    'factor' => 'string',
                    'percentage' => 'number',
                    'monetary_impact' => 'number',
                    'rationale' => 'string',
                ]],
                'methodology_summary' => 'string',
                'confidence_score' => 'integer_0_100',
                'confidence_reasons' => ['string'],
                'human_steps' => ['string'],
                'advisor_detail_steps' => ['string'],
                'ai_disclaimer' => 'string',
            ],
        ];
    }

    /**
     * @param mixed $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeAdjustments(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $result = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $factor = trim((string) ($item['factor'] ?? ''));
            if ($factor === '') {
                continue;
            }

            $result[] = [
                'factor' => $factor,
                'percentage' => (float) ($item['percentage'] ?? 0),
                'monetary_impact' => (float) ($item['monetary_impact'] ?? 0),
                'rationale' => trim((string) ($item['rationale'] ?? '')),
            ];
        }

        return $result;
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

    private function normalizePpu(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $number = (float) $value;
        if ($number <= 0) {
            return null;
        }

        // Keep PPU precision by tens (not thousands) and clamp to plausible NL range.
        $number = round($number / 10) * 10;
        if ($number < 5000 || $number > 80000) {
            return null;
        }

        return $number;
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
