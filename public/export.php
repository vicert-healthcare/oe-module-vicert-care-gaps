<?php

/**
 * Care Gaps Worklist for OpenEMR: CSV export of the current filter, for phone and text outreach.
 * Every export is written to OpenEMR's audit log, since the file contains patient information.
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use Vicert\CareGaps\Http\PageGuard;
use Vicert\CareGaps\Repository\ClinicalDataRepository;
use Vicert\CareGaps\Service\WorklistFilter;
use Vicert\CareGaps\Service\WorklistService;
use Vicert\CareGaps\Support\CsvEscaper;

(new ModulesClassLoader(OEGlobalsBag::getInstance()->getProjectDir()))
    ->registerNamespaceIfNotExists('Vicert\\CareGaps\\', __DIR__ . '/../src');

PageGuard::requireAccess();

$today = new DateTimeImmutable('today');
$filter = WorklistFilter::fromQuery($_GET);
$rows = WorklistService::withDefaultMeasures()
    ->build((new ClinicalDataRepository())->loadRecords($today), $today)
    ->filter($filter)
    ->rows;

PageGuard::audit('CSV export', count($rows), $filter->measure?->value);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="care-gaps-' . $today->format('Y-m-d') . '.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fputcsv($out, ['Last name', 'First name', 'Age', 'Phone', 'Provider', 'Care gap', 'Details', 'Due since'], escape: '');
foreach ($rows as $row) {
    fputcsv($out, [
        CsvEscaper::cell($row->patient->lastName),
        CsvEscaper::cell($row->patient->firstName),
        (string) $row->age,
        CsvEscaper::phone($row->patient->phone),
        CsvEscaper::cell($row->patient->providerName),
        $row->gap->measure->label(),
        CsvEscaper::cell($row->gap->detail),
        $row->gap->dueSince?->format('Y-m-d') ?? '',
    ], escape: '');
}
fclose($out);
