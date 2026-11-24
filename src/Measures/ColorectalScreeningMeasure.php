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
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Domain\Screening;
use Vicert\CareGaps\Support\DateMath;

/**
 * Colorectal cancer screening (after CMS130): patients 45-75 without colorectal cancer or a colectomy need a
 * screening that is still within its valid period (colonoscopy 10 years down to FIT/FOBT 1 year).
 */
final class ColorectalScreeningMeasure implements Measure
{
    public const MIN_AGE = 45;
    public const MAX_AGE = 75;

    public function evaluate(PatientRecord $record, DateTimeImmutable $today): array
    {
        $age = DateMath::ageOn($record->patient->dob, $today);
        if ($age < self::MIN_AGE || $age > self::MAX_AGE || $record->hasAnyProblem(MeasureCodes::COLORECTAL_EXCLUSIONS)) {
            return [];
        }

        foreach ($record->screenings as $screening) {
            if ($screening->expiresOn() > $today) {
                return [];
            }
        }

        $latest = $this->latest($record->screenings);
        if ($latest === null) {
            return [new CareGap(MeasureKey::ColorectalScreeningDue, 'No screening on file')];
        }
        return [new CareGap(
            MeasureKey::ColorectalScreeningDue,
            sprintf('Last %s %s (expired)', $latest->type->label(), DateMath::display($latest->date)),
            $latest->date,
            $latest->expiresOn(),
        )];
    }

    /** @param list<Screening> $screenings */
    private function latest(array $screenings): ?Screening
    {
        $latest = null;
        foreach ($screenings as $screening) {
            if ($latest === null || $screening->date > $latest->date) {
                $latest = $screening;
            }
        }
        return $latest;
    }
}
