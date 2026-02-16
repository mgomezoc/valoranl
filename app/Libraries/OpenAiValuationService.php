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
     * Estimate with full context: priors from DB, local result, and confidence cap.
     *
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $priors  PPU stats from municipality (n, p25, p50, p75, avg)
     * @param array<string, mixed>|null $localResult  Algorithm fallback result if available
     * @return array<string, mixed>|null
     */
    public function estimateWithContext(
        array $subject,
        string $locationScope,
        int $rawCount,
        int $usefulCount,
        array $priors = [],
        ?array $localResult = null,
        int $confidenceCap = 45,
    ): ?array {
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

        // Build constraints from priors
        $constraints = $this->buildConstraints($subject, $priors, $confidenceCap);

        $systemPrompt = $this->buildSystemPrompt();
        $userPayload = $this->buildUserPayload(
            subject: $subject,
            locationScope: $locationScope,
            rawCount: $rawCount,
            usefulCount: $usefulCount,
            priors: $priors,
            localResult: $localResult,
            constraints: $constraints,
        );

        log_message('info', 'OpenAI valuation request started. model={model} scope={scope} found={found} used={used} cap={cap}', [
            'model' => $model,
            'scope' => $locationScope,
            'found' => $rawCount,
            'used' => $usefulCount,
            'cap' => $confidenceCap,
        ]);

        log_message('info', 'OpenAI valuation prompt payload. model={model} user={userPayload}', [
            'model' => $model,
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

        // Extract and clamp values against constraints
        $estimatedValue = $this->normalizeMoney($parsed['estimated_value'] ?? null);
        $estimatedLow = $this->normalizeMoney($parsed['estimated_low'] ?? null);
        $estimatedHigh = $this->normalizeMoney($parsed['estimated_high'] ?? null);

        if ($estimatedValue === null || $estimatedLow === null || $estimatedHigh === null) {
            $this->lastAttempt['status'] = 'invalid_amounts';

            return null;
        }

        // Clamp values to constraints
        $estimatedValue = max($constraints['value_min'], min($constraints['value_max'], $estimatedValue));
        $estimatedLow = max($constraints['value_min'], min($constraints['value_max'], $estimatedLow));
        $estimatedHigh = max($constraints['value_min'], min($constraints['value_max'], $estimatedHigh));

        // Clamp confidence to cap
        $confidenceScore = (int) ($parsed['confidence_score'] ?? 18);
        $confidenceScore = max(5, min($confidenceCap, $confidenceScore));

        // Clamp PPU
        $rawEstimatedPpu = $parsed['estimated_value_per_m2'] ?? null;
        $estimatedPpu = $this->normalizePpu($rawEstimatedPpu);

        if ($estimatedPpu === null && isset($subject['area_construction_m2']) && (float) $subject['area_construction_m2'] > 0) {
            $estimatedPpu = $this->normalizePpu($estimatedValue / (float) $subject['area_construction_m2']);
        }

        // Clamp PPU to constraint range
        if ($estimatedPpu !== null) {
            $estimatedPpu = max($constraints['ppu_min'], min($constraints['ppu_max'], $estimatedPpu));
            $estimatedPpu = round($estimatedPpu / 10) * 10;
        }

        $confidenceReasons = $this->normalizeStringArray($parsed['confidence_reasons'] ?? []);
        $humanSteps = $this->normalizeStringArray($parsed['human_steps'] ?? []);
        $advisorSteps = $this->normalizeStringArray($parsed['advisor_detail_steps'] ?? []);
        $aiDisclaimer = trim((string) ($parsed['ai_disclaimer'] ?? 'Estimación orientativa generada con IA por falta de comparables locales.'));
        $methodologySummary = trim((string) ($parsed['methodology_summary'] ?? ''));
        $appliedAdjustments = $this->normalizeAdjustments($parsed['applied_adjustments'] ?? []);

        if ($confidenceReasons === []) {
            $confidenceReasons = [
                'No se encontraron comparables útiles dentro de la misma colonia o municipio.',
                'Se usó estimación de apoyo potenciada por IA.',
                'Este resultado es orientativo y de baja confiabilidad.',
            ];
        }

        // Add cap reason
        $confidenceReasons[] = sprintf('Confianza limitada a %d (cap por evidencia insuficiente).', $confidenceCap);

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
                '2) Se consultó motor de IA con datos del sujeto, priors de mercado y restricciones de confianza.',
                sprintf('3) Valor estimado IA: $%s MXN (clamped a constraints).', number_format($estimatedValue, 0)),
            ];
        }

        $requestId = isset($body['id']) ? (string) $body['id'] : null;

        $this->lastAttempt['status'] = 'success';
        $this->lastAttempt['detail'] = $requestId;

        log_message('info', 'OpenAI valuation completed. status={status} request_id={requestId} value={value} confidence={confidence} ppu={ppu}', [
            'status' => $this->lastAttempt['status'],
            'requestId' => $requestId ?? 'n/a',
            'value' => (string) $estimatedValue,
            'confidence' => (string) $confidenceScore,
            'ppu' => $estimatedPpu !== null ? (string) $estimatedPpu : 'n/a',
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
                    'confidence_cap' => $confidenceCap,
                    'constraints_applied' => $constraints,
                    'input_summary' => sprintf(
                        'IA usó: tipo=%s, municipio=%s, colonia=%s, construcción=%s m², edad=%d años, conservación=%d/10, priors(n=%d, p50=$%s/m²), cap=%d.',
                        (string) ($subject['property_type'] ?? 'N/D'),
                        (string) ($subject['municipality'] ?? 'N/D'),
                        (string) ($subject['colony'] ?? 'N/D'),
                        number_format((float) ($subject['area_construction_m2'] ?? 0), 2),
                        (int) ($subject['age_years'] ?? 0),
                        (int) ($subject['conservation_level'] ?? 0),
                        (int) ($priors['n'] ?? 0),
                        number_format((float) ($priors['p50_ppu'] ?? 0), 0),
                        $confidenceCap,
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

    // ═══════════════════════════════════════════════════════════════
    //  Constraints & prompt building
    // ═══════════════════════════════════════════════════════════════

    /**
     * Build value/PPU constraints from priors for clamping.
     *
     * @return array{ppu_min: float, ppu_max: float, value_min: float, value_max: float, confidence_cap: int}
     */
    private function buildConstraints(array $subject, array $priors, int $confidenceCap): array
    {
        $ppuMin = max(5000.0, ((float) ($priors['p25_ppu'] ?? 5000)) * 0.7);
        $ppuMax = min(80000.0, ((float) ($priors['p75_ppu'] ?? 80000)) * 1.3);

        // Ensure min < max
        if ($ppuMin >= $ppuMax) {
            $ppuMin = 5000.0;
            $ppuMax = 80000.0;
        }

        $area = max(1.0, (float) ($subject['area_construction_m2'] ?? 1));

        return [
            'ppu_min' => round($ppuMin, 0),
            'ppu_max' => round($ppuMax, 0),
            'value_min' => round($ppuMin * $area * 0.8, -3),
            'value_max' => round($ppuMax * $area * 1.2, -3),
            'confidence_cap' => $confidenceCap,
        ];
    }

    private function buildSystemPrompt(): string
    {
        return implode("\n", [
            'Eres un valuador inmobiliario certificado en México, especialista en mercado residencial de Nuevo León.',
            '',
            'Reglas estrictas:',
            '1. Devuelve EXCLUSIVAMENTE un JSON válido. Sin markdown, sin texto adicional, sin bloques de código.',
            '2. NO inventes comparables, direcciones ni fuentes de datos.',
            '3. Si no hay comparables (n=0): estima usando costo de reposición + terreno + depreciación + negociación, ÚNICAMENTE con los priors proporcionados.',
            '4. El campo confidence_score DEBE ser <= al confidence_cap proporcionado en constraints.',
            '5. Los valores estimados deben estar dentro de los rangos value_min/value_max de constraints.',
            '6. El PPU estimado (estimated_value_per_m2) debe estar dentro de ppu_min/ppu_max de constraints.',
            '7. Todos los montos en MXN. Redondea el valor final a miles.',
            '8. Sé conservador: ante la duda, usa el rango bajo de los priors.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayload(
        array $subject,
        string $locationScope,
        int $rawCount,
        int $usefulCount,
        array $priors,
        ?array $localResult,
        array $constraints,
    ): array {
        $payload = [
            'task' => 'Estimar valor de mercado de un inmueble residencial en Nuevo León, México.',
            'subject' => [
                'property_type' => $subject['property_type'] ?? 'casa',
                'municipality' => $subject['municipality'] ?? '',
                'colony' => $subject['colony'] ?? '',
                'area_construction_m2' => $subject['area_construction_m2'] ?? 0,
                'area_land_m2' => $subject['area_land_m2'],
                'age_years' => $subject['age_years'] ?? 0,
                'conservation_level' => $subject['conservation_level'] ?? 7,
                'bedrooms' => $subject['bedrooms'],
                'bathrooms' => $subject['bathrooms'],
                'half_bathrooms' => $subject['half_bathrooms'],
                'parking' => $subject['parking'],
            ],
            'comparable_summary' => [
                'n_found' => $rawCount,
                'n_used' => $usefulCount,
                'scope_searched' => $locationScope,
                'note' => 'No hay comparables directos útiles. Usa los priors de mercado proporcionados.',
            ],
            'constraints' => [
                'do_not_invent_comparables' => true,
                'ppu_min' => $constraints['ppu_min'],
                'ppu_max' => $constraints['ppu_max'],
                'value_min' => $constraints['value_min'],
                'value_max' => $constraints['value_max'],
                'confidence_cap' => $constraints['confidence_cap'],
            ],
            'output_schema' => [
                'estimated_value' => 'number (MXN, rounded to thousands)',
                'estimated_low' => 'number (MXN)',
                'estimated_high' => 'number (MXN)',
                'estimated_value_per_m2' => 'number (PPU in MXN/m²)',
                'applied_adjustments' => [['factor' => 'string', 'percentage' => 'number', 'monetary_impact' => 'number', 'rationale' => 'string']],
                'methodology_summary' => 'string',
                'confidence_score' => 'integer (must be <= confidence_cap)',
                'confidence_reasons' => ['string'],
                'human_steps' => ['string'],
                'advisor_detail_steps' => ['string'],
                'ai_disclaimer' => 'string',
            ],
        ];

        // Add geo if available
        if (isset($subject['lat']) && $subject['lat'] !== null) {
            $payload['subject']['lat'] = $subject['lat'];
            $payload['subject']['lng'] = $subject['lng'];
        }
        if (isset($subject['address']) && $subject['address'] !== '') {
            $payload['subject']['address'] = $subject['address'];
        }

        // Add priors if available
        if (($priors['n'] ?? 0) > 0) {
            $payload['comparable_summary']['market_priors'] = [
                'n_listings_municipio' => $priors['n'],
                'ppu_p25' => $priors['p25_ppu'] ?? null,
                'ppu_median' => $priors['p50_ppu'] ?? null,
                'ppu_p75' => $priors['p75_ppu'] ?? null,
                'ppu_avg' => $priors['avg_ppu'] ?? null,
                'scope' => $priors['scope'] ?? 'municipio',
            ];
        }

        // Add local result for reference
        if ($localResult !== null) {
            $payload['local_result'] = [
                'estimated_value' => $localResult['estimated_value'] ?? null,
                'ppu_used' => $localResult['ppu_base'] ?? null,
                'range_low' => $localResult['estimated_low'] ?? null,
                'range_high' => $localResult['estimated_high'] ?? null,
                'method' => $localResult['calc_breakdown']['method'] ?? 'synthetic_local_fallback',
            ];
        }

        return $payload;
    }

    // ═══════════════════════════════════════════════════════════════
    //  Configuration helpers
    // ═══════════════════════════════════════════════════════════════

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

    // ═══════════════════════════════════════════════════════════════
    //  Response parsing & normalization
    // ═══════════════════════════════════════════════════════════════

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

        $number = round($number / 10) * 10;
        if ($number < 3000 || $number > 100000) {
            return null;
        }

        return $number;
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
