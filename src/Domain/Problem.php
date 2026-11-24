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

/** One ICD-10 code from the patient's problem list. */
final readonly class Problem
{
    public function __construct(
        public string $code,
        public bool $active,
    ) {
    }

    /** @param list<string> $prefixes */
    public function matches(array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($this->code, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
