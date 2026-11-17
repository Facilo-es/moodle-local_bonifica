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
 * Bonifica in a course: the action, its groups, participants, costs and files.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_bonifica\data;
use local_bonifica\edition;
use local_bonifica\grid;
use local_bonifica\paste;
use local_bonifica\ui;
use local_bonifica\rules\fields;
use local_bonifica\rules\nif;

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'participantes', PARAM_ALPHA);
$gid = optional_param('g', 0, PARAM_INT);
$do = optional_param('do', '', PARAM_ALPHA);
$tabs = ['accion', 'grupos', 'participantes', 'costes', 'archivos'];
if (!in_array($tab, $tabs, true)) {
    $tab = 'participantes';
}

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/bonifica:manage', $context);

$p = 'local_bonifica';
$base = new moodle_url('/local/bonifica/course.php', ['id' => $courseid]);
$PAGE->set_url(new moodle_url($base, ['tab' => $tab] + ($gid ? ['g' => $gid] : [])));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('title', $p) . ' · ' . format_string($course->shortname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->add_body_class('local-bonifica-page');
$icon = ['size' => 32, 'c1' => '#5b6670', 'c2' => '#357a32'];

$action = data::get_action($courseid);
if (!$action) {
    if ($do === 'start') {
        require_sesskey();
        data::save_action($courseid, ['nombre' => core_text::substr($course->fullname, 0, 255)]);
        redirect(new moodle_url($base, ['tab' => 'accion']));
    }
    echo $OUTPUT->header();
    echo html_writer::start_div('local-bonifica');
    echo $OUTPUT->render_from_template('local_bonifica/empty', ['url' => $base->out(false), 'sesskey' => sesskey(),
        'icon' => $icon]);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

data::sync_groups($action);
$groups = data::get_groups($action);
$group = $gid && isset($groups[$gid]) ? $groups[$gid] : reset($groups);
$url = new moodle_url($base, ['tab' => $tab, 'g' => $group->id]);
$taburl = fn(string $t) => new moodle_url($base, ['tab' => $t, 'g' => $group->id]);

// Typed costs kept after a failed save.
$posted = [];
$costerrors = [];

if ($do !== '') {
    require_sesskey();
    switch ($do) {
        case 'addpart':
            data::save_part(['bgroupid' => $group->id, 'tipodoc' => nif::TYPE_NIF, 'docnum' => '', 'nombre' => '',
                'email' => '', 'telefono' => '']);
            redirect($taburl('participantes'));
            break;

        case 'paste':
            $result = paste::parse(optional_param('text', '', PARAM_RAW), $group->id);
            foreach ($result['rows'] as $row) {
                data::save_part(['bgroupid' => $group->id] + $row);
            }
            $n = count($result['rows']);
            if ($n) {
                \core\notification::success(get_string($n === 1 ? 'paste_added_one' : 'paste_added', $p, $n));
            }
            if ($result['errors']) {
                $lines = array_map(fn($e) => get_string('paste_row', $p, (object)$e), array_slice($result['errors'], 0, 10));
                $n = count($result['errors']);
                \core\notification::warning(get_string($n === 1 ? 'paste_failed_one' : 'paste_failed', $p, $n) .
                    html_writer::alist(array_map('s', $lines)));
            }
            redirect($taburl('participantes'));
            break;

        case 'saveall':
            $parts = data::get_parts($group->id);
            $delete = optional_param('delpart', 0, PARAM_INT);
            if ($delete) {
                if (!isset($parts[$delete])) {
                    throw new moodle_exception('invalidrecord', 'error');
                }
                $DB->delete_records('local_bonifica_part', ['id' => $delete, 'bgroupid' => $group->id]);
                \core\notification::success(get_string('deleted_part', $p));
                redirect($taburl('participantes'));
            }
            $cells = (array)(data_submitted()->cells ?? []);
            foreach ($cells as $partid => $values) {
                if (!isset($parts[$partid])) {
                    continue;
                }
                $part = $parts[$partid];
                foreach ((array)$values as $field => $value) {
                    $field = clean_param($field, PARAM_ALPHANUMEXT);
                    $value = clean_param((string)$value, PARAM_TEXT);
                    if (strpos($field, 'flag_') === 0) {
                        if ((string)(int)!empty($part->flags[substr($field, 5)]) === $value) {
                            continue;
                        }
                    } else if (!in_array($field, grid::FIELDS, true) || (string)($part->$field ?? '') === trim($value)) {
                        continue;
                    }
                    grid::save($part, $field, $value, $context);
                    $part = data::get_part($partid);
                }
            }
            \core\notification::success(get_string('saved', $p));
            $back = optional_param('back', 'participantes', PARAM_ALPHA);
            redirect($taburl(in_array($back, $tabs, true) ? $back : 'participantes'));
            break;

        case 'filed':
            $kind = optional_param('kind', 'fin', PARAM_ALPHA);
            data::save_group($group->id, [$kind === 'inicio' ? 'startfiled' : 'endfiled' => time()]);
            \core\notification::success(get_string('filed_done', $p));
            redirect($taburl('archivos'));
            break;

        case 'costs':
            $delete = optional_param('delcost', 0, PARAM_INT);
            if ($delete) {
                $DB->delete_records('local_bonifica_cost', ['id' => $delete, 'bgroupid' => $group->id]);
                \core\notification::success(get_string('deleted_cost', $p));
                redirect($taburl('costes'));
            }
            $role = data::role();
            $testmode = (bool)get_config($p, 'testmode');
            $existing = data::get_costs($group->id);
            $names = ['directos', 'indirectos', 'salariales', 'organizacion'];
            $save = [];
            $cifs = [];
            foreach ((array)(data_submitted()->costs ?? []) as $id => $row) {
                $row = array_map(fn($v) => trim(clean_param((string)$v, PARAM_TEXT)), (array)$row);
                if ($id !== 'new' && !isset($existing[$id])) {
                    continue;
                }
                $filled = array_filter($row, fn($v) => $v !== '');
                if ($id === 'new' && !$filled) {
                    continue;
                }
                $posted[$id] = $row;
                $rec = ['id' => $id === 'new' ? 0 : (int)$id, 'bgroupid' => $group->id];
                if ($role !== 'bonificada') {
                    $cif = nif::clean($row['cif'] ?? '');
                    if (!nif::valid_cif($cif, $testmode)) {
                        $costerrors[$id]['cif'] = get_string('invalidcif', $p);
                    } else if (isset($cifs[$cif])) {
                        $costerrors[$id]['cif'] = get_string('err_cifdup', $p);
                    }
                    $cifs[$cif] = true;
                    $rec['cif'] = $cif;
                }
                foreach ($names as $name) {
                    if (!array_key_exists($name, $row)) {
                        continue;
                    }
                    $amount = fields::amount($row[$name] === '' ? '0' : $row[$name]);
                    if ($amount === null) {
                        $costerrors[$id][$name] = get_string('err_amount', $p);
                    }
                    $rec[$name] = $amount;
                }
                $save[] = [$rec, $row];
            }
            if (!$costerrors) {
                foreach ($save as [$rec, $row]) {
                    data::save_cost($rec);
                    if (!empty($rec['cif'])) {
                        $plantilla = trim($row['plantilla'] ?? '');
                        data::save_company($rec['cif'], ['razonsocial' => core_text::substr($row['razon'] ?? '', 0, 255),
                            'plantilla' => ctype_digit($plantilla) ? (int)$plantilla : null]);
                    }
                }
                \core\notification::success(get_string('saved', $p));
                redirect($taburl('costes'));
            }
            $tab = 'costes';
            break;
    }
}

// Moodle forms post back to this page and handle themselves.
$forms = [];
if ($tab === 'accion') {
    $forms['action'] = new \local_bonifica\form\action($url->out(false));
    $forms['action']->set_data((object)['id' => $courseid, 'idaccion' => $action->idaccion, 'nombre' => $action->nombre,
        'modalidad' => $action->modalidad, 'horaspr' => format_float((float)$action->horaspr, -1),
        'horaste' => format_float((float)$action->horaste, -1)]);
    if ($forms['action']->is_cancelled()) {
        redirect($url);
    } else if ($fields = $forms['action']->get_fields()) {
        data::save_action($courseid, $fields);
        \core\notification::success(get_string('saved', $p));
        redirect($url);
    }
}
$edit = optional_param('edit', 0, PARAM_INT);
if ($tab === 'grupos' && $edit && isset($groups[$edit])) {
    $eg = $groups[$edit];
    $editurl = new moodle_url($url, ['edit' => $edit, 'g' => $edit]);
    $forms['group'] = new \local_bonifica\form\group($editurl->out(false));
    $forms['group']->set_data((object)['id' => $courseid, 'g' => $edit, 'edit' => $edit, 'tab' => 'grupos',
        'idgrupo' => $eg->idgrupo, 'fechainicio' => $eg->fechainicio, 'fechafin' => $eg->fechafin,
        'responsable' => $eg->responsable, 'telefono' => $eg->telefono]);
    if ($forms['group']->is_cancelled()) {
        redirect($taburl('grupos'));
    } else if ($fields = $forms['group']->get_fields()) {
        data::save_group($edit, $fields);
        \core\notification::success(get_string('saved', $p));
        redirect(new moodle_url($base, ['tab' => 'grupos', 'g' => $edit]));
    }
}

echo $OUTPUT->header();
$classes = edition::is_pro() ? 'pro' : '';
$content = '';
switch ($tab) {
    case 'accion':
        $content .= $OUTPUT->heading(get_string('tab_accion', $p), 3, 'bA-h');
        $content .= $forms['action']->render();
        break;

    case 'grupos':
        $rows = [];
        foreach ($groups as $g) {
            $rows[] = ['id' => $g->id, 'name' => $g->name, 'idgrupo' => $g->idgrupo, 'nparts' => (int)$g->nparts,
                'start' => $g->fechainicio ? userdate($g->fechainicio, get_string('strftimedatefullshort', 'langconfig')) : '',
                'end' => $g->fechafin ? userdate($g->fechafin, get_string('strftimedatefullshort', 'langconfig')) : '',
                'status' => ui::status($action, $g), 'selected' => $g->id == ($edit ?: $group->id),
                'editurl' => (new moodle_url($base, ['tab' => 'grupos', 'g' => $g->id, 'edit' => $g->id]))->out(false)];
        }
        $content .= $OUTPUT->render_from_template('local_bonifica/groups', ['help' => get_string('groups_help', $p),
            'groups' => $rows, 'courseid' => $course->id, 'sesskey' => sesskey(),
            'downloadurl' => (new moodle_url('/local/bonifica/download.php'))->out(false),
            'footer' => get_string('footer', $p)]);
        if (isset($forms['group'])) {
            $content .= $OUTPUT->heading(get_string('editgroup_title', $p, s($groups[$edit]->name)), 3, 'bA-h mt-4');
            $content .= $forms['group']->render();
        }
        break;

    case 'participantes':
        $ctx = ui::participants($action, $group, $context, $url);
        $ctx['segfin'] = optional_param('seg', '', PARAM_ALPHA) === 'fin' && edition::is_pro();
        if ($ctx['segfin']) {
            $classes .= ' show-fin';
        }
        $content .= $OUTPUT->render_from_template('local_bonifica/participants', $ctx);
        break;

    case 'costes':
        $content .= $OUTPUT->render_from_template('local_bonifica/costs', ui::costs($action, $group, $url, $posted, $costerrors));
        break;

    case 'archivos':
        $ctx = ui::files($action, $group, $url);
        $classes .= ($ctx['ready'] ? ' is-ready' : '') . ($ctx['generated'] ? ' is-done' : '');
        $content .= $OUTPUT->render_from_template('local_bonifica/files', $ctx);
        break;
}

$tabrow = [];
foreach ($tabs as $t) {
    $tabrow[] = new tabobject($t, $taburl($t), get_string('tab_' . $t, $p));
}
echo html_writer::start_div('local-bonifica ' . trim($classes), ['data-courseid' => $courseid]);
echo $OUTPUT->render_from_template('local_bonifica/header', ['title' => get_string('title', $p), 'bar' => ui::bar($action),
    'hasgroups' => count($groups) > 1, 'url' => $base->out(false), 'courseid' => $courseid, 'tab' => $tab,
    'groups' => ui::group_options($groups, $group), 'icon' => $icon]);
echo $OUTPUT->tabtree($tabrow, $tab);
echo $content;
echo html_writer::end_div();
$PAGE->requires->js_call_amd('local_bonifica/grid', 'init');
echo $OUTPUT->footer();
