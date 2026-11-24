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

namespace Vicert\CareGaps\Measures;

use Vicert\CareGaps\Domain\ScreeningType;

/**
 * Every clinical code the worklist recognises, in one place.
 *
 * Based on the CMS122 (diabetes A1C), CMS165 (blood pressure) and CMS130 (colorectal screening) value sets,
 * trimmed to the codes practices commonly record. This is a worklist, not a certified quality measure.
 */
final class MeasureCodes
{
    /** ICD-10 prefixes for type 1, type 2 and other specified diabetes. */
    public const DIABETES = ['E10', 'E11', 'E13'];

    /** ICD-10 essential (primary) hypertension. */
    public const HYPERTENSION = ['I10'];

    /**
     * Excludes colorectal screening, active or resolved: colon, rectosigmoid and rectal cancer (C18-C20),
     * personal history of colon or rectal cancer (Z85.038, Z85.048), and acquired absence of colon (Z90.49).
     */
    public const COLORECTAL_EXCLUSIONS = ['C18', 'C19', 'C20', 'Z85.038', 'Z85.048', 'Z90.49'];

    /** LOINC codes for hemoglobin A1C. */
    public const A1C_LOINC = ['4548-4', '4549-2', '17855-8', '17856-6', '96595-4'];

    /** @var array<string, list<string>> screening type => LOINC, CPT and HCPCS codes */
    public const SCREENING_CODES = [
        'colonoscopy' => [
            '44388', '44389', '44390', '44391', '44392', '44394', '44401', '44402', '44403', '44404', '44405',
            '44406', '44407', '44408', '45378', '45379', '45380', '45381', '45382', '45384', '45385', '45386',
            '45388', '45389', '45390', '45391', '45392', '45393', '45398', 'G0105', 'G0121',
        ],
        'ct_colonography' => ['74261', '74262', '74263', '60515-4', '72531-7', '79069-1', '79071-7', '79101-2'],
        'flexible_sigmoidoscopy' => [
            '45330', '45331', '45332', '45333', '45334', '45335', '45337', '45338', '45340', '45341', '45342',
            '45346', '45347', '45349', '45350', 'G0104',
        ],
        'fit_dna' => ['77353-1', '77354-9', '81528'],
        'fobt' => [
            '2335-8', '12503-9', '12504-7', '14563-1', '14564-9', '14565-6', '27396-1', '27401-9', '27925-7',
            '27926-5', '29771-3', '56490-6', '56491-4', '57905-2', '58453-2', '80372-6', '82270', '82274', 'G0328',
        ],
    ];

    /**
     * Words in a surgery-history title that mean a screening was done, when no code was recorded.
     * Checked in order, so "virtual colonoscopy" (a CT colonography) is matched before "colonoscopy".
     */
    private const TITLE_KEYWORDS = [
        'virtual colonoscopy' => ScreeningType::CtColonography,
        'colonography' => ScreeningType::CtColonography,
        'sigmoidoscopy' => ScreeningType::FlexibleSigmoidoscopy,
        'colonoscopy' => ScreeningType::Colonoscopy,
    ];

    /** Words that mean the test was not actually done, so a matching title must not count. */
    private const NOT_DONE_PATTERN = '/\b(refus\w*|declin\w*|schedul\w*|planned|cancel\w*|ordered)\b|^\s*(no|not|never)\b/';

    /** Plausible A1C range in percent (NGSP); values outside it are treated as missing. */
    public const A1C_MIN_PERCENT = 3.0;
    public const A1C_MAX_PERCENT = 20.0;

    public static function screeningTypeForCode(string $code): ?ScreeningType
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        foreach (self::SCREENING_CODES as $type => $codes) {
            if (in_array($code, $codes, true)) {
                return ScreeningType::from($type);
            }
        }
        return null;
    }

    public static function screeningTypeForTitle(string $title): ?ScreeningType
    {
        $title = strtolower($title);
        if (preg_match(self::NOT_DONE_PATTERN, $title) === 1) {
            return null;
        }
        foreach (self::TITLE_KEYWORDS as $keyword => $type) {
            if (str_contains($title, $keyword)) {
                return $type;
            }
        }
        return null;
    }

    /** @return list<string> every screening code, for the database query */
    public static function allScreeningCodes(): array
    {
        return array_values(array_merge(...array_values(self::SCREENING_CODES)));
    }
}
