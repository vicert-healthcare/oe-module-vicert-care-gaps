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

namespace Vicert\CareGaps\Domain;

/** Everything the measures need to know about one patient, read once from the database. */
final readonly class PatientRecord
{
    /**
     * @param list<Problem>   $problems
     * @param list<LabResult> $a1cResults
     * @param list<BpReading> $bpReadings
     * @param list<Screening> $screenings
     */
    public function __construct(
        public Patient $patient,
        public array $problems,
        public array $a1cResults,
        public array $bpReadings,
        public array $screenings,
    ) {
    }

    /** @param list<string> $prefixes ICD-10 code prefixes, such as "E11" */
    public function hasActiveProblem(array $prefixes): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->active && $problem->matches($prefixes)) {
                return true;
            }
        }
        return false;
    }

    /** Active or resolved: used for history-based exclusions such as past colorectal cancer. */
    public function hasAnyProblem(array $prefixes): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->matches($prefixes)) {
                return true;
            }
        }
        return false;
    }
}
