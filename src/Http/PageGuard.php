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

namespace Vicert\CareGaps\Http;

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Common\Session\SessionWrapperFactory;

/** Access checks and audit logging shared by the worklist page and the CSV export. */
final class PageGuard
{
    /** Both pages show clinical gaps (medical records) and phone numbers (demographics). */
    public static function requireAccess(): void
    {
        if (AclMain::aclCheckCore('patients', 'med') && AclMain::aclCheckCore('patients', 'demo')) {
            return;
        }
        http_response_code(403);
        echo xlt('You do not have access to patient medical records and demographics.');
        exit;
    }

    /**
     * Records who viewed or exported the worklist. The comment holds counts and the measure filter only,
     * never names or search text, so the audit log itself carries no patient information.
     */
    public static function audit(string $action, int $rowCount, ?string $measure): void
    {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        EventAuditLogger::getInstance()->newEvent(
            'patient-record',
            (string) $session->get('authUser'),
            (string) $session->get('authProvider'),
            1,
            sprintf('Care Gaps Worklist %s: %d rows, measure %s', $action, $rowCount, $measure ?? 'all'),
        );
    }
}
