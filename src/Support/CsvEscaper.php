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

namespace Vicert\CareGaps\Support;

/** Stops spreadsheet apps from running a cell as a formula when staff open the exported CSV. */
final class CsvEscaper
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(string $value): string
    {
        if ($value !== '' && in_array($value[0], self::FORMULA_STARTS, true)) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * A phone number reduced to the characters a phone number can contain. Nothing left can form a
     * spreadsheet formula, so a leading "+" (international numbers) survives untouched.
     */
    public static function phone(string $value): string
    {
        $phone = trim((string) preg_replace('/[^0-9+()\s.-]/', '', $value));
        return preg_match('/\d/', $phone) === 1 ? $phone : '';
    }
}
