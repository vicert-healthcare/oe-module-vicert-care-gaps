<?php

declare(strict_types=1);

namespace Vicert\CareGaps\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vicert\CareGaps\Repository\RowNormalizer;

final class RowNormalizerTest extends TestCase
{
    public function testFirstUsableDateIsPicked(): void
    {
        $row = ['collected' => '0000-00-00 00:00:00', 'resulted' => null, 'reported' => '2026-03-02 10:00:00'];

        self::assertSame('2026-03-02 10:00:00', RowNormalizer::firstDate($row, ['collected', 'resulted', 'reported']));
        self::assertSame('', RowNormalizer::firstDate(['a' => ''], ['a', 'missing']));
    }

    public function testLabRowKeepsResultCodeOverOrderCode(): void
    {
        $row = RowNormalizer::labRow(
            ['pid' => '4', 'collected' => '2026-01-05', 'result_code' => '29771-3', 'order_code' => 'FITPANEL', 'result' => 'Negative'],
            ['collected'],
        );

        self::assertSame(['pid' => '4', 'date' => '2026-01-05', 'code' => '29771-3', 'title' => '', 'result' => 'Negative', 'units' => ''], $row);
    }

    public function testLabRowFallsBackToOrderCodeWhenResultHasNoCode(): void
    {
        $row = RowNormalizer::labRow(
            ['pid' => '4', 'collected' => '2026-01-05', 'result_code' => '', 'order_code' => '45378', 'result' => ''],
            ['collected'],
        );

        self::assertSame('45378', $row['code']);
    }

    public function testSurgeryRowUsesTheFirstScreeningCodeInTheDiagnosisField(): void
    {
        $row = RowNormalizer::surgeryRow([
            'pid' => '9', 'begdate' => '2024-06-01', 'date' => '2024-06-03 08:00:00',
            'diagnosis' => 'ICD10:Z12.11;CPT4:45378', 'title' => 'Procedure',
        ]);

        self::assertSame(['pid' => '9', 'date' => '2024-06-01', 'code' => '45378', 'title' => 'Procedure'], $row);
    }

    public function testSurgeryRowWithoutCodeKeepsTitleAndFallsBackToEntryDate(): void
    {
        $row = RowNormalizer::surgeryRow([
            'pid' => '9', 'begdate' => null, 'date' => '2024-06-03 08:00:00', 'diagnosis' => '', 'title' => 'Colonoscopy',
        ]);

        self::assertSame(['pid' => '9', 'date' => '2024-06-03 08:00:00', 'code' => '', 'title' => 'Colonoscopy'], $row);
    }

    public function testPlaceholdersMatchTheNumberOfValues(): void
    {
        self::assertSame('?,?,?', RowNormalizer::placeholders(['a', 'b', 'c']));
    }
}
