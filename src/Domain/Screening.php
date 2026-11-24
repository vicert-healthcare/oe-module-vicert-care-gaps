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

/** A colorectal cancer screening test the patient had, from labs, orders, billing or surgery history. */
final readonly class Screening
{
    public function __construct(
        public ScreeningType $type,
        public DateTimeImmutable $date,
    ) {
    }

    /** The first day this screening no longer counts. */
    public function expiresOn(): DateTimeImmutable
    {
        return $this->date->modify('+' . $this->type->validityYears() . ' years');
    }
}
