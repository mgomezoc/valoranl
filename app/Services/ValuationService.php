<?php

namespace App\Services;

use App\Models\ListingModel;

class ValuationService
{
    private const MIN_COMPARABLES = 5;
    private const TARGET_COMPARABLES = 10;
    private const FALLBACK_BASE_PPU = 18000.0;

    public function __construct(private readonly ListingModel $listingModel = new ListingModel())
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $subject = $this->normalizeInput($input);
        $areaMin = $subject['area_construction_m2'] * 0.7;
        $areaMax = $subject['area_construction_m2'] * 1.3;

        $locationScope = 'colonia';
        $rawComparables = $this->getComparables($subject, $areaMin, $areaMax, useColony: true);

        if (count($rawComparables) < self::TARGET_COMPARABLES) {
            $rawComparables = $this->getComparables($subject, $areaMin, $areaMax, useColony: false);
            $locationScope = 'municipio';
        }

        if (count($rawComparables) < self::MIN_COMPARABLES) {
            $rawComparables = $this->getFallbackComparablesByMunicipality($subject);
            $locationScope = 'municipio_ampliado';
        }

        if (count($rawComparables) < self::MIN_COMPARABLES) {
            $rawComparables = $this->getFallbackComparablesStatewide($subject);
            $locationScope = 'estado';
        }

        $prepared = $this->prepareComparables($rawComparables, $subject, $locationScope);

        $comparablesRawCount = count($rawComparables);
        $comparablesUsefulCount = count($prepared);

        if ($prepared === []) {
            return $this->buildSyntheticEstimate($subject);
        }

        $ppus = array_column($prepared, 'ppu_m2');
        $scores = array_column($prepared, 'similarity_score');

        $ppuBase = $this->weightedMedian($ppus, $scores);
        $ppuAdjusted = $this->applySizeAdjustment($ppuBase, $subject['area_construction_m2'], $prepared);

        $estimatedValue = $ppuAdjusted * $subject['area_construction_m2'];
        $ppuP25 = $this->percentile($ppus, 0.25);
        $ppuP75 = $this->percentile($ppus, 0.75);
        $estimatedLow = $ppuP25 * $subject['area_construction_m2'];
        $estimatedHigh = $ppuP75 * $subject['area_construction_m2'];

        $confidence = $this->buildConfidence($prepared, $locationScope);

        usort($prepared, static fn(array $a, array $b): int => $b['similarity_score'] <=> $a['similarity_score']);
        $topComparables = array_slice($prepared, 0, 10);

