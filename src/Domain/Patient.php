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

/** The demographic facts the worklist shows for one patient. */
final readonly class Patient
{
    public function __construct(
        public int $pid,
        public string $firstName,
        public string $lastName,
        public DateTimeImmutable $dob,
        public ?int $providerId,
        public string $providerName,
        public string $phone = '',
    ) {
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
