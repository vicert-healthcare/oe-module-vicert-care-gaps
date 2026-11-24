<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use DateTimeImmutable;
use Vicert\CareGaps\Domain\BpReading;
use Vicert\CareGaps\Domain\LabResult;
use Vicert\CareGaps\Domain\Patient;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Domain\Problem;
use Vicert\CareGaps\Domain\Screening;
use Vicert\CareGaps\Domain\ScreeningType;

/** Builders for fictional test patients. "Today" is fixed so every rule is deterministic. */
final class Fixtures
{
    public const TODAY = '2026-11-16';

    public static function today(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::TODAY);
    }

    public static function daysAgo(int $days): DateTimeImmutable
    {
        return self::today()->modify("-{$days} days");
    }

    /** A date of birth that makes the patient exactly $age years old today. */
    public static function dobForAge(int $age): DateTimeImmutable
    {
        return self::today()->modify("-{$age} years")->modify('-10 days');
    }

    /**
     * @param list<Problem>    $problems
     * @param list<LabResult>  $a1c
     * @param list<BpReading>  $bp
     * @param list<Screening>  $screenings
     */
    public static function record(
        int $age,
        array $problems = [],
        array $a1c = [],
        array $bp = [],
        array $screenings = [],
        int $pid = 1,
        ?int $providerId = 7,
    ): PatientRecord {
        $patient = new Patient($pid, 'Test', 'Patient', self::dobForAge($age), $providerId, 'Dr Test');
        return new PatientRecord($patient, $problems, $a1c, $bp, $screenings);
    }

    public static function active(string $code): Problem
    {
        return new Problem($code, true);
    }

    public static function resolved(string $code): Problem
    {
        return new Problem($code, false);
    }

    public static function a1c(int $daysAgo, ?float $value): LabResult
    {
        return new LabResult(self::daysAgo($daysAgo), $value);
    }

    public static function bp(int $daysAgo, int $systolic, int $diastolic): BpReading
    {
        return new BpReading(self::daysAgo($daysAgo), $systolic, $diastolic);
    }

    public static function screening(ScreeningType $type, int $daysAgo): Screening
    {
        return new Screening($type, self::daysAgo($daysAgo));
    }
}