        return [
            'ok' => true,
            'message' => $locationScope === 'estado'
                ? 'Valuación estimada con referencia estatal por baja disponibilidad local de comparables.'
                : 'Valuación estimada calculada correctamente.',
            'subject' => $subject,
            'estimated_value' => round($estimatedValue, 2),
            'estimated_low' => round($estimatedLow, 2),
            'estimated_high' => round($estimatedHigh, 2),
            'ppu_base' => round($ppuAdjusted, 2),
            'comparables_count' => count($prepared),
            'comparables' => $topComparables,
            'confidence_score' => $confidence['score'],
            'confidence_reasons' => $confidence['reasons'],
            'location_scope' => $locationScope,
            'calc_breakdown' => [
                'method' => 'comparables_v1',
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
                'comparables_raw' => $comparablesRawCount,
                'comparables_useful' => $comparablesUsefulCount,
                'ppu_stats' => [
                    'weighted_median' => round($ppuBase, 2),
                    'adjusted_ppu' => round($ppuAdjusted, 2),
                    'p25' => round($ppuP25, 2),
                    'p75' => round($ppuP75, 2),
                ],
                'formula' => [
                    'estimated_value' => 'adjusted_ppu * subject_area_m2',
                    'estimated_low' => 'p25_ppu * subject_area_m2',
                    'estimated_high' => 'p75_ppu * subject_area_m2',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
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
        ];
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function getComparables(array $subject, float $areaMin, float $areaMax, bool $useColony): array
    {
        $builder = $this->listingModel->builder();
        $builder->select('id, url, title, property_type, municipality, colony, area_construction_m2, bedrooms, bathrooms, parking, lat, lng, price_amount, currency')
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
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function getFallbackComparablesByMunicipality(array $subject): array
    {
        $builder = $this->listingModel->builder();

        return $builder->select('id, url, title, property_type, municipality, colony, area_construction_m2, bedrooms, bathrooms, parking, lat, lng, price_amount, currency')
            ->where('status', 'active')
            ->where('price_type', 'sale')
            ->where('municipality', $subject['municipality'])
            ->where('price_amount IS NOT NULL', null, false)
            ->where('area_construction_m2 IS NOT NULL', null, false)
            ->where('area_construction_m2 >', 0)
            ->orderBy('updated_at', 'DESC')
            ->limit(350)
            ->get()->getResultArray();
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<int, array<string, mixed>>
     */
    private function getFallbackComparablesStatewide(array $subject): array
    {
        $builder = $this->listingModel->builder();

        return $builder->select('id, url, title, property_type, municipality, colony, area_construction_m2, bedrooms, bathrooms, parking, lat, lng, price_amount, currency')
            ->where('status', 'active')
            ->where('price_type', 'sale')
            ->where('property_type', $subject['property_type'])
            ->where('state', 'Nuevo León')
            ->where('price_amount IS NOT NULL', null, false)
            ->where('area_construction_m2 IS NOT NULL', null, false)
            ->where('area_construction_m2 >', 0)
            ->orderBy('updated_at', 'DESC')
            ->limit(500)
            ->get()->getResultArray();
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
     * @param array<int, float> $values
     * @param array<int, float> $weights
     */
    private function weightedMedian(array $values, array $weights): float
    {
        if ($values === []) {
            return 0.0;
        }

        $pairs = [];
        $totalWeight = 0.0;

        foreach ($values as $index => $value) {
            $weight = max(0.01, (float) ($weights[$index] ?? 0.01));
            $pairs[] = ['value' => (float) $value, 'weight' => $weight];
            $totalWeight += $weight;
        }

        usort($pairs, static fn(array $a, array $b): int => $a['value'] <=> $b['value']);

        $accumulated = 0.0;
        $half = $totalWeight / 2;

        foreach ($pairs as $pair) {
            $accumulated += $pair['weight'];
            if ($accumulated >= $half) {
                return (float) $pair['value'];
            }
        }

        return (float) end($pairs)['value'];
    }

    /**
     * @param array<int, array<string, mixed>> $comparables
     */
    private function applySizeAdjustment(float $ppuBase, float $subjectArea, array $comparables): float
    {
        if ($ppuBase <= 0 || $subjectArea <= 0 || $comparables === []) {
            return $ppuBase;
        }

        $areas = array_map(static fn(array $item): float => (float) $item['area_construction_m2'], $comparables);
        $medianComparableArea = $this->percentile($areas, 0.5);

        if ($medianComparableArea <= 0 || $subjectArea <= $medianComparableArea) {
            return $ppuBase;
        }

        $k = 0.05;
        $factor = 1 - ($k * (($subjectArea / $medianComparableArea) - 1));

        return $ppuBase * max(0.90, $factor);
    }

    /**
     * @param array<int, array<string, mixed>> $comparables
     * @return array{score: int, reasons: array<int, string>}
     */
    private function buildConfidence(array $comparables, string $locationScope): array
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
                'municipio_ampliado' => 'Comparables ampliados dentro del municipio por baja muestra local.',
                default => 'Comparables de referencia estatal por baja disponibilidad local.',
            },
        ];

        return ['score' => $score, 'reasons' => $reasons];
    }

    /**
     * @param array<string, mixed> $subject
     * @return array<string, mixed>
     */
    private function buildSyntheticEstimate(array $subject): array
    {
        $estimatedValue = self::FALLBACK_BASE_PPU * $subject['area_construction_m2'];

        return [
            'ok' => true,
            'message' => 'Valuación estimada con referencia base por falta de comparables publicados en la zona.',
            'subject' => $subject,
            'estimated_value' => round($estimatedValue, 2),
            'estimated_low' => round($estimatedValue * 0.85, 2),
            'estimated_high' => round($estimatedValue * 1.15, 2),
            'ppu_base' => self::FALLBACK_BASE_PPU,
            'comparables_count' => 0,
            'comparables' => [],
            'confidence_score' => 18,
            'confidence_reasons' => [
                'No se localizaron comparables activos con datos completos.',
                'Se aplicó una referencia base de mercado para orientación rápida.',
                'Este resultado es informativo y no sustituye un avalúo profesional.',
            ],
            'location_scope' => 'sintetico',
            'calc_breakdown' => [
                'method' => 'synthetic_fallback_v1',
                'scope_used' => 'sintetico',
                'comparables_raw' => 0,
                'comparables_useful' => 0,
                'ppu_stats' => [
                    'weighted_median' => 0,
                    'adjusted_ppu' => self::FALLBACK_BASE_PPU,
                    'p25' => self::FALLBACK_BASE_PPU * 0.85,
                    'p75' => self::FALLBACK_BASE_PPU * 1.15,
                ],
                'formula' => [
                    'estimated_value' => 'fallback_ppu * subject_area_m2',
                    'estimated_low' => '(fallback_ppu * 0.85) * subject_area_m2',
                    'estimated_high' => '(fallback_ppu * 1.15) * subject_area_m2',
                ],
            ],
        ];
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
