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

/** A lab result; $value is null when the result has no usable number. */
final readonly class LabResult
{
    public function __construct(
        public DateTimeImmutable $date,
        public ?float $value,
    ) {
    }
}
