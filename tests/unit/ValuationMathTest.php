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

    public function testConservationMultiplierMatchesSpecification(): void
    {
        $this->assertSame(0.7871, $this->valuationMath->conservationMultiplier(7));
    }

    public function testAgeMultiplierMatchesSpecification(): void
    {
        $this->assertSame(0.9375, $this->valuationMath->ageMultiplier(15, 60));
    }

    public function testRossHeideckeFactorMatchesSpecification(): void
    {
        $expected = round(0.9375 * 0.9191, 4);

        $this->assertSame($expected, $this->valuationMath->rossHeideckeFactor(15, 8, 60));
        $this->assertSame(0.8617, $this->valuationMath->rossHeideckeFactor(15, 8, 60));
    }

    public function testNegotiationFactorMatchesSpecification(): void
    {
        $this->assertSame(0.95, $this->valuationMath->negotiationFactor());
    }

    public function testConservationInferenceByAgeMatchesSpecification(): void
    {
        $this->assertSame(9, $this->valuationMath->inferConservationLevel(4));
        $this->assertSame(8, $this->valuationMath->inferConservationLevel(8));
        $this->assertSame(7, $this->valuationMath->inferConservationLevel(15));
        $this->assertSame(6, $this->valuationMath->inferConservationLevel(25));
        $this->assertSame(5, $this->valuationMath->inferConservationLevel(35));
        $this->assertSame(4, $this->valuationMath->inferConservationLevel(50));
    }
}
