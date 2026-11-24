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

/** One open gap for one patient, with the evidence staff need to act on it. */
final readonly class CareGap
{
    public function __construct(
        public MeasureKey $measure,
        public string $detail,
        public ?DateTimeImmutable $lastDate = null,
        public ?DateTimeImmutable $dueSince = null,
    ) {
    }
}
