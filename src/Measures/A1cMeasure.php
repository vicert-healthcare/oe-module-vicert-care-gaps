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
use Vicert\CareGaps\Domain\CareGap;
use Vicert\CareGaps\Domain\LabResult;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Support\DateMath;

/**
 * Diabetes A1C, two flags:
 * - Overdue: no A1C in the last 6 months (the same rule as Vicert's Canvas A1C plugin).
 * - Poor control: the latest A1C in the last 12 months is above 9%, or there is none (after CMS122).
 */
final class A1cMeasure implements Measure
{
    public const MIN_AGE = 18;
    public const MAX_AGE = 75;
    public const OVERDUE_MONTHS = 6;
    public const CONTROL_LOOKBACK_MONTHS = 12;
    public const POOR_CONTROL_ABOVE = 9.0;

    public function evaluate(PatientRecord $record, DateTimeImmutable $today): array
    {
        $age = DateMath::ageOn($record->patient->dob, $today);
        if ($age < self::MIN_AGE || $age > self::MAX_AGE || !$record->hasActiveProblem(MeasureCodes::DIABETES)) {
            return [];
        }

        $latest = $this->latestWithValue($record->a1cResults);
        return array_values(array_filter([
            $this->overdueGap($latest, $today),
            $this->poorControlGap($latest, $today),
        ]));
    }

    private function overdueGap(?LabResult $latest, DateTimeImmutable $today): ?CareGap
    {
        if ($latest === null) {
            return new CareGap(MeasureKey::A1cOverdue, 'No A1C on file');
        }
        if ($latest->date >= DateMath::monthsBefore($today, self::OVERDUE_MONTHS)) {
            return null;
        }
        return new CareGap(
            MeasureKey::A1cOverdue,
            $this->describe($latest),
            $latest->date,
            DateMath::addMonths($latest->date, self::OVERDUE_MONTHS),
        );
    }

    private function poorControlGap(?LabResult $latest, DateTimeImmutable $today): ?CareGap
    {
        $lookback = DateMath::monthsBefore($today, self::CONTROL_LOOKBACK_MONTHS);
        if ($latest === null || $latest->date < $lookback) {
            return new CareGap(MeasureKey::A1cPoorControl, 'No A1C in the last 12 months', $latest?->date);
        }
        if ($latest->value <= self::POOR_CONTROL_ABOVE) {
            return null;
        }
        return new CareGap(MeasureKey::A1cPoorControl, $this->describe($latest), $latest->date);
    }

    /** @param list<LabResult> $results */
    private function latestWithValue(array $results): ?LabResult
    {
        $latest = null;
        foreach ($results as $result) {
            if ($result->value !== null && ($latest === null || $result->date > $latest->date)) {
                $latest = $result;
            }
        }
        return $latest;
    }

    private function describe(LabResult $result): string
    {
        $value = rtrim(rtrim(number_format((float) $result->value, 1, '.', ''), '0'), '.');
        return sprintf('Last A1C %s%% on %s', $value, DateMath::display($result->date));
    }
}
