<?php

namespace App\Services;

use App\Libraries\OpenAiValuationService;
use App\Libraries\ValuationMath;
use App\Models\ListingModel;

class ValuationService
{

    public function __construct(
        private readonly ListingModel $listingModel = new ListingModel(),
        private readonly ValuationMath $valuationMath = new ValuationMath(),
        private readonly OpenAiValuationService $openAiValuationService = new OpenAiValuationService(),
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $subject = $this->normalizeInput($input);

        $subjectDepr = $this->valuationMath->depreciation(
            ageYears: $subject['age_years'],
            conservationLevel: $subject['conservation_level'],
        );
        $rossHeideckeFactor = $this->valuationMath->rossHeideckeFactor(
            ageYears: $subject['age_years'],
            conservationLevel: $subject['conservation_level'],
        );

        $areaMin = $subject['area_construction_m2'] * 0.7;
        $areaMax = $subject['area_construction_m2'] * 1.3;

        $locationScope = 'colonia';
        $rawComparables = $this->getComparables($subject, $areaMin, $areaMax, useColony: true);
        $prepared = $this->prepareComparables($rawComparables, $subject, $locationScope);

        if ($prepared === []) {
            $locationScope = 'municipio';
            $rawComparables = $this->getComparables($subject, $areaMin, $areaMax, useColony: false);
            $prepared = $this->prepareComparables($rawComparables, $subject, $locationScope);
        }

        $comparablesRawCount = count($rawComparables);
        $comparablesUsefulCount = count($prepared);

        if ($prepared === []) {
            $algorithmFallback = $this->buildSyntheticFallbackEstimate(
                subject: $subject,
                locationScope: $locationScope,
                rawCount: $comparablesRawCount,
                usefulCount: $comparablesUsefulCount,
                openAiAttemptMeta: ['attempted' => false, 'status' => 'not_called', 'detail' => null],
            );

            $openAiFallback = $this->openAiValuationService->estimateWithoutComparables(
                subject: $subject,
                locationScope: $locationScope,
                rawCount: $comparablesRawCount,
                usefulCount: $comparablesUsefulCount,
            );

            if (is_array($openAiFallback)) {
                return $this->buildAiAugmentedFallbackEstimate(
                    subject: $subject,
                    locationScope: $locationScope,
                    rawCount: $comparablesRawCount,
                    usefulCount: $comparablesUsefulCount,
                    algorithmFallback: $algorithmFallback,
                    openAiFallback: $openAiFallback,
                );
            }

            $openAiAttemptMeta = $this->openAiValuationService->getLastAttemptMeta();

            $algorithmFallback['calc_breakdown']['ai_metadata'] = [
                'provider' => 'openai',
                'attempted' => (bool) ($openAiAttemptMeta['attempted'] ?? false),
                'status' => (string) ($openAiAttemptMeta['status'] ?? 'not_called'),
                'detail' => isset($openAiAttemptMeta['detail']) ? (string) $openAiAttemptMeta['detail'] : null,
            ];

            return $algorithmFallback;
        }

        // Compute per-comparable homologation factors
        $homologated = $this->homologateComparables($prepared, $subject, $subjectDepr);

        $ppusHomologados = array_column($homologated, 'ppu_homologado');
        $ppuPromedio = array_sum($ppusHomologados) / count($ppusHomologados);
        $ppuAplicado = $this->valuationMath->roundPpu($ppuPromedio);

        $rangeSpread = (new \Config\Valuation())->rangeSpread;

        $estimatedValue = $this->valuationMath->roundValueToThousands($ppuAplicado * $subject['area_construction_m2']);
        $estimatedLow = $this->valuationMath->roundValueToThousands($ppuAplicado * (1 - $rangeSpread) * $subject['area_construction_m2']);
        $estimatedHigh = $this->valuationMath->roundValueToThousands($ppuAplicado * (1 + $rangeSpread) * $subject['area_construction_m2']);

        $confidence = $this->buildConfidence($prepared, $locationScope, $subject);

        usort($homologated, static fn(array $a, array $b): int => $b['similarity_score'] <=> $a['similarity_score']);
        $topComparables = array_slice($homologated, 0, 10);

        $result = [
            'ok' => true,
            'message' => sprintf(
                'Valuación estimada calculada con comparables del %s.',
                $this->scopeLabel($locationScope),
            ),
            'subject' => $subject,
            'estimated_value' => $estimatedValue,
            'estimated_low' => $estimatedLow,
            'estimated_high' => $estimatedHigh,
            'ppu_base' => $ppuAplicado,
            'comparables_count' => count($prepared),
            'comparables' => $topComparables,
            'confidence_score' => $confidence['score'],
            'confidence_reasons' => $confidence['reasons'],
            'location_scope' => $locationScope,
            'calc_breakdown' => [
                'method' => 'comparables_v2_excel',
                'filters' => [
                    'status' => 'active',
                    'price_type' => 'sale',
                    'property_type' => $subject['property_type'],
                    'municipality' => $subject['municipality'],
                    'colony' => $subject['colony'],
                    'area_range_m2' => [
                        'min' => round($areaMin, 2),
                        'max' => round($areaMax, 2),
                    ],
                ],
                'scope_used' => $locationScope,
                'used_properties_database' => $comparablesUsefulCount > 0,
                'data_origin' => [
                    'source' => 'listings',
                    'source_label' => 'Base interna de propiedades publicadas (tabla listings).',
                    'used_for_calculation' => $comparablesUsefulCount > 0,
                    'records_found' => $comparablesRawCount,
                    'records_used' => $comparablesUsefulCount,
                ],
                'comparables_raw' => $comparablesRawCount,
                'comparables_useful' => $comparablesUsefulCount,
                'ppu_stats' => [
                    'ppu_promedio' => round($ppuPromedio, 2),
                    'ppu_aplicado' => $ppuAplicado,
                ],
                'valuation_factors' => [
                    'ross_heidecke' => $rossHeideckeFactor,
                    'depreciation_subject' => $subjectDepr,
                    'negotiation' => $this->valuationMath->negotiationFactor(),
                    'location' => $this->valuationMath->locationFactor(),
                    'zone' => $this->valuationMath->zoneFactor(),
                ],
                'formula' => [
                    'ppu_homologado' => 'PPU_bruto × Zona × Ubicación × Superficie × Edad × Equipamiento × Negociación',
                    'ppu_aplicado' => 'ROUND(AVERAGE(PPUs_homologados), -1)',
                    'estimated_value' => 'ROUNDUP(PPU_aplicado × m²_construcción, -3)',
                    'estimated_low' => 'ROUNDUP(PPU_aplicado × 0.9 × m²_construcción, -3)',
                    'estimated_high' => 'ROUNDUP(PPU_aplicado × 1.1 × m²_construcción, -3)',
                ],
                'human_steps' => $this->buildHumanSteps(
                    $comparablesRawCount,
                    $comparablesUsefulCount,
                    $locationScope,
                    $ppuPromedio,
                    $ppuAplicado,
                    $subject,
                    $estimatedValue,
                ),
                'advisor_detail_steps' => $this->buildAdvisorSteps(
                    $comparablesUsefulCount,
                    $comparablesRawCount,
                    $locationScope,
                    $ppuPromedio,
                    $ppuAplicado,
                    $subject,
                    $estimatedValue,
                    $rossHeideckeFactor,
                    $subjectDepr,
                ),
            ],
        ];

        // Residual breakdown if construction_unit_value provided
        if ($subject['construction_unit_value'] !== null) {
            $result['residual_breakdown'] = $this->valuationMath->residualBreakdown(
                marketValue: $estimatedValue,
                constructionUnitValue: $subject['construction_unit_value'],
                areaConstructionM2: $subject['area_construction_m2'],
                depreciationFactor: $subjectDepr,
                equipmentValue: $subject['equipment_value'] ?? 0.0,
                areaLandM2: $subject['area_land_m2'] ?? 0.0,
            );
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $ageYears = isset($input['age_years']) && $input['age_years'] !== '' ? max(0, (int) $input['age_years']) : 0;

        $conservationLevel = isset($input['conservation_level']) && $input['conservation_level'] !== ''
            ? max(1, min(10, (int) $input['conservation_level']))
            : $this->valuationMath->inferConservationLevel($ageYears);

        return [
            'property_type' => trim((string) ($input['property_type'] ?? '')),
            'municipality' => trim((string) ($input['municipality'] ?? '')),
            'colony' => trim((string) ($input['colony'] ?? '')),
            'area_construction_m2' => max(0.0, (float) ($input['area_construction_m2'] ?? 0)),
            'area_land_m2' => isset($input['area_land_m2']) && $input['area_land_m2'] !== '' ? max(0.0, (float) $input['area_land_m2']) : null,
            'bedrooms' => isset($input['bedrooms']) && $input['bedrooms'] !== '' ? max(0, (int) $input['bedrooms']) : null,
            'bathrooms' => isset($input['bathrooms']) && $input['bathrooms'] !== '' ? max(0.0, (float) $input['bathrooms']) : null,
            'half_bathrooms' => isset($input['half_bathrooms']) && $input['half_bathrooms'] !== '' ? max(0, (int) $input['half_bathrooms']) : null,
            'parking' => isset($input['parking']) && $input['parking'] !== '' ? max(0, (int) $input['parking']) : null,
            'lat' => isset($input['lat']) && $input['lat'] !== '' ? (float) $input['lat'] : null,
            'lng' => isset($input['lng']) && $input['lng'] !== '' ? (float) $input['lng'] : null,
            'age_years' => $ageYears,
            'conservation_level' => $conservationLevel,
            'construction_unit_value' => isset($input['construction_unit_value']) && $input['construction_unit_value'] !== ''
                ? max(0.0, (float) $input['construction_unit_value'])
                : null,
            'equipment_value' => isset($input['equipment_value']) && $input['equipment_value'] !== ''
                ? max(0.0, (float) $input['equipment_value'])
                : null,
        ];
    }

    private const COMPARABLE_SELECT = 'id, url, title, property_type, municipality, colony, area_construction_m2, area_land_m2, age_years, bedrooms, bathrooms, parking, lat, lng, price_amount, currency';

    /**
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function getComparables(array $subject, float $areaMin, float $areaMax, bool $useColony): array
    {
        $builder = $this->listingModel->builder();
        $builder->select(self::COMPARABLE_SELECT)
            ->where('status', 'active')
            ->where('price_type', 'sale')
            ->where('property_type', $subject['property_type'])
            ->where('municipality', $subject['municipality'])
            ->where('price_amount IS NOT NULL', null, false)
            ->where('area_construction_m2 IS NOT NULL', null, false)
            ->where('area_construction_m2 >', 0)
            ->where('area_construction_m2 >=', $areaMin)
            ->where('area_construction_m2 <=', $areaMax)
            ->orderBy('updated_at', 'DESC')
            ->limit(200);

        if ($useColony) {
            $builder->where('colony', $subject['colony']);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function prepareComparables(array $rows, array $subject, string $locationScope): array
    {
        $comparables = [];

        foreach ($rows as $row) {
            $area = (float) ($row['area_construction_m2'] ?? 0);
            $price = (float) ($row['price_amount'] ?? 0);

            if ($area <= 0 || $price <= 0) {
                continue;
            }

            $ppu = $price / $area;
            $distanceKm = $this->computeDistanceKm(
                $subject['lat'],
                $subject['lng'],
                isset($row['lat']) ? (float) $row['lat'] : null,
                isset($row['lng']) ? (float) $row['lng'] : null,
            );

            $comparables[] = [
                'id' => (int) $row['id'],
                'title' => (string) ($row['title'] ?? 'Comparable'),
                'url' => (string) ($row['url'] ?? ''),
                'municipality' => (string) ($row['municipality'] ?? ''),
                'colony' => (string) ($row['colony'] ?? ''),
                'currency' => (string) ($row['currency'] ?? 'MXN'),
                'price_amount' => $price,
                'area_construction_m2' => $area,
                'area_land_m2' => isset($row['area_land_m2']) && $row['area_land_m2'] !== null ? (float) $row['area_land_m2'] : null,
                'age_years' => isset($row['age_years']) && $row['age_years'] !== null ? (int) $row['age_years'] : null,
                'bedrooms' => $row['bedrooms'] !== null ? (int) $row['bedrooms'] : null,
                'bathrooms' => $row['bathrooms'] !== null ? (float) $row['bathrooms'] : null,
                'parking' => $row['parking'] !== null ? (int) $row['parking'] : null,
                'ppu_m2' => $ppu,
                'distance_km' => $distanceKm,
                'similarity_score' => $this->computeSimilarity($subject, $row, $distanceKm),
                'location_scope' => $locationScope,
            ];
        }

        return array_values($this->removeOutliersByIqr($comparables));
    }

    /**
     * Compute per-comparable homologation factors and homologated PPU.
     *
     * @param array<int, array<string, mixed>> $comparables
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function homologateComparables(array $comparables, array $subject, float $subjectDepr): array
    {
        $zoneFactor = $this->valuationMath->zoneFactor();
        $locationFactor = $this->valuationMath->locationFactor();
        $negotiationFactor = $this->valuationMath->negotiationFactor();
        $equipmentFactor = 1.0;

        $result = [];

        foreach ($comparables as $comp) {
            $ppuBruto = $comp['ppu_m2'];

            // Surface factor
            $surfaceFactor = 1.0;
            $compLand = $comp['area_land_m2'];
            $subjLand = $subject['area_land_m2'];
            if ($compLand !== null && $compLand > 0 && $subjLand !== null && $subjLand > 0) {
                $surfaceFactor = $this->valuationMath->surfaceHomologationFactor(
                    $comp['area_construction_m2'],
                    $compLand,
                    $subject['area_construction_m2'],
                    $subjLand,
                );
            }

            // Age factor
            $ageFactor = 1.0;
            if ($comp['age_years'] !== null) {
                $compConservation = $this->valuationMath->inferConservationLevel($comp['age_years']);
                $compDepr = $this->valuationMath->depreciation($comp['age_years'], $compConservation);
                $ageFactor = $this->valuationMath->ageHomologationFactor($subjectDepr, $compDepr);
            }

            $fre = $zoneFactor * $locationFactor * $surfaceFactor * $ageFactor * $equipmentFactor * $negotiationFactor;
            $ppuHomologado = $ppuBruto * $fre;

            $comp['homologation_factors'] = [
                'zone' => round($zoneFactor, 4),
                'location' => round($locationFactor, 4),
                'surface' => round($surfaceFactor, 4),
                'age' => round($ageFactor, 4),
                'equipment' => round($equipmentFactor, 4),
                'negotiation' => round($negotiationFactor, 4),
                'fre' => round($fre, 4),
            ];
            $comp['ppu_homologado'] = round($ppuHomologado, 2);

            $result[] = $comp;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $row
     */
    private function computeSimilarity(array $subject, array $row, ?float $distanceKm): float
    {
        $subjectArea = (float) $subject['area_construction_m2'];
        $rowArea = (float) ($row['area_construction_m2'] ?? 0);
        $areaNorm = $subjectArea > 0 ? min(1.0, abs($subjectArea - $rowArea) / $subjectArea) : 1.0;

        $bedsNorm = $this->featureDiffNorm($subject['bedrooms'], $row['bedrooms'] ?? null, 5);
        $bathsNorm = $this->featureDiffNorm($subject['bathrooms'], $row['bathrooms'] ?? null, 4);
        $parkingNorm = $this->featureDiffNorm($subject['parking'], $row['parking'] ?? null, 4);

        $distanceNorm = $distanceKm !== null ? min(1.0, $distanceKm / 20.0) : null;

        $weights = $distanceNorm !== null
            ? ['distance' => 0.40, 'area' => 0.25, 'rooms' => 0.20, 'bathParking' => 0.15]
            : ['distance' => 0.00, 'area' => 0.50, 'rooms' => 0.30, 'bathParking' => 0.20];

        $penalty = ($weights['distance'] * ($distanceNorm ?? 0.0))
            + ($weights['area'] * $areaNorm)
            + ($weights['rooms'] * $bedsNorm)
            + ($weights['bathParking'] * (($bathsNorm + $parkingNorm) / 2));

        return max(0.05, round(1 - $penalty, 4));
    }

    private function featureDiffNorm(mixed $subjectValue, mixed $rowValue, float $maxDiff): float
    {
        if ($subjectValue === null || $rowValue === null || $maxDiff <= 0) {
            return 0.5;
        }

        return min(1.0, abs((float) $subjectValue - (float) $rowValue) / $maxDiff);
    }

    private function computeDistanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @param array<int, array<string, mixed>> $comparables
     * @return array<int, array<string, mixed>>
     */
    private function removeOutliersByIqr(array $comparables): array
    {
        if (count($comparables) < 8) {
            return $comparables;
        }

        $ppus = array_column($comparables, 'ppu_m2');
        $q1 = $this->percentile($ppus, 0.25);
        $q3 = $this->percentile($ppus, 0.75);
        $iqr = $q3 - $q1;

        if ($iqr <= 0) {
            return $comparables;
        }

        $min = $q1 - (1.5 * $iqr);
        $max = $q3 + (1.5 * $iqr);

        return array_values(array_filter(
            $comparables,
            static fn(array $item): bool => $item['ppu_m2'] >= $min && $item['ppu_m2'] <= $max,
        ));
    }

    /**
     * @param array<int, float> $values
     */
    private function percentile(array $values, float $percentile): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);

