<?php

/**
 * Care Gaps Worklist for OpenEMR.
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Vicert\CareGaps\Measures;

use DateTimeImmutable;
use Vicert\CareGaps\Domain\BpReading;
use Vicert\CareGaps\Domain\CareGap;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Support\DateMath;

/**
 * Hypertension blood pressure control (after CMS165):
 * - Not controlled: on the most recent day with a reading, the lowest systolic is 140+ or the lowest
 *   diastolic is 90+ (a repeat reading the same day can bring the patient under control, as in CMS165).
 * - Not checked: no reading in the last 12 months.
 */
final class BloodPressureMeasure implements Measure
{
    public const MIN_AGE = 18;
    public const MAX_AGE = 85;
    public const LOOKBACK_MONTHS = 12;
    public const SYSTOLIC_LIMIT = 140;
    public const DIASTOLIC_LIMIT = 90;

    public function evaluate(PatientRecord $record, DateTimeImmutable $today): array
    {
        $age = DateMath::ageOn($record->patient->dob, $today);
        if ($age < self::MIN_AGE || $age > self::MAX_AGE || !$record->hasActiveProblem(MeasureCodes::HYPERTENSION)) {
            return [];
        }

        $valid = array_values(array_filter($record->bpReadings, static fn (BpReading $r): bool => $r->isValid()));
        $latestDay = $this->latestDay($valid);
        if ($latestDay === null || $latestDay->date < DateMath::monthsBefore($today, self::LOOKBACK_MONTHS)) {
            $detail = $latestDay === null ? 'No BP on file' : $this->describe($latestDay);
            return [new CareGap(MeasureKey::BpMissing, $detail, $latestDay?->date)];
        }

        if ($latestDay->systolic < self::SYSTOLIC_LIMIT && $latestDay->diastolic < self::DIASTOLIC_LIMIT) {
            return [];
        }
        return [new CareGap(MeasureKey::BpUncontrolled, $this->describe($latestDay), $latestDay->date)];
    }

    /**
     * The most recent day's reading, using that day's lowest systolic and lowest diastolic.
     *
     * @param list<BpReading> $readings
     */
    private function latestDay(array $readings): ?BpReading
    {
        if ($readings === []) {
            return null;
        }
        $day = max(array_map(static fn (BpReading $r): string => $r->date->format('Y-m-d'), $readings));
        $sameDay = array_filter($readings, static fn (BpReading $r): bool => $r->date->format('Y-m-d') === $day);
        return new BpReading(
            new DateTimeImmutable($day),
            min(array_map(static fn (BpReading $r): int => $r->systolic, $sameDay)),
            min(array_map(static fn (BpReading $r): int => $r->diastolic, $sameDay)),
        );
    }

    private function describe(BpReading $reading): string
    {
        return sprintf('Last BP %d/%d on %s', $reading->systolic, $reading->diastolic, DateMath::display($reading->date));
    }
}
