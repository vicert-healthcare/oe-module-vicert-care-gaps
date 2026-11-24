<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Domain\CareGap;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Measures\A1cMeasure;

final class A1cMeasureTest extends TestCase
{
    /** @return list<MeasureKey> */
    private function keys(array $gaps): array
    {
        return array_map(static fn (CareGap $gap): MeasureKey => $gap->measure, $gaps);
    }

    private function evaluate(...$args): array
    {
        return (new A1cMeasure())->evaluate(Fixtures::record(...$args), Fixtures::today());
    }

    public function testRecentNormalA1cHasNoGap(): void
    {
        $gaps = $this->evaluate(age: 65, problems: [Fixtures::active('E11.9')], a1c: [Fixtures::a1c(60, 6.8)]);

        self::assertSame([], $gaps);
    }

    public function testA1cOlderThanSixMonthsIsOverdueButNotPoorControl(): void
    {
        $gaps = $this->evaluate(age: 71, problems: [Fixtures::active('E11.9')], a1c: [Fixtures::a1c(270, 8.1)]);

        self::assertSame([MeasureKey::A1cOverdue], $this->keys($gaps));
        self::assertSame('Last A1C 8.1% on Feb 19, 2026', $gaps[0]->detail);
        self::assertSame('2026-08-19', $gaps[0]->dueSince?->format('Y-m-d'));
    }

    public function testNoA1cIsBothOverdueAndPoorControl(): void
    {
        $gaps = $this->evaluate(age: 35, problems: [Fixtures::active('E10.9')]);

        self::assertSame([MeasureKey::A1cOverdue, MeasureKey::A1cPoorControl], $this->keys($gaps));
        self::assertSame('No A1C on file', $gaps[0]->detail);
        self::assertNull($gaps[0]->dueSince);
        self::assertSame('No A1C in the last 12 months', $gaps[1]->detail);
    }

    public function testRecentA1cAboveNineIsPoorControlOnly(): void
    {
        $gaps = $this->evaluate(age: 50, problems: [Fixtures::active('E11.65')], a1c: [Fixtures::a1c(30, 9.6)]);

        self::assertSame([MeasureKey::A1cPoorControl], $this->keys($gaps));
        self::assertSame('Last A1C 9.6% on Oct 17, 2026', $gaps[0]->detail);
    }

    public function testExactlyNineIsNotPoorControl(): void
    {
        $gaps = $this->evaluate(age: 50, problems: [Fixtures::active('E11.9')], a1c: [Fixtures::a1c(30, 9.0)]);

        self::assertSame([], $gaps);
    }

    public function testLatestResultWinsOverOlderHighResult(): void
    {
        $gaps = $this->evaluate(
            age: 50,
            problems: [Fixtures::active('E11.9')],
            a1c: [Fixtures::a1c(200, 10.2), Fixtures::a1c(20, 7.1)],
        );

        self::assertSame([], $gaps);
    }

    public function testResultWithoutValueIsIgnored(): void
    {
        $gaps = $this->evaluate(age: 50, problems: [Fixtures::active('E11.9')], a1c: [Fixtures::a1c(20, null)]);

        self::assertSame([MeasureKey::A1cOverdue, MeasureKey::A1cPoorControl], $this->keys($gaps));
    }

    public function testA1cBetweenSixAndTwelveMonthsAboveNineIsBothGaps(): void
    {
        $gaps = $this->evaluate(age: 60, problems: [Fixtures::active('E11.9')], a1c: [Fixtures::a1c(250, 11.0)]);

        self::assertSame([MeasureKey::A1cOverdue, MeasureKey::A1cPoorControl], $this->keys($gaps));
    }

    /** @return iterable<string, array{int, list<\Vicert\CareGaps\Domain\Problem>}> */
    public static function excludedPatients(): iterable
    {
        yield 'not diabetic' => [54, [Fixtures::active('I10')]];
        yield 'diabetes resolved' => [58, [Fixtures::resolved('E11.9')]];
        yield 'too young' => [17, [Fixtures::active('E10.9')]];
        yield 'too old' => [76, [Fixtures::active('E11.9')]];
        yield 'gestational diabetes is not counted' => [30, [Fixtures::active('O24.4')]];
    }

    #[DataProvider('excludedPatients')]
    public function testExcludedPatientsHaveNoGap(int $age, array $problems): void
    {
        self::assertSame([], $this->evaluate(age: $age, problems: $problems));
    }

    public function testBoundaryAgesAreIncluded(): void
    {
        self::assertNotSame([], $this->evaluate(age: 18, problems: [Fixtures::active('E10.9')]));
        self::assertNotSame([], $this->evaluate(age: 75, problems: [Fixtures::active('E13.9')]));
    }
}
