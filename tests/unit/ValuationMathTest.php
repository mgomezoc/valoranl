<?php

use App\Libraries\ValuationMath;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ValuationMathTest extends CIUnitTestCase
{
    private ValuationMath $valuationMath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->valuationMath = new ValuationMath();
    }

    // --- Conservation multiplier (scale 1-10) ---

    public function testConservationMultiplierLevel7(): void
    {
        $this->assertSame(0.9191, $this->valuationMath->conservationMultiplier(7));
    }

    public function testConservationMultiplierLevel10New(): void
    {
        $this->assertSame(1.0, $this->valuationMath->conservationMultiplier(10));
    }

    public function testConservationMultiplierLevel1Ruin(): void
    {
        $this->assertSame(0.0, $this->valuationMath->conservationMultiplier(1));
    }

    public function testConservationMultiplierClampsAbove10(): void
    {
        $this->assertSame(1.0, $this->valuationMath->conservationMultiplier(15));
    }

    // --- Age multiplier (exponential Ross-Heidecke) ---

    public function testAgeMultiplierExponential(): void
    {
        // 1 - (15/60)^1.4 = 1 - 0.25^1.4 ≈ 0.8563
        $result = $this->valuationMath->ageMultiplier(15, 60);
        $this->assertEqualsWithDelta(0.8563, $result, 0.001);
    }

    public function testAgeMultiplierZeroAge(): void
    {
        // 1 - (0/60)^1.4 = 1.0
        $this->assertSame(1.0, $this->valuationMath->ageMultiplier(0, 60));
    }

    public function testAgeMultiplierFullLife(): void
    {
        // 1 - (60/60)^1.4 = 0.0
        $this->assertSame(0.0, $this->valuationMath->ageMultiplier(60, 60));
    }

    public function testAgeMultiplierClampsBeyondUsefulLife(): void
    {
        // age > usefulLife should be clamped
        $this->assertSame(0.0, $this->valuationMath->ageMultiplier(80, 60));
    }

    // --- Ross-Heidecke factor ---

    public function testRossHeideckeFactorExcelCase(): void
    {
        // age=15, conservation=8 → 0.8563 × 0.9748 ≈ 0.8347
        $result = $this->valuationMath->rossHeideckeFactor(15, 8, 60);
        $this->assertEqualsWithDelta(0.8347, $result, 0.002);
    }

    public function testRossHeideckeFactorConservation7(): void
    {
        // age=15, conservation=7 → 0.8563 × 0.9191 ≈ 0.7870
        $result = $this->valuationMath->rossHeideckeFactor(15, 7, 60);
        $this->assertEqualsWithDelta(0.7870, $result, 0.002);
    }

    // --- Depreciation ---

    public function testDepreciationIsOneMinusRossHeidecke(): void
    {
        $rh = $this->valuationMath->rossHeideckeFactor(15, 8, 60);
        $depr = $this->valuationMath->depreciation(15, 8, 60);
        $this->assertEqualsWithDelta(1.0 - $rh, $depr, 0.0001);
    }

    // --- Negotiation factor ---

    public function testNegotiationFactor(): void
    {
        $this->assertSame(0.95, $this->valuationMath->negotiationFactor());
    }

    // --- Location and zone factors ---

    public function testLocationFactor(): void
    {
        $this->assertSame(0.95, $this->valuationMath->locationFactor());
    }

    public function testZoneFactor(): void
    {
        $this->assertSame(1.0, $this->valuationMath->zoneFactor());
    }

    // --- Conservation inference by age ---

    public function testConservationInferenceByAge(): void
    {
        $this->assertSame(10, $this->valuationMath->inferConservationLevel(0));
        $this->assertSame(9, $this->valuationMath->inferConservationLevel(4));
        $this->assertSame(8, $this->valuationMath->inferConservationLevel(8));
        $this->assertSame(7, $this->valuationMath->inferConservationLevel(15));
        $this->assertSame(6, $this->valuationMath->inferConservationLevel(25));
        $this->assertSame(5, $this->valuationMath->inferConservationLevel(35));
        $this->assertSame(4, $this->valuationMath->inferConservationLevel(50));
        $this->assertSame(4, $this->valuationMath->inferConservationLevel(70));
    }

    // --- Surface homologation factor ---

    public function testSurfaceHomologationFactor(): void
    {
        // ((67/112) / (146/90))^(1/3) ≈ (0.5982 / 1.6222)^(1/3) ≈ 0.3688^(1/3) ≈ 0.7171
        $result = $this->valuationMath->surfaceHomologationFactor(67, 112, 146, 90);
        $this->assertEqualsWithDelta(0.7171, $result, 0.002);
    }

    public function testSurfaceHomologationFactorEqualRatios(): void
    {
        // Equal ratios → 1.0
        $result = $this->valuationMath->surfaceHomologationFactor(100, 200, 100, 200);
        $this->assertSame(1.0, $result);
    }

    public function testSurfaceHomologationFactorZeroLandReturnsOne(): void
    {
        $this->assertSame(1.0, $this->valuationMath->surfaceHomologationFactor(100, 0, 100, 90));
    }

    // --- Age homologation factor ---

    public function testAgeHomologationFactor(): void
    {
        // 1 - (0.20 - 0.10) = 0.9
        $this->assertSame(0.9, $this->valuationMath->ageHomologationFactor(0.20, 0.10));
    }

    public function testAgeHomologationFactorEqualDepreciation(): void
    {
        $this->assertSame(1.0, $this->valuationMath->ageHomologationFactor(0.15, 0.15));
    }

    // --- Rounding ---

    public function testRoundPpuToDecenas(): void
    {
        $this->assertSame(12260.0, $this->valuationMath->roundPpu(12262.0));
        $this->assertSame(12270.0, $this->valuationMath->roundPpu(12265.0));
        $this->assertSame(15000.0, $this->valuationMath->roundPpu(15000.0));
    }

    public function testRoundValueToThousands(): void
    {
        // ROUNDUP to nearest 1000
        $this->assertSame(1790000.0, $this->valuationMath->roundValueToThousands(1789960.0));
        $this->assertSame(2000000.0, $this->valuationMath->roundValueToThousands(2000000.0));
        $this->assertSame(1001000.0, $this->valuationMath->roundValueToThousands(1000001.0));
    }

    // --- Residual breakdown ---

    public function testResidualBreakdown(): void
    {
        $result = $this->valuationMath->residualBreakdown(
            marketValue: 1790000.0,
            constructionUnitValue: 7500.0,
            areaConstructionM2: 146.0,
            depreciationFactor: 0.2130,
            equipmentValue: 20000.0,
            areaLandM2: 90.0,
        );

        $this->assertArrayHasKey('construction_value', $result);
        $this->assertArrayHasKey('equipment_value', $result);
        $this->assertArrayHasKey('land_value', $result);
        $this->assertArrayHasKey('land_unit_value', $result);

        // V.Construcciones = (7500 × 146) × (1 - 0.2130) = 1,095,000 × 0.787 = 861,765
        $this->assertEqualsWithDelta(861765.0, $result['construction_value'], 100);
        $this->assertEqualsWithDelta(20000.0, $result['equipment_value'], 0.01);

        // V.Terreno = 1,790,000 - 861,765 - 20,000 = 908,235
        $this->assertEqualsWithDelta(908235.0, $result['land_value'], 100);

        // V.U. Terreno = 908,235 / 90 ≈ 10,091
        $this->assertEqualsWithDelta(10091.5, $result['land_unit_value'], 10);
    }
}
