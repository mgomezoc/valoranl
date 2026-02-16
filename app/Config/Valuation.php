<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Valuation extends BaseConfig
{
    /**
     * Multiplicadores Ross-Heidecke por nivel de conservación (1 a 10).
     * Escala alineada al Excel de referencia.
     * 10 = nuevo, 1 = ruina.
     *
     * @var array<int, float>
     */
    public array $conservationMultipliers = [
        1  => 0.0000,
        2  => 0.1350,
        3  => 0.2480,
        4  => 0.4740,
        5  => 0.6680,
        6  => 0.8190,
        7  => 0.9191,
        8  => 0.9748,
        9  => 0.9968,
        10 => 1.0000,
    ];

    /**
     * Inferencia de conservación por antigüedad (años).
     *
     * @var array<int, int>
     */
    public array $conservationInferenceByAge = [
        0  => 10,
        4  => 9,
        8  => 8,
        15 => 7,
        25 => 6,
        35 => 5,
        50 => 4,
    ];

    /**
     * Exponente para la fórmula Ross-Heidecke: (1 - (edad/vida)^exp) × conservación.
     */
    public float $rossHeideckeExponent = 1.4;

    public int $usefulLifeYears = 60;

    public float $negotiationFactor = 0.95;

    /**
     * Factor de ubicación por comparable (ajuste posición dentro de zona).
     */
    public float $locationFactor = 0.95;

    /**
     * Factor de zona por comparable (ajuste zona/ubicación relativa).
     */
    public float $zoneFactor = 1.0;

    /**
     * Spread para rangos: valor ± rangeSpread (10% = Excel).
     */
    public float $rangeSpread = 0.10;
}
