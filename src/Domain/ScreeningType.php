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

/** Colorectal cancer screening tests and how long each one counts (CMS130 look-back periods). */
enum ScreeningType: string
{
    case Colonoscopy = 'colonoscopy';
    case CtColonography = 'ct_colonography';
    case FlexibleSigmoidoscopy = 'flexible_sigmoidoscopy';
    case FitDna = 'fit_dna';
    case Fobt = 'fobt';

    public function validityYears(): int
    {
        return match ($this) {
            self::Colonoscopy => 10,
            self::CtColonography, self::FlexibleSigmoidoscopy => 5,
            self::FitDna => 3,
            self::Fobt => 1,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Colonoscopy => 'colonoscopy',
            self::CtColonography => 'CT colonography',
            self::FlexibleSigmoidoscopy => 'flexible sigmoidoscopy',
            self::FitDna => 'stool DNA test',
            self::Fobt => 'FIT/FOBT',
        };
    }
}
