<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Domain\ScreeningType;
use Vicert\CareGaps\Measures\ColorectalScreeningMeasure;

final class ColorectalScreeningMeasureTest extends TestCase
{
    private function evaluate(...$args): array
    {
        return (new ColorectalScreeningMeasure())->evaluate(Fixtures::record(...$args), Fixtures::today());
    }

    public function testNoScreeningIsDue(): void
    {
        $gaps = $this->evaluate(age: 65);

        self::assertCount(1, $gaps);
        self::assertSame(MeasureKey::ColorectalScreeningDue, $gaps[0]->measure);
        self::assertSame('No screening on file', $gaps[0]->detail);
        self::assertNull($gaps[0]->dueSince);
    }

    /** @return iterable<string, array{ScreeningType, int}> */
    public static function validScreenings(): iterable
    {
        yield 'colonoscopy 3 years ago' => [ScreeningType::Colonoscopy, 3 * 365];
        yield 'colonoscopy 9 years ago' => [ScreeningType::Colonoscopy, 9 * 365];
        yield 'FIT 4 months ago' => [ScreeningType::Fobt, 120];
        yield 'stool DNA 2 years ago' => [ScreeningType::FitDna, 2 * 365];
        yield 'CT colonography 4 years ago' => [ScreeningType::CtColonography, 4 * 365];
        yield 'flexible sigmoidoscopy 4 years ago' => [ScreeningType::FlexibleSigmoidoscopy, 4 * 365];
    }

    #[DataProvider('validScreenings')]
    public function testValidScreeningClosesTheGap(ScreeningType $type, int $daysAgo): void
    {
        self::assertSame([], $this->evaluate(age: 60, screenings: [Fixtures::screening($type, $daysAgo)]));
    }

    public function testExpiredColonoscopyIsDueWithExpiryDate(): void
    {
        $gaps = $this->evaluate(age: 70, screenings: [Fixtures::screening(ScreeningType::Colonoscopy, 12 * 365)]);

        self::assertCount(1, $gaps);
        self::assertSame('Last colonoscopy Nov 19, 2014 (expired)', $gaps[0]->detail);
        self::assertSame('2024-11-19', $gaps[0]->dueSince?->format('Y-m-d'));
    }

    public function testExpiredFitIsDue(): void
    {
        $gaps = $this->evaluate(age: 47, screenings: [Fixtures::screening(ScreeningType::Fobt, 450)]);

        self::assertCount(1, $gaps);
        self::assertSame('Last FIT/FOBT Aug 23, 2025 (expired)', $gaps[0]->detail);
    }

    public function testMostRecentScreeningIsReportedWhenAllExpired(): void
    {
        $gaps = $this->evaluate(
            age: 66,
            screenings: [
                Fixtures::screening(ScreeningType::Colonoscopy, 15 * 365),
                Fixtures::screening(ScreeningType::Fobt, 500),
            ],
        );

        self::assertStringStartsWith('Last FIT/FOBT', $gaps[0]->detail);
    }

    public function testOneValidScreeningIsEnoughEvenIfAnotherExpired(): void
    {
        $gaps = $this->evaluate(
            age: 66,
            screenings: [
                Fixtures::screening(ScreeningType::Fobt, 500),
                Fixtures::screening(ScreeningType::Colonoscopy, 2 * 365),
            ],
        );

        self::assertSame([], $gaps);
    }

    public function testAgeBoundaries(): void
    {
        self::assertSame([], $this->evaluate(age: 44));
        self::assertCount(1, $this->evaluate(age: 45));
        self::assertCount(1, $this->evaluate(age: 75));
        self::assertSame([], $this->evaluate(age: 76));
    }

    public function testColorectalCancerHistoryExcludesEvenWhenResolved(): void
    {
        self::assertSame([], $this->evaluate(age: 60, problems: [Fixtures::resolved('C18.9')]));
        self::assertSame([], $this->evaluate(age: 60, problems: [Fixtures::active('C20')]));
    }

    public function testPersonalHistoryCodesAndColectomyExclude(): void
    {
        self::assertSame([], $this->evaluate(age: 60, problems: [Fixtures::active('Z85.038')]));
        self::assertSame([], $this->evaluate(age: 60, problems: [Fixtures::active('Z85.048')]));
        self::assertSame([], $this->evaluate(age: 60, problems: [Fixtures::active('Z90.49')]));
        self::assertCount(1, $this->evaluate(age: 60, problems: [Fixtures::active('Z85.3')]), 'breast cancer history does not exclude');
    }
}
