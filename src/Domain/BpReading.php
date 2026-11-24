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

use DateTimeImmutable;

/** One blood pressure reading from the Vitals form. */
final readonly class BpReading
{
    public function __construct(
        public DateTimeImmutable $date,
        public int $systolic,
        public int $diastolic,
    ) {
    }

    public function isValid(): bool
    {
        return $this->systolic > 0 && $this->diastolic > 0;
    }
}
