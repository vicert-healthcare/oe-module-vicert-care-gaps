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

use Vicert\CareGaps\Domain\CareGap;
use Vicert\CareGaps\Domain\Patient;

/** One line of the worklist: a patient and one of their open gaps. */
final readonly class WorklistRow
{
    public function __construct(
        public Patient $patient,
        public int $age,
        public CareGap $gap,
    ) {
    }
}
