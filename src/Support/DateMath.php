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

/** Calendar arithmetic that never overflows into the next month (Aug 31 minus 6 months is Feb 28). */
final class DateMath
{
    public static function monthsBefore(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        return self::addMonths($date, -$months);
    }

    public static function addMonths(DateTimeImmutable $date, int $months): DateTimeImmutable
    {
        $index = (int) $date->format('Y') * 12 + (int) $date->format('n') - 1 + $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        $firstOfMonth = $date->setDate($year, $month, 1);
        $day = min((int) $date->format('j'), (int) $firstOfMonth->format('t'));
        return $firstOfMonth->setDate($year, $month, $day)->setTime(0, 0);
    }

    /** Whole years completed on $today. */
    public static function ageOn(DateTimeImmutable $dob, DateTimeImmutable $today): int
    {
        return $dob->diff($today)->y;
    }

    public static function display(DateTimeImmutable $date): string
    {
        return $date->format('M j, Y');
    }
}
