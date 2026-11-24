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

namespace Vicert\CareGaps\Repository;

use Vicert\CareGaps\Measures\MeasureCodes;
use Vicert\CareGaps\Support\CodeParser;

/** Shapes raw query rows into the columns RecordAssembler expects. Pure, so it is unit tested. */
final class RowNormalizer
{
    /**
     * The first column (in order of preference) holding a real date, as stored; '' when none does.
     *
     * @param list<string> $columns
     */
    public static function firstDate(array $row, array $columns): string
    {
        foreach ($columns as $column) {
            $value = is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
            if (CodeParser::parseDate($value) !== null) {
                return $value;
            }
        }
        return '';
    }

    /**
     * A lab or procedure-order row: the result's own code wins, the ordered code is the fallback.
     *
     * @param list<string> $dateColumns
     * @return array{pid: string, date: string, code: string, title: string, result: string, units: string}
     */
    public static function labRow(array $row, array $dateColumns): array
    {
        $resultCode = trim((string) ($row['result_code'] ?? ''));
        return [
            'pid' => (string) ($row['pid'] ?? ''),
            'date' => self::firstDate($row, $dateColumns),
            'code' => $resultCode !== '' ? $resultCode : trim((string) ($row['order_code'] ?? '')),
            'title' => '',
            'result' => (string) ($row['result'] ?? ''),
            'units' => (string) ($row['units'] ?? ''),
        ];
    }

    /**
     * A surgery-history row. Practices record codes like "CPT4:45378" in the diagnosis field; when there
     * is no recognised code, the title ("Colonoscopy") is kept for keyword matching.
     *
     * @return array{pid: string, date: string, code: string, title: string}
     */
    public static function surgeryRow(array $row): array
    {
        $code = '';
        foreach (explode(';', (string) ($row['diagnosis'] ?? '')) as $entry) {
            $candidate = trim(array_pad(explode(':', $entry, 2), 2, '')[1]);
            if (MeasureCodes::screeningTypeForCode($candidate) !== null) {
                $code = $candidate;
                break;
            }
        }
        return [
            'pid' => (string) ($row['pid'] ?? ''),
            'date' => self::firstDate($row, ['begdate', 'date']),
            'code' => $code,
            'title' => (string) ($row['title'] ?? ''),
        ];
    }

    /** "?,?,?" for a parameterised IN (...) list. */
    public static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
