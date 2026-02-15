<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Valuation extends BaseConfig
{
    /**
     * Multiplicadores Ross-Heidecke simplificados por nivel de conservación (1 a 9).
     * 9 = excelente, 1 = muy malo.
     *
     * @var array<int, float>
     */
    public array $conservationMultipliers = [
        1 => 0.4500,
        2 => 0.5300,
        3 => 0.6100,
        4 => 0.6900,
        5 => 0.7400,
        6 => 0.7700,
        7 => 0.7871,
        8 => 0.9191,
        9 => 1.0000,
    ];

    /**
     * Inferencia de conservación por antigüedad (años).
     *
     * @var array<int, int>
     */
    public array $conservationInferenceByAge = [
        4 => 9,
        8 => 8,
        15 => 7,
        25 => 6,
        35 => 5,
        50 => 4,
    ];

    public float $depreciationImpact = 0.25;

    public int $usefulLifeYears = 60;

    public float $negotiationFactor = 0.95;
}
