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

namespace Vicert\CareGaps;

use OpenEMR\Menu\MenuEvent;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/** Adds "Care Gaps Worklist" to the top of OpenEMR's Reports menu. */
final class Bootstrap
{
    public const MODULE_PATH = '/interface/modules/custom_modules/oe-module-vicert-care-gaps';
    private const REPORTS_MENU_ID = 'repimg';

    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, $this->addMenuItem(...));
    }

    public function addMenuItem(MenuEvent $event): MenuEvent
    {
        $menu = $event->getMenu();
        foreach ($menu as $menuItem) {
            if (($menuItem->menu_id ?? '') !== self::REPORTS_MENU_ID) {
                continue;
            }
            $item = new stdClass();
            $item->requirement = 0;
            $item->target = 'cgw0';
            $item->menu_id = 'cgw0';
            $item->label = xl('Care Gaps Worklist'); // the menu renderer escapes labels itself
            $item->url = self::MODULE_PATH . '/public/index.php';
            $item->children = [];
            // The menu can only require one permission (a nested list means "any of"); the page itself
            // also requires demographics access, because it shows phone numbers.
            $item->acl_req = ['patients', 'med'];
            $item->global_req = [];
            array_unshift($menuItem->children, $item);
            break;
        }
        $event->setMenu($menu);
        return $event;
    }
}
