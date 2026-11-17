<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Edition switch.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bonifica;

/**
 * Tells the free and Pro builds apart. The free build ships without the Pro code.
 */
class edition {
    /** @var bool True in the Moodle Marketplace build, which never mentions Pro. */
    const LISTING = false;

    /** @var bool|null Test-only override for LISTING. */
    public static $listingoverride = null;

    /**
     * Whether Pro features are reachable.
     *
     * @return bool
     */
    public static function is_pro(): bool {
        return false;
    }

    /**
     * Whether the free build may show the Pro card. Marketplace rules forbid it in the listed build.
     *
     * @return bool
     */
    public static function show_upsell(): bool {
        $listing = self::LISTING;
        if (self::$listingoverride !== null && (defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
            $listing = self::$listingoverride;
        }
        return !self::is_pro() && !$listing;
    }

    /**
     * Stops a Pro-only request in the free build.
     *
     * @throws \moodle_exception
     */
    public static function require_pro(): void {
        if (!self::is_pro()) {
            throw new \moodle_exception('pro_only', 'local_bonifica');
        }
    }
}
