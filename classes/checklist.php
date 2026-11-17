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

use local_bonifica\rules\costs;
use local_bonifica\rules\fields;
use local_bonifica\rules\nif;
use local_bonifica\xml\builder_fin;
use local_bonifica\xml\schemas;
use local_bonifica\xml\validator;

/**
 * The checks run before a file is offered: each problem in plain words, tied to the field that fixes it.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class checklist {
    /** @var string[] The checks, in the order they are listed. */
    const CHECKS = ['action', 'group', 'docs', 'contact', 'profile', 'duplicates', 'costs', 'schema'];

    /** @var array Problems that block the file. */
    protected $issues = [];

    /** @var array Warnings that don't block it (Pro deadlines and cost caps). */
    protected $warnings = [];

    /** @var array Checks that found something. */
    protected $failed = [];

    /**
     * Runs every check on the end file for some groups of an action.
     *
     * @param \stdClass $action
     * @param \stdClass[] $groups decorated groups from data::get_groups()
     * @param string|null $role defaults to the site setting
     * @param int|null $now
     * @return array ['issues' => [], 'warnings' => [], 'passed' => string[], 'total' => int, 'ready' => bool, 'data' => array]
     */
    public static function run(\stdClass $action, array $groups, ?string $role = null, ?int $now = null): array {
        $c = new self();
        $role = $role ?? data::role();
        $data = [];
        $c->check_action($action);
        foreach ($groups as $group) {
            // Keys stay the row numbers of the full table, so messages match the rows the coordinator sees.
            $parts = array_values(data::get_parts($group->id));
            $costs = data::get_costs($group->id);
            $c->check_group($group, $parts);
            $c->check_parts($group, $parts);
            $c->check_costs($group, $costs, $role);
            $data[] = ['action' => $action, 'group' => $group, 'parts' => array_values($parts), 'costs' => $costs];
        }
        if (!$c->issues && $data) {
            $doc = builder_fin::build($role, $data);
            foreach (validator::validate_dom($doc, schemas::path('fin', $role, data::schema_year())) as $e) {
                $c->add('schema', $e['message'], get_string('where_file', 'local_bonifica'), 'archivos');
            }
        }
        $passed = array_values(array_diff(self::CHECKS, array_keys($c->failed)));
        return ['issues' => $c->issues, 'warnings' => $c->warnings, 'total' => count(self::CHECKS),
            'passed' => array_map(fn($k) => get_string('check_' . $k, 'local_bonifica'), $passed),
            'ready' => !$c->issues && (bool)$data, 'data' => $data];
    }

    /**
     * Adds a blocking problem.
     *
     * @param string $check one of CHECKS
     * @param string $message
     * @param string $where
     * @param string $tab tab that fixes it
     * @param array $fix optional inline fix: ['partid' => int, 'field' => string, 'value' => string]
     */
    protected function add(string $check, string $message, string $where, string $tab, array $fix = []): void {
        $this->failed[$check] = true;
        $this->issues[] = ['check' => $check, 'message' => $message, 'where' => $where, 'tab' => $tab, 'fix' => $fix];
    }

    /**
     * Action fields.
     *
     * @param \stdClass $action
     */
    protected function check_action(\stdClass $action): void {
        $where = get_string('tab_accion', 'local_bonifica');
        if (!fields::idaccion($action->idaccion)) {
            $this->add('action', get_string('chk_idaccion', 'local_bonifica'), $where, 'accion');
        }
        if (trim((string)$action->nombre) === '') {
            $this->add('action', get_string('chk_nombre', 'local_bonifica'), $where, 'accion');
        }
        if ((float)$action->horaspr + (float)$action->horaste <= 0) {
            $this->add('action', get_string('chk_horas', 'local_bonifica'), $where, 'accion');
        }
    }

    /**
     * Where a group issue is, without doubling the word when the group is already named "Grupo ...".
     *
     * @param string $name
     * @return string
     */
    protected static function where_group(string $name): string {
        $prefix = \core_text::strtolower(trim(get_string('where_group', 'local_bonifica', '')));
        $lower = \core_text::strtolower(trim($name));
        return $prefix === '' || \core_text::strpos($lower, $prefix . ' ') === 0 || $lower === $prefix
            ? $name : get_string('where_group', 'local_bonifica', $name);
    }

    /**
     * Group fields.
     *
     * @param \stdClass $group
     * @param array $parts
     */
    protected function check_group(\stdClass $group, array $parts): void {
        $where = self::where_group($group->name);
        $n = $group->idgrupo ?: $group->name;
        if (!fields::idgrupo($group->idgrupo)) {
            $this->add('group', get_string('chk_idgrupo', 'local_bonifica', $n), $where, 'grupos');
        }
        if (!$group->fechainicio || !$group->fechafin) {
            $this->add('group', get_string('chk_fechas', 'local_bonifica', $n), $where, 'grupos');
        } else if ($group->fechafin < $group->fechainicio) {
            $this->add('group', get_string('chk_fechaorden', 'local_bonifica', $n), $where, 'grupos');
        }
        if (!$parts) {
            $this->add('group', get_string('chk_noparts', 'local_bonifica', $n), $where, 'participantes');
        }
    }

    /**
     * Participant fields.
     *
     * @param \stdClass $group
     * @param array $parts
     */
    protected function check_parts(\stdClass $group, array $parts): void {
        $seen = [];
        foreach ($parts as $i => $p) {
            $a = (object)[
                'n' => $i + 1,
                'doc' => s($p->docnum),
                'type' => get_string('doctype_' . (int)$p->tipodoc, 'local_bonifica'),
            ];
            $where = get_string('where_part', 'local_bonifica', (object)[
                'group' => $group->name,
                'name' => s($p->nombre ?: $p->email),
            ]);
            $fix = fn($field, $value) => ['partid' => (int)$p->id, 'field' => $field, 'value' => (string)$value];
            if ($p->docnum === '') {
                $this->add('docs', get_string('chk_nodoc', 'local_bonifica', $a), $where, 'participantes', $fix('docnum', ''));
            } else if (!nif::valid_document((int)$p->tipodoc, $p->docnum)) {
                $key = (int)$p->tipodoc === nif::TYPE_PASSPORT ? 'chk_passport' : 'chk_doc';
                $this->add('docs', get_string($key, 'local_bonifica', $a), $where, 'participantes', $fix('docnum', $p->docnum));
            }
            $clean = nif::clean($p->docnum);
            if ($clean !== '' && isset($seen[$clean])) {
                $a->other = $seen[$clean];
                $this->add(
                    'duplicates',
                    get_string('chk_duplicate', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('docnum', $p->docnum)
                );
            }
            $seen[$clean] = $i + 1;
            if (!fields::email($p->email)) {
                $this->add(
                    'contact',
                    get_string('chk_email', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('email', $p->email)
                );
            }
            if (fields::phone($p->telefono) === null) {
                $this->add(
                    'contact',
                    get_string('chk_phone', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('telefono', $p->telefono)
                );
            }
            if ((int)$p->categoria < 1 || (int)$p->categoria > 5) {
                $this->add(
                    'profile',
                    get_string('chk_categoria', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('categoria', '')
                );
            }
            if ((int)$p->nivelestudios < 1 || (int)$p->nivelestudios > 10) {
                $this->add(
                    'profile',
                    get_string('chk_nivel', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('nivelestudios', '')
                );
            }
            if (!in_array($p->diploma, ['S', 'N'], true)) {
                $this->add(
                    'profile',
                    get_string('chk_diploma', 'local_bonifica', $a),
                    $where,
                    'participantes',
                    $fix('diploma', '')
                );
            }
        }
    }

    /**
     * Cost rows.
     *
     * @param \stdClass $group
     * @param array $costs
     * @param string $role
     */
    protected function check_costs(\stdClass $group, array $costs, string $role): void {
        $where = get_string('where_costs', 'local_bonifica', $group->name);
        if (!$costs) {
            $this->add('costs', get_string('chk_nocosts', 'local_bonifica', $group->idgrupo ?: $group->name), $where, 'costes');
        }
        $testmode = (bool)get_config('local_bonifica', 'testmode');
        foreach ($costs as $c) {
            $label = $role === 'bonificada' ? $group->name : $c->cif;
            if ($role !== 'bonificada' && !nif::valid_cif((string)$c->cif, $testmode)) {
                $this->add('costs', get_string('chk_cif', 'local_bonifica', s($c->cif)), $where, 'costes');
            }
            $names = ['directos', 'indirectos', 'salariales'];
            if ($role === 'organizadora') {
                $names[] = 'organizacion';
            }
            foreach ($names as $name) {
                if (fields::amount((string)$c->$name) === null) {
                    $this->add('costs', get_string('chk_amount', 'local_bonifica', s($label)), $where, 'costes');
                    break;
                }
            }
        }
    }
}
