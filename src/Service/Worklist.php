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

use Vicert\CareGaps\Domain\MeasureKey;

/** The open gaps across all patients. Immutable: filtering returns a new worklist. */
final readonly class Worklist
{
    /** @param list<WorklistRow> $rows */
    public function __construct(public array $rows)
    {
    }

    /** @return array<string, int> number of patients per measure key, including measures with none */
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(static fn (MeasureKey $k): string => $k->value, MeasureKey::cases()), 0);
        foreach ($this->rows as $row) {
            $counts[$row->gap->measure->value]++;
        }
        return $counts;
    }

    public function patientCount(): int
    {
        return count(array_unique(array_map(static fn (WorklistRow $row): int => $row->patient->pid, $this->rows)));
    }

    /** @return array<int, string> provider id => name, for the provider filter */
    public function providers(): array
    {
        $providers = [];
        foreach ($this->rows as $row) {
            if ($row->patient->providerId !== null) {
                $providers[$row->patient->providerId] = $row->patient->providerName;
            }
        }
        asort($providers);
        return $providers;
    }

    public function filter(WorklistFilter $filter): self
    {
        $search = mb_strtolower($filter->search);
        return new self(array_values(array_filter(
            $this->rows,
            static fn (WorklistRow $row): bool =>
                ($filter->measure === null || $row->gap->measure === $filter->measure)
                && ($filter->providerId === null || $row->patient->providerId === $filter->providerId)
                && ($search === '' || str_contains(mb_strtolower($row->patient->fullName()), $search)),
        )));
    }
}