        if ($count === 1) {
            return (float) $values[0];
        }

        $index = ($count - 1) * $percentile;
        $floor = (int) floor($index);
        $ceil = (int) ceil($index);

        if ($floor === $ceil) {
            return (float) $values[$floor];
        }

        $weight = $index - $floor;

        return ((1 - $weight) * (float) $values[$floor]) + ($weight * (float) $values[$ceil]);
    }

    /**
     * @param array<int, array<string, mixed>> $comparables
     * @param array<string, mixed> $subject
     * @return array{score: int, reasons: array<int, string>}
     */
    private function buildConfidence(array $comparables, string $locationScope, array $subject): array
    {
        $n = count($comparables);
        $baseN = min(1.0, $n / 20);

        $ppus = array_column($comparables, 'ppu_m2');
        $mean = array_sum($ppus) / max(1, count($ppus));
        $std = $this->stdDev($ppus, $mean);
        $dispersionRatio = $mean > 0 ? $std / $mean : 1;
        $baseDispersion = max(0.2, 1 - min(1.0, $dispersionRatio));

        $baseLocation = match ($locationScope) {
            'colonia' => 1.0,
            'municipio' => 0.85,
            'municipio_ampliado' => 0.75,
            default => 0.60,
        };

        $score = (int) round(100 * $baseN * $baseDispersion * $baseLocation);
        $score = max(15, min(98, $score));

        $reasons = [
            sprintf('%d comparables útiles.', $n),
            sprintf('Dispersión %s (σ/μ %.2f).', $dispersionRatio < 0.25 ? 'baja' : ($dispersionRatio < 0.45 ? 'media' : 'alta'), $dispersionRatio),
            match ($locationScope) {
                'colonia' => 'Comparables de la misma colonia.',
                'municipio' => 'Comparables del mismo municipio.',
                default => 'Comparables del mismo municipio.',
            },
        ];

        if ($subject['age_years'] === 0 && !isset($subject['_age_provided'])) {
            $reasons[] = 'Edad del inmueble no proporcionada (default 0); el resultado puede variar.';
        }
        if ($subject['area_land_m2'] === null) {
            $reasons[] = 'Sin m² de terreno; factor de superficie no aplicado.';
        }

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    private function buildSyntheticFallbackEstimate(
        array $subject,
        string $locationScope,
        int $rawCount,
        int $usefulCount,
        array $openAiAttemptMeta = [],
    ): array {
        $fallbackPpu = 18000.0;
        $rossHeideckeFactor = $this->valuationMath->rossHeideckeFactor(
            ageYears: $subject['age_years'],
            conservationLevel: $subject['conservation_level'],
        );
        $negotiationFactor = $this->valuationMath->negotiationFactor();
        $adjustmentFactor = round($rossHeideckeFactor * $negotiationFactor, 4);

        $rawValue = $fallbackPpu * $subject['area_construction_m2'] * $adjustmentFactor;
        $estimatedValue = $this->valuationMath->roundValueToThousands($rawValue);
        $rangeSpread = (new \Config\Valuation())->rangeSpread;

        $aiStatus = (string) ($openAiAttemptMeta['status'] ?? 'not_called');
        $aiAttempted = (bool) ($openAiAttemptMeta['attempted'] ?? false);
        $aiDetail = isset($openAiAttemptMeta['detail']) ? (string) $openAiAttemptMeta['detail'] : null;

        return [
            'ok' => true,
            'message' => 'No hubo comparables útiles en colonia/municipio; se muestra una estimación de apoyo con baja confianza.',
            'subject' => $subject,
            'estimated_value' => $estimatedValue,
            'estimated_low' => $this->valuationMath->roundValueToThousands($estimatedValue * (1 - $rangeSpread)),
            'estimated_high' => $this->valuationMath->roundValueToThousands($estimatedValue * (1 + $rangeSpread)),
            'ppu_base' => $fallbackPpu,
            'comparables_count' => 0,
            'comparables' => [],
            'confidence_score' => 18,
            'confidence_reasons' => [
                'No se encontraron comparables útiles dentro de la misma colonia o municipio.',
                'Se calculó una estimación de apoyo con referencia base de mercado (sin comparables directos).',
                'El resultado es orientativo y su confiabilidad es baja.',
                sprintf('Estado de consulta IA: %s%s.', $aiStatus, $aiDetail ? ' (' . $aiDetail . ')' : ''),
            ],
            'location_scope' => $locationScope,
            'calc_breakdown' => [
                'method' => 'synthetic_local_fallback',
                'scope_used' => $locationScope,
                'used_properties_database' => false,
                'data_origin' => [
                    'source' => 'fallback_reference',
                    'source_label' => 'Referencia base de mercado para orientación (sin comparables locales útiles).',
                    'used_for_calculation' => false,
                    'records_found' => $rawCount,
                    'records_used' => $usefulCount,
                ],
                'comparables_raw' => $rawCount,
                'comparables_useful' => $usefulCount,
                'ppu_stats' => [
                    'ppu_promedio' => null,
                    'ppu_aplicado' => $fallbackPpu,
                ],
                'valuation_factors' => [
                    'ross_heidecke' => $rossHeideckeFactor,
                    'negotiation' => $negotiationFactor,
                    'combined_adjustment_factor' => $adjustmentFactor,
                ],
                'ai_metadata' => [
                    'provider' => 'openai',
                    'attempted' => $aiAttempted,
                    'status' => $aiStatus,
                    'detail' => $aiDetail,
                ],
                'formula' => [
                    'estimated_value' => 'ROUNDUP(fallback_ppu × m²_construcción × adjustment_factor, -3)',
                ],
                'human_steps' => [
                    sprintf(
                        'Se buscaron comparables en %s: %d encontrados, %d útiles. Al no haber muestra suficiente, aplicamos una estimación de apoyo.',
                        $this->scopeLabel($locationScope),
                        $rawCount,
                        $usefulCount,
                    ),
                    sprintf(
                        'Se usó un PPU base de $%s/m² con ajuste por estado/edad (Ross-Heidecke) y negociación.',
                        number_format($fallbackPpu, 0),
                    ),
                    sprintf(
                        'Cálculo estimado: $%s × %s m² × %.4f = $%s MXN.',
                        number_format($fallbackPpu, 0),
                        number_format((float) $subject['area_construction_m2'], 2),
                        $adjustmentFactor,
                        number_format($estimatedValue, 0),
                    ),
                    'Importante: este valor es orientativo y tiene baja confiabilidad por falta de comparables locales.',
                    sprintf('Consulta IA en fallback: %s%s.', $aiStatus, $aiDetail ? ' (' . $aiDetail . ')' : ''),
                ],
                'advisor_detail_steps' => [
                    sprintf(
                        '1) Búsqueda local restringida (colonia/municipio). Registros encontrados: %d. Utilizables: %d.',
                        $rawCount,
                        $usefulCount,
                    ),
                    sprintf(
                        '2) Fallback sintético local: PPU base $%s/m².',
                        number_format($fallbackPpu, 2),
                    ),
                    sprintf(
                        '3) Ajuste aplicado: Ross-Heidecke %.4f × Negociación %.4f = %.4f.',
                        $rossHeideckeFactor,
                        $negotiationFactor,
                        $adjustmentFactor,
                    ),
                    sprintf(
                        '4) Valor final = ROUNDUP($%s × %s × %.4f, -3) = $%s MXN.',
                        number_format($fallbackPpu, 2),
                        number_format((float) $subject['area_construction_m2'], 2),
                        $adjustmentFactor,
                        number_format($estimatedValue, 0),
                    ),
                ],
            ],
        ];
    }


    /**
     * @param array<string, mixed> $subject
     * @param array<string, mixed> $algorithmFallback
     * @param array<string, mixed> $openAiFallback
     * @return array<string, mixed>
     */
    private function buildAiAugmentedFallbackEstimate(
        array $subject,
        string $locationScope,
        int $rawCount,
        int $usefulCount,
        array $algorithmFallback,
        array $openAiFallback,
    ): array {
        $rangeSpread = (new \Config\Valuation())->rangeSpread;

        $aiPpu = isset($openAiFallback['calc_breakdown']['ppu_stats']['ppu_aplicado'])
            ? (float) $openAiFallback['calc_breakdown']['ppu_stats']['ppu_aplicado']
            : 0.0;

        if ($aiPpu <= 0 && isset($openAiFallback['estimated_value'], $subject['area_construction_m2']) && (float) $subject['area_construction_m2'] > 0) {
            $aiPpu = (float) $openAiFallback['estimated_value'] / (float) $subject['area_construction_m2'];
        }

        $aiPpu = $this->valuationMath->roundPpu($aiPpu > 0 ? $aiPpu : 18000.0);
        $aiAdjustedValue = $this->valuationMath->roundValueToThousands($aiPpu * (float) $subject['area_construction_m2']);

        $result = $openAiFallback;
        $result['message'] = 'Sin comparables locales, se muestran dos referencias: algoritmo base y cálculo potenciado con OpenAI.';
        $result['estimated_value'] = $aiAdjustedValue;
        $result['estimated_low'] = $this->valuationMath->roundValueToThousands($aiAdjustedValue * (1 - $rangeSpread));
        $result['estimated_high'] = $this->valuationMath->roundValueToThousands($aiAdjustedValue * (1 + $rangeSpread));
        $result['ppu_base'] = $aiPpu;

        $result['confidence_reasons'][] = 'La estimación final usa el PPU sugerido por IA como reemplazo de comparables faltantes y aplica el ajuste del algoritmo existente.';

        $result['calc_breakdown']['method'] = 'ai_augmented_algorithm_fallback';
        $result['calc_breakdown']['scope_used'] = $locationScope;
        $result['calc_breakdown']['comparables_raw'] = $rawCount;
        $result['calc_breakdown']['comparables_useful'] = $usefulCount;
        $result['calc_breakdown']['used_properties_database'] = false;
        $result['calc_breakdown']['data_origin']['source_label'] = 'PPU de apoyo generado por OpenAI para reemplazar comparables faltantes y continuar con el algoritmo local.';
        $result['calc_breakdown']['data_origin']['used_for_calculation'] = false;
        $result['calc_breakdown']['ppu_stats']['ppu_aplicado'] = $aiPpu;
        $result['calc_breakdown']['valuation_factors']['ai_ppu_direct'] = true;
        $result['calc_breakdown']['formula']['estimated_value'] = 'ROUNDUP(ppu_openai × m²_construcción, -3)';
        $result['calc_breakdown']['result_comparison'] = [
            'algorithm_existing' => [
                'label' => 'Algoritmo original (sin IA)',
                'estimated_value' => $algorithmFallback['estimated_value'] ?? null,
                'estimated_low' => $algorithmFallback['estimated_low'] ?? null,
                'estimated_high' => $algorithmFallback['estimated_high'] ?? null,
                'ppu_used' => $algorithmFallback['ppu_base'] ?? null,
            ],
            'ai_augmented' => [
                'label' => 'Algoritmo con PPU de OpenAI',
                'estimated_value' => $result['estimated_value'],
                'estimated_low' => $result['estimated_low'],
                'estimated_high' => $result['estimated_high'],
                'ppu_used' => $aiPpu,
            ],
        ];

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function buildAdvisorSteps(
        int $usefulCount,
        int $rawCount,
        string $scope,
        float $ppuPromedio,
        float $ppuAplicado,
        array $subject,
        float $estimatedValue,
        float $rossHeidecke,
        float $subjectDepr,
    ): array {
        return [
            sprintf(
                '1) Se depuraron comparables activos y se conservaron %d propiedades útiles de %d encontradas en el alcance %s.',
                $usefulCount,
                $rawCount,
                $scope,
            ),
            sprintf(
                '2) Se calcularon factores de homologación por comparable (Zona × Ubicación × Superficie × Edad × Equipamiento × Negociación).',
            ),
            sprintf(
                '3) PPU promedio homologado: $%s/m² → PPU aplicado (redondeado a decenas): $%s/m².',
                number_format($ppuPromedio, 2),
                number_format($ppuAplicado, 0),
            ),
            sprintf(
                '4) Ross-Heidecke del sujeto: %.4f (depreciación: %.4f). Edad: %d años, Conservación: %d.',
                $rossHeidecke,
                $subjectDepr,
                $subject['age_years'],
                $subject['conservation_level'],
            ),
            sprintf(
                '5) Valor = ROUNDUP(PPU_aplicado × m²_construcción, -3) = ROUNDUP($%s × %s, -3) = $%s MXN.',
                number_format($ppuAplicado, 0),
                number_format((float) $subject['area_construction_m2'], 2),
                number_format($estimatedValue, 0),
            ),
            sprintf(
                '6) Rangos: Mínimo (-10%%) = $%s | Máximo (+10%%) = $%s.',
                number_format($this->valuationMath->roundValueToThousands($ppuAplicado * 0.9 * $subject['area_construction_m2']), 0),
                number_format($this->valuationMath->roundValueToThousands($ppuAplicado * 1.1 * $subject['area_construction_m2']), 0),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<int, string>
     */
    private function buildHumanSteps(
        int $rawCount,
        int $usefulCount,
        string $scope,
        float $ppuPromedio,
        float $ppuAplicado,
        array $subject,
        float $estimatedValue,
    ): array {
        $scopeLabel = $this->scopeLabel($scope);

        return [
            sprintf(
                'Sí usamos datos de la base de propiedades: encontramos %d y usamos %d comparables válidos (%s).',
                $rawCount,
                $usefulCount,
                $scopeLabel,
            ),
            'A cada comparable se le ajustó su precio por m² con factores de zona, ubicación, superficie, edad, equipamiento y negociación para hacerlo comparable con tu inmueble.',
            sprintf(
                'Promedio homologado: $%s/m² → valor aplicado: $%s/m² (redondeado a decenas).',
                number_format($ppuPromedio, 2),
                number_format($ppuAplicado, 0),
            ),
            sprintf(
                'Valor final: $%s/m² × %s m² = $%s MXN (redondeado a miles).',
                number_format($ppuAplicado, 0),
                number_format((float) $subject['area_construction_m2'], 2),
                number_format($estimatedValue, 0),
            ),
            sprintf(
                'Rango de orientación: mínimo $%s y máximo $%s (±10%%).',
                number_format($this->valuationMath->roundValueToThousands($ppuAplicado * 0.9 * $subject['area_construction_m2']), 0),
                number_format($this->valuationMath->roundValueToThousands($ppuAplicado * 1.1 * $subject['area_construction_m2']), 0),
            ),
        ];
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'colonia' => 'misma colonia',
            'municipio' => 'mismo municipio',
            'municipio_ampliado' => 'municipio ampliado',
            'estado' => 'referencia estatal',
            default => $scope,
        };
    }

    /**
     * @param array<int, float> $values
     */
    private function stdDev(array $values, float $mean): float
    {
        if ($values === []) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($values as $value) {
            $sum += ($value - $mean) ** 2;
        }

        return sqrt($sum / count($values));
    }
}
