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
 * Builds a FUNDAE file, checks it against the official schema and sends it. Nothing is sent if a check fails.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_bonifica\checklist;
use local_bonifica\data;
use local_bonifica\edition;
use local_bonifica\xml\builder_fin;
use local_bonifica\xml\schemas;
use local_bonifica\xml\validator;

$courseid = required_param('id', PARAM_INT);
$kind = required_param('kind', PARAM_ALPHA);
$gid = optional_param('g', 0, PARAM_INT);
$gids = optional_param_array('gs', [], PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/bonifica:manage', $context);
require_sesskey();

if (!in_array($kind, ['fin', 'aaff', 'inicio'], true) || ($kind !== 'fin' && !edition::is_pro())) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$action = data::get_action($courseid);
if (!$action) {
    throw new moodle_exception('invalidparameter', 'debug');
}
$ids = array_unique(array_filter(array_merge([$gid], $gids)));
if (!$ids) {
    $ids = array_keys(data::get_groups($action));
}
$groups = [];
foreach ($ids as $id) {
    $groups[] = data::get_group((int)$id, $action);
}
$back = new moodle_url('/local/bonifica/course.php', ['id' => $courseid, 'tab' => 'archivos', 'g' => reset($ids)]);

$role = data::role();
$year = data::schema_year();
if ($kind === 'fin') {
    if (!checklist::run($action, $groups)['ready']) {
        redirect($back, get_string('notready', 'local_bonifica'), null, \core\output\notification::NOTIFY_ERROR);
    }
    $sets = [];
    foreach ($groups as $group) {
        $parts = data::get_parts($group->id);
        $sets[] = ['action' => $action, 'group' => $group, 'parts' => $parts, 'costs' => data::get_costs($group->id)];
    }
    $doc = builder_fin::build($role, $sets);
    $filename = builder_fin::filename($sets);
}

$errors = validator::validate_dom($doc, schemas::path($kind, $role, $year));
if ($errors) {
    $list = html_writer::alist(array_map(fn($e) => s($e['message']), array_slice($errors, 0, 10)));
    redirect($back, get_string('notready', 'local_bonifica') . $list, null, \core\output\notification::NOTIFY_ERROR);
}
$xml = $doc->saveXML();

// Only who, when, which group and the file hash are kept. Never the file itself.
foreach ($groups as $group) {
    $exportid = $DB->insert_record('local_bonifica_export', (object)['bgroupid' => $group->id, 'kind' => $kind,
        'schemayear' => $year, 'userid' => $USER->id, 'timecreated' => time(), 'sha256' => hash('sha256', $xml)]);
    \local_bonifica\event\file_generated::create(['objectid' => $exportid, 'context' => $context])->trigger();
}

send_file($xml, $filename, 0, 0, true, true, 'application/xml');
