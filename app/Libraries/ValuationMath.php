<?php

namespace App\Libraries;

use Config\Valuation;

class ValuationMath
{
    public function __construct(private readonly Valuation $config = new Valuation())
    {
    }

    public function conservationMultiplier(int $conservationLevel): float
    {
        $level = max(1, min(10, $conservationLevel));

        return (float) ($this->config->conservationMultipliers[$level] ?? 1.0);
    }

    /**
     * Age depreciation factor using Ross-Heidecke exponential formula.
     * Formula: 1 - (age / usefulLife) ^ exponent
     */
    public function ageMultiplier(int $ageYears, ?int $usefulLifeYears = null): float
    {
        $lifeYears = max(1, $usefulLifeYears ?? $this->config->usefulLifeYears);
        $age = max(0, min($ageYears, $lifeYears));
        $exponent = $this->config->rossHeideckeExponent;
        $depreciation = ($age / $lifeYears) ** $exponent;

        return round(max(0.0, 1 - $depreciation), 4);
    }

    public function rossHeideckeFactor(int $ageYears, int $conservationLevel, ?int $usefulLifeYears = null): float
    {
        $ageFactor = $this->ageMultiplier($ageYears, $usefulLifeYears);
        $conservationFactor = $this->conservationMultiplier($conservationLevel);

        return round($ageFactor * $conservationFactor, 4);
    }

    /**
     * Depreciation = 1 - rossHeideckeFactor.
     */
    public function depreciation(int $ageYears, int $conservationLevel, ?int $usefulLifeYears = null): float
    {
        return round(1 - $this->rossHeideckeFactor($ageYears, $conservationLevel, $usefulLifeYears), 4);
    }

    public function negotiationFactor(): float
    {
        return $this->config->negotiationFactor;
    }

    public function locationFactor(): float
    {
        return $this->config->locationFactor;
    }

    public function zoneFactor(): float
    {
        return $this->config->zoneFactor;
    }

    public function inferConservationLevel(int $ageYears): int
    {
        $age = max(0, $ageYears);

        foreach ($this->config->conservationInferenceByAge as $ageLimit => $level) {
            if ($age <= $ageLimit) {
                return $level;
            }
        }

        return 4;
    }

    /**
     * Surface homologation factor per Excel formula:
     * ((constComp/terrComp) / (constSubj/terrSubj)) ^ (1/3)
     */
    public function surfaceHomologationFactor(
        float $constComp,
        float $terrComp,
        float $constSubj,
        float $terrSubj,
    ): float {
        if ($terrComp <= 0 || $terrSubj <= 0 || $constSubj <= 0 || $constComp <= 0) {
            return 1.0;
        }

        $ratioComp = $constComp / $terrComp;
        $ratioSubj = $constSubj / $terrSubj;

        if ($ratioSubj <= 0) {
            return 1.0;
        }

        return round(($ratioComp / $ratioSubj) ** (1 / 3), 4);
    }

    /**
     * Age homologation factor per Excel formula:
     * 1 - (depreciationSubject - depreciationComparable)
     */
    public function ageHomologationFactor(float $deprSubject, float $deprComparable): float
    {
        return round(1 - ($deprSubject - $deprComparable), 4);
    }

    /**
     * Round PPU to nearest 10 (decenas) per Excel ROUND(,-1).
     */
    public function roundPpu(float $value): float
    {
        return round($value, -1);
    }

    /**
     * Round value up to nearest 1000 (miles) per Excel ROUNDUP(,-3).
     */
    public function roundValueToThousands(float $value): float
    {
        return ceil($value / 1000) * 1000;
    }

    /**
     * Residual breakdown (informative).
     *
     * @return array{construction_value: float, equipment_value: float, land_value: float, land_unit_value: float}
     */
    public function residualBreakdown(
        float $marketValue,
        float $constructionUnitValue,
        float $areaConstructionM2,
        float $depreciationFactor,
        float $equipmentValue,
        float $areaLandM2,
    ): array {
        $constructionValue = ($constructionUnitValue * $areaConstructionM2) * (1 - $depreciationFactor);
        $landValue = $marketValue - $constructionValue - $equipmentValue;
        $landUnitValue = $areaLandM2 > 0 ? $landValue / $areaLandM2 : 0.0;

        return [
            'construction_value' => round($constructionValue, 2),
            'equipment_value' => round($equipmentValue, 2),
            'land_value' => round($landValue, 2),
            'land_unit_value' => round($landUnitValue, 2),
        ];
    }
}
