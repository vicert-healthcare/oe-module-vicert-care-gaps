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

/** The care gaps the worklist can flag, in the order they are shown. */
enum MeasureKey: string
{
    case A1cOverdue = 'a1c_overdue';
    case A1cPoorControl = 'a1c_poor_control';
    case BpUncontrolled = 'bp_uncontrolled';
    case BpMissing = 'bp_missing';
    case ColorectalScreeningDue = 'crc_screening_due';

    public function label(): string
    {
        return match ($this) {
            self::A1cOverdue => 'A1C overdue',
            self::A1cPoorControl => 'A1C >9% or missing',
            self::BpUncontrolled => 'BP not controlled',
            self::BpMissing => 'BP not checked',
            self::ColorectalScreeningDue => 'Colorectal screening due',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::A1cOverdue => 'Diabetic patients 18-75 with no A1C in the last 6 months',
            self::A1cPoorControl => 'Diabetic patients 18-75 whose last A1C is above 9%, or with none in 12 months',
            self::BpUncontrolled => 'Hypertension patients 18-85 whose last BP is 140/90 or higher',
            self::BpMissing => 'Hypertension patients 18-85 with no BP in the last 12 months',
            self::ColorectalScreeningDue => 'Patients 45-75 with no colorectal cancer screening still valid',
        };
    }

    public function order(): int
    {
        return array_search($this, self::cases(), true);
    }
}
