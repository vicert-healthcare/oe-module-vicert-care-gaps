<?php

/**
 * Care Gaps Worklist for OpenEMR: the worklist page (Reports > Care Gaps Worklist).
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use OpenEMR\Core\Header;
use OpenEMR\Core\ModulesClassLoader;
use OpenEMR\Core\OEGlobalsBag;
use Vicert\CareGaps\Bootstrap;
use Vicert\CareGaps\Domain\MeasureKey;
use Vicert\CareGaps\Http\PageGuard;
use Vicert\CareGaps\Repository\ClinicalDataRepository;
use Vicert\CareGaps\Service\WorklistFilter;
use Vicert\CareGaps\Service\WorklistService;
use Vicert\CareGaps\Support\DateMath;

(new ModulesClassLoader(OEGlobalsBag::getInstance()->getProjectDir()))
    ->registerNamespaceIfNotExists('Vicert\\CareGaps\\', __DIR__ . '/../src');

PageGuard::requireAccess();

/** Rows shown on screen; the CSV export always has every row. */
const MAX_ROWS_SHOWN = 500;

$today = new DateTimeImmutable('today');
$worklist = WorklistService::withDefaultMeasures()->build((new ClinicalDataRepository())->loadRecords($today), $today);
$filter = WorklistFilter::fromQuery($_GET);
$visible = $worklist->filter($filter);
$shownRows = array_slice($visible->rows, 0, MAX_ROWS_SHOWN);
PageGuard::audit('viewed', count($visible->rows), $filter->measure?->value);
$counts = $worklist->filter(new WorklistFilter(null, $filter->providerId, $filter->search))->counts();
$webRoot = OEGlobalsBag::getInstance()->getWebRoot();
$exportUrl = $webRoot . Bootstrap::MODULE_PATH . '/public/export.php?' . http_build_query($filter->toQuery());

/** Link that keeps the current provider and search but switches the measure. */
$measureLink = static function (?MeasureKey $measure) use ($filter): string {
    $query = (new WorklistFilter($measure, $filter->providerId, $filter->search))->toQuery();
    return '?' . http_build_query($query);
};
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Care Gaps Worklist'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet" href="<?php echo attr($webRoot . Bootstrap::MODULE_PATH . '/public/assets/worklist.css'); ?>">
</head>
<body class="body_top care-gaps">
<div class="container-fluid mt-3">
    <div class="d-flex flex-wrap align-items-baseline justify-content-between mb-2">
        <h2 class="mb-1"><?php echo xlt('Care Gaps Worklist'); ?></h2>
        <span class="text-muted small">
            <?php echo xlt('As of') . ' ' . text(DateMath::display($today)); ?> &middot;
            <?php echo text((string) $worklist->patientCount()) . ' ' . xlt('patients with open gaps'); ?>
        </span>
    </div>

    <div class="cg-cards mb-3">
        <a class="cg-card <?php echo $filter->measure === null ? 'active' : ''; ?>" href="<?php echo attr($measureLink(null)); ?>">
            <span class="cg-count"><?php echo text((string) array_sum($counts)); ?></span>
            <span class="cg-label"><?php echo xlt('Open gaps (all measures)'); ?></span>
        </a>
        <?php foreach (MeasureKey::cases() as $key) : ?>
            <a class="cg-card <?php echo $filter->measure === $key ? 'active' : ''; ?>"
               href="<?php echo attr($measureLink($key)); ?>" title="<?php echo xla($key->description()); ?>">
                <span class="cg-count"><?php echo text((string) $counts[$key->value]); ?></span>
                <span class="cg-label"><?php echo xlt($key->label()); ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="form-inline mb-3">
        <?php if ($filter->measure !== null) : ?>
            <input type="hidden" name="measure" value="<?php echo attr($filter->measure->value); ?>">
        <?php endif; ?>
        <label class="mr-2" for="cg-provider"><?php echo xlt('Provider'); ?></label>
        <select id="cg-provider" name="provider" class="form-control form-control-sm mr-3" onchange="this.form.submit()">
            <option value=""><?php echo xlt('All providers'); ?></option>
            <?php foreach ($worklist->providers() as $id => $name) : ?>
                <option value="<?php echo attr((string) $id); ?>" <?php echo $filter->providerId === $id ? 'selected' : ''; ?>>
                    <?php echo text($name !== '' ? $name : xl('Provider') . ' ' . $id); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="cg-search"><?php echo xlt('Patient name'); ?></label>
        <input id="cg-search" type="search" name="q" class="form-control form-control-sm mr-2"
               maxlength="<?php echo attr((string) WorklistFilter::MAX_SEARCH_LENGTH); ?>"
               placeholder="<?php echo xla('Search patient name'); ?>" value="<?php echo attr($filter->search); ?>">
        <button type="submit" class="btn btn-sm btn-primary mr-2"><?php echo xlt('Filter'); ?></button>
        <a class="btn btn-sm btn-secondary" href="<?php echo attr($exportUrl); ?>"><?php echo xlt('Export CSV'); ?></a>
    </form>

    <?php if ($visible->rows === []) : ?>
        <div class="alert alert-success"><?php echo xlt('No open care gaps match this filter.'); ?></div>
    <?php else : ?>
        <?php if (count($visible->rows) > MAX_ROWS_SHOWN) : ?>
            <div class="alert alert-info py-2">
                <?php echo xlt('Showing the first') . ' ' . text((string) MAX_ROWS_SHOWN) . ' ' . xlt('of') . ' '
                    . text((string) count($visible->rows)) . '. '
                    . xlt('Narrow the list with the filters, or use Export CSV for every row.'); ?>
            </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover cg-table">
                <thead>
                <tr>
                    <th><?php echo xlt('Patient'); ?></th>
                    <th><?php echo xlt('Age'); ?></th>
                    <th><?php echo xlt('Phone'); ?></th>
                    <th><?php echo xlt('Provider'); ?></th>
                    <th><?php echo xlt('Care gap'); ?></th>
                    <th><?php echo xlt('Details'); ?></th>
                    <th><?php echo xlt('Due since'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($shownRows as $row) : ?>
                    <tr>
                        <td>
                            <a href="#" class="cg-open-chart" data-pid="<?php echo attr((string) $row->patient->pid); ?>">
                                <?php echo text($row->patient->lastName . ', ' . $row->patient->firstName); ?>
                            </a>
                        </td>
                        <td><?php echo text((string) $row->age); ?></td>
                        <td class="text-nowrap"><?php echo text($row->patient->phone); ?></td>
                        <td><?php echo text($row->patient->providerName); ?></td>
                        <td><span class="cg-badge cg-<?php echo attr($row->gap->measure->value); ?>"><?php echo xlt($row->gap->measure->label()); ?></span></td>
                        <td><?php echo text($row->gap->detail); ?></td>
                        <td class="text-nowrap"><?php echo text($row->gap->dueSince === null ? '' : DateMath::display($row->gap->dueSince)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <p class="text-muted small mt-3">
        <?php echo xlt('Based on the CMS122, CMS165 and CMS130 quality measures, adapted for a daily worklist. Not a certified quality measure report.'); ?>
    </p>
</div>
<script>
    document.querySelectorAll('.cg-open-chart').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var url = <?php echo js_escape($webRoot . '/interface/patient_file/summary/demographics.php?set_pid='); ?>
                + encodeURIComponent(link.dataset.pid);
            top.restoreSession();
            top.navigateTab(url, 'pat', function () { top.activateTabByName('pat', true); });
        });
    });
</script>
</body>
</html>
