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

namespace local_bonifica;

use local_bonifica\rules\fields;
use local_bonifica\rules\nif;

/**
 * Builds the template data for the course pages.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ui {
    /** @var string Component. */
    const P = 'local_bonifica';

    /**
     * Status of a group, latest step first.
     *
     * @param \stdClass $action
     * @param \stdClass $group
     * @param int|null $now
     * @return array ['key' => string, 'label' => string, 'badge' => string]
     */
    public static function status(\stdClass $action, \stdClass $group, ?int $now = null): array {
        $now = $now ?? time();
        if (!empty($group->endfiled)) {
            $key = 'filed';
            $badge = 'success';
        } else if ($group->fechafin && $group->fechafin < $now) {
            $key = 'finished';
            $badge = 'warning';
        } else if (!empty($group->startfiled)) {
            $key = 'started';
            $badge = 'info';
        } else if (checklist::run($action, [$group])['ready']) {
            $key = 'ready';
            $badge = 'success';
        } else {
            $key = 'missing';
            $badge = 'danger';
        }
        return ['key' => $key, 'label' => get_string('st_' . $key, self::P), 'badge' => $badge];
    }

    /**
     * Header line under the title.
     *
     * @param \stdClass $action
     * @return string
     */
    public static function bar(\stdClass $action): string {
        $hours = (float)$action->horaspr + (float)$action->horaste;
        return get_string('bar_action', self::P, (object)[
            'id' => s($action->idaccion ?: '—'),
            'mod' => get_string('mod_' . $action->modalidad, self::P),
            'hours' => format_float($hours, -1),
            'role' => get_string('role_' . data::role(), self::P),
        ]);
    }

    /**
     * Options of the group menu.
     *
     * @param \stdClass[] $groups
     * @param \stdClass $current
     * @return array
     */
    public static function group_options(array $groups, \stdClass $current): array {
        $out = [];
        foreach ($groups as $g) {
            $label = $g->name . ' · ' . get_string('col_idgrupo', self::P) . ' ' . ($g->idgrupo ?: '—');
            if ($g->fechainicio && $g->fechafin) {
                $label .= ' · ' . userdate($g->fechainicio, get_string('strftimedatefullshort', 'langconfig')) . ' – ' .
                    userdate($g->fechafin, get_string('strftimedatefullshort', 'langconfig'));
            }
            $out[] = ['value' => $g->id, 'label' => $label, 'selected' => $g->id == $current->id];
        }
        return $out;
    }

    /**
     * Options for a select, with the current value marked.
     *
     * @param array $menu value => label
     * @param mixed $current
     * @return array
     */
    public static function options(array $menu, $current): array {
        $out = [];
        foreach ($menu as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label, 'selected' => (string)$value === (string)$current];
        }
        return $out;
    }

    /**
     * Menus of the select columns.
     *
     * @param string $field
     * @return array|null value => label, or null for a text field
     */
    public static function menu(string $field): ?array {
        switch ($field) {
            case 'tipodoc':
                $out = [];
                foreach ([nif::TYPE_NIF, nif::TYPE_NIE, nif::TYPE_PASSPORT] as $t) {
                    $out[$t] = get_string('doctype_' . $t, self::P);
                }
                return $out;
            case 'categoria':
                $out = ['' => get_string('choose', self::P)];
                for ($i = 1; $i <= 5; $i++) {
                    $out[$i] = $i . ' · ' . get_string('cat_' . $i, self::P);
                }
                return $out;
            case 'nivelestudios':
                $out = ['' => get_string('choose', self::P)];
                for ($i = 1; $i <= 10; $i++) {
                    $out[$i] = $i . ' · ' . get_string('est_' . $i, self::P);
                }
                return $out;
            case 'diploma':
                return ['' => get_string('choose', self::P), 'S' => get_string('yes_s', self::P),
                    'N' => get_string('no_n', self::P)];
        }
        return null;
    }

    /**
     * One editable cell.
     *
     * @param \stdClass $part
     * @param string $field
     * @param string|null $why problem shown under the cell
     * @return array
     */
    public static function cell(\stdClass $part, string $field, ?string $why): array {
        $menu = self::menu($field);
        $value = (string)($part->$field ?? '');
        $sizes = ['nombre' => 22, 'docnum' => 11, 'email' => 24, 'telefono' => 10];
        return [
            'field' => $field, 'partid' => $part->id, 'value' => $value, 'bad' => $why !== null, 'why' => $why,
            'isselect' => $menu !== null, 'options' => $menu !== null ? self::options($menu, $value) : [],
            'size' => $sizes[$field] ?? 10, 'mono' => in_array($field, ['docnum', 'telefono'], true),
            'label' => get_string(
                'col_' . ($field === 'nivelestudios' ? 'nivel' : ($field === 'nombre' ? 'participante' : $field)),
                self::P
            ),
        ];
    }

    /**
     * The participants tab.
     *
     * @param \stdClass $action
     * @param \stdClass $group
     * @param \context $context
     * @param \moodle_url $url
     * @return array
     */
    public static function participants(\stdClass $action, \stdClass $group, \context $context, \moodle_url $url): array {
        $state = grid::state($action, $group);
        $sensitive = has_capability('local/bonifica:viewsensitive', $context);
        $rows = [];
        $n = 0;
        foreach (data::get_parts($group->id) as $part) {
            $n++;
            $bad = $state['cells'][$part->id] ?? [];
            $cells = [];
            foreach (grid::FIELDS as $field) {
                $cells[] = self::cell($part, $field, $bad[$field] ?? null);
            }
            $flags = [];
            foreach (data::FLAGS as $flag) {
                if (in_array($flag, data::SENSITIVE, true) && !$sensitive) {
                    continue;
                }
                $flags[] = ['flag' => $flag, 'label' => get_string('flag_' . $flag, self::P),
                    'checked' => !empty($part->flags[$flag]), 'partid' => $part->id];
            }
            $on = array_column(array_filter($flags, fn($f) => $f['checked']), 'label');
            $rows[] = ['id' => $part->id, 'n' => $n, 'ok' => !$bad, 'cells' => $cells, 'flags' => $flags,
                'fixlabel' => get_string('fixrow', self::P, $n),
                'flagsummary' => $on ? implode(', ', $on) : get_string('none'),
                'name' => $part->nombre ?: $part->email,
                'delconfirm' => get_string('deletepart_confirm', self::P, s($part->nombre ?: $part->email))];
        }
        $ready = checklist::run($action, [$group])['ready'];
        $headers = [];
        foreach (grid::FIELDS as $field) {
            $headers[] = self::cell((object)['id' => 0], $field, null)['label'];
        }
        $out = $state + [
            'rows' => $rows, 'headers' => $headers, 'hasrows' => (bool)$rows, 'canexport' => $ready,
            'sesskey' => sesskey(), 'actionurl' => $url->out(false), 'courseid' => $action->courseid, 'groupid' => $group->id,
            'downloadurl' => (new \moodle_url('/local/bonifica/download.php'))->out(false),
            'templateurl' => (new \moodle_url('/local/bonifica/template.php', ['id' => $action->courseid]))->out(false),
            'pro' => edition::is_pro(), 'footer' => get_string('footer', self::P),
        ];
        if ($state['allready'] && !$ready) {
            $out['foot'] = get_string('notready', self::P);
            $out['footshort'] = get_string('foot_short_notready', self::P);
        }
        return $out;
    }

    /**
     * The files tab.
     *
     * @param \stdClass $action
     * @param \stdClass $group
     * @param \moodle_url $url
     * @return array
     */
    public static function files(\stdClass $action, \stdClass $group, \moodle_url $url): array {
        global $DB;
        $result = checklist::run($action, [$group]);
        $issues = [];
        foreach ($result['issues'] as $i => $issue) {
            $row = ['i' => $i, 'message' => $issue['message'], 'where' => $issue['where'],
                'tab' => $issue['tab'], 'tabname' => get_string('tab_' . $issue['tab'], self::P),
                'taburl' => (new \moodle_url($url, ['tab' => $issue['tab']]))->out(false)];
            if (!empty($issue['fix']['partid'])) {
                $part = data::get_part($issue['fix']['partid']);
                $row['fix'] = self::cell($part, $issue['fix']['field'], null);
            }
            $issues[] = $row;
        }
        $total = count($result['passed']) + count(array_unique(array_column($result['issues'], 'check')));
        $data = ['action' => $action, 'group' => $group, 'parts' => data::get_parts($group->id),
            'costs' => data::get_costs($group->id)];
        $last = $DB->get_records(
            'local_bonifica_export',
            ['bgroupid' => $group->id, 'kind' => 'fin'],
            'timecreated DESC',
            '*',
            0,
            1
        );
        $status = self::status($action, $group);
        $year = data::schema_year();
        return [
            'filename' => \local_bonifica\xml\builder_fin::filename([$data]),
            'status' => $status, 'ready' => $result['ready'], 'issues' => $issues, 'hasissues' => (bool)$issues,
            'left' => get_string('left_n', self::P, (object)['left' => count($issues), 'total' => count($issues)]),
            'pct' => (int)round(100 * count($result['passed']) / max(1, $result['total'])),
            'icon' => ['size' => 22, 'c1' => '#5b6670', 'c2' => '#357a32'],
            'mutedicon' => ['size' => 26, 'c1' => '#8f959e', 'c2' => '#8f959e'],
            'passed' => array_map(fn($t) => ['text' => $t], $result['passed']),
            'passedcount' => get_string('passed_n', self::P, count($result['passed'])),
            'readytitle' => get_string('ready', self::P, $year),
            'readysub' => get_string('passed_n', self::P, $total),
            'warnings' => $result['warnings'], 'haswarnings' => (bool)$result['warnings'],
            'generated' => (bool)$last && empty($group->endfiled),
            'filed' => !empty($group->endfiled)
                ? get_string('filed_on', self::P, userdate($group->endfiled, get_string('strftimedatefullshort', 'langconfig')))
                : null,
            'pro' => edition::is_pro(), 'upsell' => edition::show_upsell(),
            'inicioready' => edition::is_pro() && empty($group->startfiled),
            'sesskey' => sesskey(), 'courseid' => $action->courseid, 'groupid' => $group->id,
            'actionurl' => $url->out(false),
            'downloadurl' => (new \moodle_url('/local/bonifica/download.php'))->out(false),
            'footer' => get_string('footer', self::P),
        ];
    }

    /**
     * The costs tab.
     *
     * @param \stdClass $action
     * @param \stdClass $group
     * @param \moodle_url $url
     * @param array $posted rows typed in a failed save, by id
     * @param array $errors field errors, by id and field
     * @return array
     */
    public static function costs(
        \stdClass $action,
        \stdClass $group,
        \moodle_url $url,
        array $posted = [],
        array $errors = []
    ): array {
        $role = data::role();
        $names = ['directos', 'indirectos', 'salariales'];
        if ($role === 'organizadora') {
            array_splice($names, 2, 0, ['organizacion']);
        }
        $rows = [];
        $costs = data::get_costs($group->id);
        $costs['new'] = (object)['id' => 'new', 'cif' => '', 'company' => null];
        foreach ($costs as $key => $c) {
            $row = ['id' => $c->id, 'isnew' => $c->id === 'new', 'cells' => []];
            $typed = $posted[$c->id] ?? [];
            if ($role !== 'bonificada') {
                $row['cif'] = ['name' => 'cif', 'value' => $typed['cif'] ?? $c->cif, 'error' => $errors[$c->id]['cif'] ?? null];
                $row['razon'] = ['value' => $typed['razon'] ?? ($c->company->razonsocial ?? '')];
                $row['plantilla'] = ['value' => $typed['plantilla'] ?? ($c->company->plantilla ?? '')];
            }
            foreach ($names as $name) {
                $value = $typed[$name] ?? (isset($c->$name) ? format_float((float)$c->$name, 2) : '');
                $row['cells'][] = ['name' => $name, 'value' => $value, 'error' => $errors[$c->id][$name] ?? null];
            }
            $rows[] = $row;
        }
        $heads = array_map(fn($n) => ['label' => get_string($n, self::P)], $names);
        $out = [
            'rows' => $rows, 'heads' => $heads, 'company' => $role !== 'bonificada', 'pro' => edition::is_pro(),
            'sesskey' => sesskey(), 'actionurl' => $url->out(false), 'footer' => get_string('footer', self::P),
        ];
        return $out;
    }
}
