<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Domain\CareGap;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Measures\BloodPressureMeasure;

final class BloodPressureMeasureTest extends TestCase
{
    private function evaluate(...$args): array
    {
        return (new BloodPressureMeasure())->evaluate(Fixtures::record(...$args), Fixtures::today());
    }

    /** @return list<MeasureKey> */
    private function keys(array $gaps): array
    {
        return array_map(static fn (CareGap $gap): MeasureKey => $gap->measure, $gaps);
    }

    public function testControlledBpHasNoGap(): void
    {
        $gaps = $this->evaluate(age: 62, problems: [Fixtures::active('I10')], bp: [Fixtures::bp(20, 128, 82)]);

        self::assertSame([], $gaps);
    }

    public function testHighSystolicIsUncontrolled(): void
    {
        $gaps = $this->evaluate(age: 62, problems: [Fixtures::active('I10')], bp: [Fixtures::bp(5, 140, 70)]);

        self::assertSame([MeasureKey::BpUncontrolled], $this->keys($gaps));
        self::assertSame('Last BP 140/70 on Nov 11, 2026', $gaps[0]->detail);
    }

    public function testHighDiastolicIsUncontrolled(): void
    {
        $gaps = $this->evaluate(age: 62, problems: [Fixtures::active('I10')], bp: [Fixtures::bp(5, 120, 90)]);

        self::assertSame([MeasureKey::BpUncontrolled], $this->keys($gaps));
    }

    public function testLowestReadingsOfTheMostRecentDayAreUsed(): void
    {
        $gaps = $this->evaluate(
            age: 70,
            problems: [Fixtures::active('I10')],
            bp: [Fixtures::bp(3, 152, 96), Fixtures::bp(3, 146, 92), Fixtures::bp(30, 118, 76)],
        );

        self::assertSame([MeasureKey::BpUncontrolled], $this->keys($gaps));
        self::assertSame('Last BP 146/92 on Nov 13, 2026', $gaps[0]->detail);
    }

    public function testSameDayRepeatReadingCanControl(): void
    {
        $gaps = $this->evaluate(
            age: 70,
            problems: [Fixtures::active('I10')],
            bp: [Fixtures::bp(3, 150, 95), Fixtures::bp(3, 138, 88)],
        );

        self::assertSame([], $gaps);
    }

    public function testNoBpInTwelveMonthsIsMissing(): void
    {
        $gaps = $this->evaluate(age: 44, problems: [Fixtures::active('I10')], bp: [Fixtures::bp(400, 120, 80)]);

        self::assertSame([MeasureKey::BpMissing], $this->keys($gaps));
        self::assertSame('Last BP 120/80 on Oct 12, 2025', $gaps[0]->detail);
    }

    public function testNoBpEverIsMissing(): void
    {
        $gaps = $this->evaluate(age: 54, problems: [Fixtures::active('I10')]);

        self::assertSame([MeasureKey::BpMissing], $this->keys($gaps));
        self::assertSame('No BP on file', $gaps[0]->detail);
    }

    public function testInvalidReadingsAreIgnored(): void
    {
        $gaps = $this->evaluate(age: 54, problems: [Fixtures::active('I10')], bp: [Fixtures::bp(5, 0, 0)]);

        self::assertSame([MeasureKey::BpMissing], $this->keys($gaps));
    }

    public function testPatientsWithoutHypertensionOrOutsideAgeAreExcluded(): void
    {
        self::assertSame([], $this->evaluate(age: 54, problems: [Fixtures::active('E11.9')]));
        self::assertSame([], $this->evaluate(age: 54, problems: [Fixtures::resolved('I10')]));
        self::assertSame([], $this->evaluate(age: 17, problems: [Fixtures::active('I10')]));
        self::assertSame([], $this->evaluate(age: 86, problems: [Fixtures::active('I10')]));
    }
}
