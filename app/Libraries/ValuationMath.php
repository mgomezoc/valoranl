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
        $level = max(1, min(9, $conservationLevel));

        return (float) ($this->config->conservationMultipliers[$level] ?? 1.0);
    }

    public function ageMultiplier(int $ageYears, ?int $usefulLifeYears = null): float
    {
        $lifeYears = max(1, $usefulLifeYears ?? $this->config->usefulLifeYears);
        $age = max(0, $ageYears);
        $depreciation = ($age / $lifeYears) * $this->config->depreciationImpact;

        return round(max(0.0, 1 - $depreciation), 4);
    }

    public function rossHeideckeFactor(int $ageYears, int $conservationLevel, ?int $usefulLifeYears = null): float
    {
        $ageFactor = $this->ageMultiplier($ageYears, $usefulLifeYears);
        $conservationFactor = $this->conservationMultiplier($conservationLevel);

        return round($ageFactor * $conservationFactor, 4);
    }

    public function negotiationFactor(): float
    {
        return $this->config->negotiationFactor;
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
}
