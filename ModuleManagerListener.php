<?php

/**
 * Care Gaps Worklist for OpenEMR: Module Manager hooks.
 *
 * The module creates no tables and changes no settings, so install, enable, disable and unregister
 * need no extra work. This class exists because OpenEMR's Module Manager expects it.
 *
 * @package   Vicert\CareGaps
 * @link      https://vicert.ai
 * @copyright Copyright (c) 2026 Vicert
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Core\AbstractModuleActionListener;

class ModuleManagerListener extends AbstractModuleActionListener
{
    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        return $currentActionStatus;
    }

    public static function getModuleNamespace(): string
    {
        return 'Vicert\\CareGaps\\';
    }

    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }
}
