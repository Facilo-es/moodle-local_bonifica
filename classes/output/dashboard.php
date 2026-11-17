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
 * Site dashboard for Bonifica Pro.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bonifica\output;

use local_bonifica\edition;

/**
 * Site dashboard: every FUNDAE group on the site by status and deadline (Pro).
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dashboard implements \renderable, \templatable {
    /** @var string Component. */
    const P = 'local_bonifica';

    /** @var string[] Status keys in pipeline order. */
    const STATUSES = ['missing', 'ready', 'started', 'finished', 'filed'];

    /** @var string[] Filters the page accepts: a status, or the week's start deadlines. */
    const FILTERS = ['missing', 'ready', 'started', 'finished', 'filed', 'week'];

    /** @var string Active filter, '' for all. */
    protected $filter;

    /** @var int Current time. */
    protected $now;

    /**
     * Constructor.
     *
     * @param string $filter one of FILTERS or ''
     * @param int|null $now
     */
    public function __construct(string $filter = '', ?int $now = null) {
        $this->filter = in_array($filter, self::FILTERS, true) ? $filter : '';
        $this->now = $now ?? time();
    }

    /**
     * Data for the template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        $base = new \moodle_url('/local/bonifica/dashboard.php');
        $ctx = ['pro' => edition::is_pro(), 'upsell' => edition::show_upsell(),
            'icon' => ['size' => 34, 'c1' => '#0f6cbf', 'c2' => '#357a32'],
            'today' => userdate($this->now, get_string('strftimedaydate', 'langconfig'))];
        if (!$ctx['pro']) {
            $ctx['mutedicon'] = ['size' => 28, 'c1' => '#adb5bd', 'c2' => '#adb5bd'];
            return $ctx;
        }
        return $ctx;
    }
}
