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

use DateTimeImmutable;

/** Turns OpenEMR's stored strings (diagnosis lists, lab values, dates) into typed values. */
final class CodeParser
{
    /**
     * OpenEMR stores problem codes as "ICD10:E11.9;ICD10:I10". Returns the ICD-10 codes, upper-cased.
     *
     * @return list<string>
     */
    public static function icd10Codes(string $diagnosis): array
    {
        $codes = [];
        foreach (explode(';', $diagnosis) as $entry) {
            [$type, $code] = array_pad(explode(':', trim($entry), 2), 2, '');
            if (strtoupper(trim($type)) === 'ICD10' && trim($code) !== '') {
                $codes[] = strtoupper(trim($code));
            }
        }
        return $codes;
    }

    /** The first number in a lab value ("9.6 %", ">14", "7,2"), or null when there is none. */
    public static function parseNumber(string $raw): ?float
    {
        if (preg_match('/\d+(?:[.,]\d+)?/', $raw, $match) !== 1) {
            return null;
        }
        return (float) str_replace(',', '.', $match[0]);
    }

    /** A date from a database column; empty, zero ("0000-00-00") and malformed values give null. */
    public static function parseDate(?string $raw): ?DateTimeImmutable
    {
        $raw = trim((string) $raw);
        if ($raw === '' || str_starts_with($raw, '0000-00-00')) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($raw, 0, 10));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }
        return $date;
    }
}
