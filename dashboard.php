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
 * Site dashboard: every FUNDAE group by status and deadline, finish rate and inspector accounts (Pro).
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_bonifica\edition;
use local_bonifica\output\dashboard;

$filter = optional_param('f', '', PARAM_ALPHA);

$p = 'local_bonifica';
$syscontext = context_system::instance();
$url = new moodle_url('/local/bonifica/dashboard.php', $filter !== '' ? ['f' => $filter] : []);

require_login();
if (!edition::is_pro()) {
    require_capability('local/bonifica:viewdashboard', $syscontext);
    $PAGE->set_url($url);
    $PAGE->set_context($syscontext);
    $PAGE->set_pagelayout('admin');
}
$PAGE->set_title(get_string('dashboard', $p));
$PAGE->set_heading(format_string($SITE->fullname));
$PAGE->add_body_class('local-bonifica-page');

$page = new dashboard($filter);
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_bonifica/dashboard', $page->export_for_template($OUTPUT));
echo $OUTPUT->footer();
