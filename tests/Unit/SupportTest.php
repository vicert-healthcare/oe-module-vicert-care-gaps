<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Domain\ScreeningType;
use Vicert\CareGaps\Measures\MeasureCodes;
use Vicert\CareGaps\Support\CodeParser;
use Vicert\CareGaps\Support\CsvEscaper;
use Vicert\CareGaps\Support\DateMath;

final class SupportTest extends TestCase
{
    public function testMonthsBeforeClampsToEndOfMonth(): void
    {
        self::assertSame('2026-02-28', DateMath::monthsBefore(new DateTimeImmutable('2026-08-31'), 6)->format('Y-m-d'));
        self::assertSame('2026-05-16', DateMath::monthsBefore(new DateTimeImmutable('2026-11-16'), 6)->format('Y-m-d'));
        self::assertSame('2025-11-16', DateMath::monthsBefore(new DateTimeImmutable('2026-11-16'), 12)->format('Y-m-d'));
    }

    public function testAddMonthsClampsToEndOfMonth(): void
    {
        self::assertSame('2026-02-28', DateMath::addMonths(new DateTimeImmutable('2025-08-31'), 6)->format('Y-m-d'));
    }

    public function testAgeCountsOnlyCompletedBirthdays(): void
    {
        $today = new DateTimeImmutable('2026-11-16');
        self::assertSame(44, DateMath::ageOn(new DateTimeImmutable('1981-11-17'), $today));
        self::assertSame(45, DateMath::ageOn(new DateTimeImmutable('1981-11-16'), $today));
    }

    public function testIcd10CodesAreParsedFromOpenEmrDiagnosisField(): void
    {
        self::assertSame(['E11.9', 'I10'], CodeParser::icd10Codes('ICD10:E11.9;ICD10:I10'));
        self::assertSame(['E11.65'], CodeParser::icd10Codes('ICD9:250.00; ICD10:e11.65'));
        self::assertSame([], CodeParser::icd10Codes(''));
        self::assertSame([], CodeParser::icd10Codes('SNOMED-CT:44054006'));
    }

    /** @return iterable<string, array{string, ?float}> */
    public static function numbers(): iterable
    {
        yield 'plain' => ['6.8', 6.8];
        yield 'with unit' => ['9.6 %', 9.6];
        yield 'greater than' => ['>14', 14.0];
        yield 'comma decimal' => ['7,2', 7.2];
        yield 'empty' => ['', null];
        yield 'text' => ['see note', null];
    }

    #[DataProvider('numbers')]
    public function testParseNumber(string $raw, ?float $expected): void
    {
        self::assertSame($expected, CodeParser::parseNumber($raw));
    }

    public function testParseDateRejectsEmptyAndZeroDates(): void
    {
        self::assertSame('2026-05-01', CodeParser::parseDate('2026-05-01 12:00:00')?->format('Y-m-d'));
        self::assertNull(CodeParser::parseDate('0000-00-00 00:00:00'));
        self::assertNull(CodeParser::parseDate(null));
        self::assertNull(CodeParser::parseDate('not a date'));
    }

    /** @return iterable<string, array{string, ?ScreeningType}> */
    public static function screeningCodes(): iterable
    {
        yield 'colonoscopy CPT' => ['45378', ScreeningType::Colonoscopy];
        yield 'colonoscopy HCPCS' => ['G0121', ScreeningType::Colonoscopy];
        yield 'CT colonography' => ['74263', ScreeningType::CtColonography];
        yield 'flex sig' => ['45330', ScreeningType::FlexibleSigmoidoscopy];
        yield 'stool DNA LOINC' => ['77353-1', ScreeningType::FitDna];
        yield 'stool DNA CPT' => ['81528', ScreeningType::FitDna];
        yield 'FIT LOINC' => ['29771-3', ScreeningType::Fobt];
        yield 'FOBT CPT' => ['82274', ScreeningType::Fobt];
        yield 'lowercase hcpcs' => ['g0105', ScreeningType::Colonoscopy];
        yield 'A1C is not screening' => ['4548-4', null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('screeningCodes')]
    public function testScreeningTypeForCode(string $code, ?ScreeningType $expected): void
    {
        self::assertSame($expected, MeasureCodes::screeningTypeForCode($code));
    }

    public function testScreeningTypeForSurgeryTitle(): void
    {
        self::assertSame(ScreeningType::Colonoscopy, MeasureCodes::screeningTypeForTitle('Screening Colonoscopy'));
        self::assertSame(ScreeningType::FlexibleSigmoidoscopy, MeasureCodes::screeningTypeForTitle('flexible sigmoidoscopy'));
        self::assertNull(MeasureCodes::screeningTypeForTitle('Appendectomy'));
    }

    public function testVirtualColonoscopyIsCtColonography(): void
    {
        self::assertSame(ScreeningType::CtColonography, MeasureCodes::screeningTypeForTitle('Virtual colonoscopy'));
    }

    /** @return iterable<string, array{string}> */
    public static function notDoneTitles(): iterable
    {
        yield 'refused' => ['Colonoscopy refused'];
        yield 'declined' => ['Patient declined colonoscopy'];
        yield 'scheduled' => ['Colonoscopy scheduled'];
        yield 'planned' => ['Planned colonoscopy'];
        yield 'negated' => ['No colonoscopy'];
        yield 'cancelled' => ['Colonoscopy cancelled'];
    }

    #[DataProvider('notDoneTitles')]
    public function testTitlesSayingTheTestWasNotDoneDoNotCount(string $title): void
    {
        self::assertNull(MeasureCodes::screeningTypeForTitle($title));
    }

    /** @return iterable<string, array{string, string}> */
    public static function phones(): iterable
    {
        yield 'international' => ['+1 555 0100', '+1 555 0100'];
        yield 'formatted' => ['(555) 010-0100', '(555) 010-0100'];
        yield 'formula attempt' => ['=cmd|calc', ''];
        yield 'text mixed in' => ['555-0100 ext=2', '555-0100 2'];
    }

    public function testCompletedTestWithNegativeFindingStillCounts(): void
    {
        self::assertSame(ScreeningType::Colonoscopy, MeasureCodes::screeningTypeForTitle('Colonoscopy, no polyps'));
    }

    #[DataProvider('phones')]
    public function testPhoneKeepsOnlyPhoneCharacters(string $raw, string $expected): void
    {
        self::assertSame($expected, CsvEscaper::phone($raw));
    }

    public function testCsvCellsThatLookLikeFormulasAreNeutralised(): void
    {
        self::assertSame("'=HYPERLINK(\"x\")", CsvEscaper::cell('=HYPERLINK("x")'));
        self::assertSame("'+1", CsvEscaper::cell('+1'));
        self::assertSame("'-2", CsvEscaper::cell('-2'));
        self::assertSame("'@cmd", CsvEscaper::cell('@cmd'));
        self::assertSame('Bob Overdue', CsvEscaper::cell('Bob Overdue'));
    }
}
