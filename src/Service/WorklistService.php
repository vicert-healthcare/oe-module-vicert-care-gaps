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

namespace Vicert\CareGaps\Service;

use DateTimeImmutable;
use Vicert\CareGaps\Domain\PatientRecord;
use Vicert\CareGaps\Measures\A1cMeasure;
use Vicert\CareGaps\Measures\BloodPressureMeasure;
use Vicert\CareGaps\Measures\ColorectalScreeningMeasure;
use Vicert\CareGaps\Measures\Measure;
use Vicert\CareGaps\Support\DateMath;

/** Runs every measure over every patient record and returns the sorted worklist. */
final readonly class WorklistService
{
    /** @param list<Measure> $measures */
    public function __construct(private array $measures)
    {
    }

    public static function withDefaultMeasures(): self
    {
        return new self([new A1cMeasure(), new BloodPressureMeasure(), new ColorectalScreeningMeasure()]);
    }

    /** @param list<PatientRecord> $records */
    public function build(array $records, DateTimeImmutable $today): Worklist
    {
        $rows = [];
        foreach ($records as $record) {
            $age = DateMath::ageOn($record->patient->dob, $today);
            foreach ($this->measures as $measure) {
                foreach ($measure->evaluate($record, $today) as $gap) {
                    $rows[] = new WorklistRow($record->patient, $age, $gap);
                }
            }
        }

        usort($rows, static fn (WorklistRow $a, WorklistRow $b): int =>
            [$a->gap->measure->order(), $a->patient->lastName, $a->patient->firstName, $a->patient->pid]
            <=> [$b->gap->measure->order(), $b->patient->lastName, $b->patient->firstName, $b->patient->pid]);

        return new Worklist($rows);
    }
}
