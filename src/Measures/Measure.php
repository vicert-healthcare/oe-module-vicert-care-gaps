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
use Vicert\CareGaps\Domain\PatientRecord;

/** A care-gap rule. Pure: it reads only the record it is given, so it can be tested without OpenEMR. */
interface Measure
{
    /** @return list<CareGap> the open gaps for this patient; empty when none apply */
    public function evaluate(PatientRecord $record, DateTimeImmutable $today): array;
}
