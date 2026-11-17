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
 * Reads and writes Bonifica's tables. Document numbers and sensitive flags are encrypted at rest.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data {
    /** @var string[] Optional participant flags, in schema order. */
    const FLAGS = ['erte', 'discapacidad', 'terrorismo', 'violencia', 'fijodiscontinuo'];

    /** @var string[] Flags that are special-category data, shown only with viewsensitive. */
    const SENSITIVE = ['discapacidad', 'terrorismo', 'violencia'];

    /**
     * The FUNDAE action of a course, or null.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_action(int $courseid): ?\stdClass {
        global $DB;
        $rec = $DB->get_record('local_bonifica_action', ['courseid' => $courseid]);
        if ($rec) {
            $rec->datos = json_decode($rec->datos ?? '', true) ?: [];
        }
        return $rec ?: null;
    }

    /**
     * Creates or updates the action of a course.
     *
     * @param int $courseid
     * @param array $fields
     * @return \stdClass the saved action
     */
    public static function save_action(int $courseid, array $fields): \stdClass {
        global $DB;
        $now = time();
        $rec = $DB->get_record('local_bonifica_action', ['courseid' => $courseid]);
        if (isset($fields['datos']) && is_array($fields['datos'])) {
            $old = $rec ? (json_decode($rec->datos ?? '', true) ?: []) : [];
            $fields['datos'] = json_encode(array_merge($old, $fields['datos']));
        }
        if ($rec) {
            foreach ($fields as $k => $v) {
                $rec->$k = $v;
            }
            $rec->timemodified = $now;
            $DB->update_record('local_bonifica_action', $rec);
        } else {
            $rec = (object)array_merge(['modalidad' => 'pr', 'nivel' => 'b', 'horaspr' => 0, 'horaste' => 0], $fields);
            $rec->courseid = $courseid;
            $rec->timecreated = $now;
            $rec->timemodified = $now;
            $rec->id = $DB->insert_record('local_bonifica_action', $rec);
        }
        self::sync_groups($rec);
        return self::get_action($courseid);
    }

    /**
     * Makes one FUNDAE group per Moodle group, or one for the whole course when it has none.
     *
     * Groups are only added, never removed, so no data is lost when a Moodle group is deleted.
     *
     * @param \stdClass $action
     */
    public static function sync_groups(\stdClass $action): void {
        global $DB;
        $mgroups = groups_get_all_groups($action->courseid, 0, 0, 'g.id, g.name', false, false);
        $have = $DB->get_records_menu('local_bonifica_group', ['actionid' => $action->id], '', 'groupid, id');
        $wanted = $mgroups ? array_keys($mgroups) : [0];
        $n = count($have);
        foreach ($wanted as $gid) {
            if (!isset($have[$gid])) {
                $n++;
                $DB->insert_record('local_bonifica_group', (object)[
                    'actionid' => $action->id, 'groupid' => $gid, 'idgrupo' => (string)$n, 'timemodified' => time(),
                ]);
            }
        }
    }

    /**
     * FUNDAE groups of an action, with their display name and participant count.
     *
     * @param \stdClass $action
     * @return \stdClass[]
     */
    public static function get_groups(\stdClass $action): array {
        global $DB;
        $sql = "SELECT g.*, mg.name AS mname, (SELECT COUNT(1) FROM {local_bonifica_part} p WHERE p.bgroupid = g.id) AS nparts
                  FROM {local_bonifica_group} g
             LEFT JOIN {groups} mg ON mg.id = g.groupid
                 WHERE g.actionid = :actionid
              ORDER BY g.id";
        $groups = $DB->get_records_sql($sql, ['actionid' => $action->id]);
        foreach ($groups as $g) {
            self::decorate_group($g);
        }
        return $groups;
    }

    /**
     * One FUNDAE group, checked against its action.
     *
     * @param int $id
     * @param \stdClass|null $action when given, the group must belong to it
     * @return \stdClass
     */
    public static function get_group(int $id, ?\stdClass $action = null): \stdClass {
        global $DB;
        $params = ['id' => $id];
        if ($action) {
            $params['actionid'] = $action->id;
        }
        $g = $DB->get_record('local_bonifica_group', $params, '*', MUST_EXIST);
        $g->mname = $g->groupid ? $DB->get_field('groups', 'name', ['id' => $g->groupid]) : null;
        $g->nparts = $DB->count_records('local_bonifica_part', ['bgroupid' => $g->id]);
        self::decorate_group($g);
        return $g;
    }

    /**
     * Adds the decoded JSON and a display name to a group record.
     *
     * @param \stdClass $g
     */
    protected static function decorate_group(\stdClass $g): void {
        $g->datos = is_array($g->datos ?? null) ? $g->datos : (json_decode($g->datos ?? '', true) ?: []);
        $g->name = $g->mname ?: get_string('wholecourse', 'local_bonifica');
    }

    /**
     * Saves group fields.
     *
     * @param int $id
     * @param array $fields
     */
    public static function save_group(int $id, array $fields): void {
        global $DB;
        if (isset($fields['datos']) && is_array($fields['datos'])) {
            $old = json_decode($DB->get_field('local_bonifica_group', 'datos', ['id' => $id]) ?? '', true) ?: [];
            $fields['datos'] = json_encode(array_merge($old, $fields['datos']));
        }
        $fields['id'] = $id;
        $fields['timemodified'] = time();
        $DB->update_record('local_bonifica_group', (object)$fields);
    }

    /**
     * Encrypts a value for storage. Empty stays empty.
     *
     * @param string|null $value
     * @return string|null
     */
    public static function encrypt(?string $value): ?string {
        if ($value === null || $value === '') {
            return $value;
        }
        return \core\encryption::encrypt($value);
    }

    /**
     * Decrypts a stored value. Values saved before encryption was possible are returned as they are.
     *
     * @param string|null $value
     * @return string
     */
    public static function decrypt(?string $value): string {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return \core\encryption::decrypt($value);
        } catch (\moodle_exception $e) {
            return $value;
        }
    }

    /**
     * Participants of a group, decrypted, in the order they were added.
     *
     * @param int $bgroupid
     * @return \stdClass[]
     */
    public static function get_parts(int $bgroupid): array {
        global $DB;
        $parts = $DB->get_records('local_bonifica_part', ['bgroupid' => $bgroupid], 'id');
        foreach ($parts as $p) {
            self::decode_part($p);
        }
        return $parts;
    }

    /**
     * One participant, decrypted.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get_part(int $id): \stdClass {
        global $DB;
        $p = $DB->get_record('local_bonifica_part', ['id' => $id], '*', MUST_EXIST);
        self::decode_part($p);
        return $p;
    }

    /**
     * Decrypts a participant record in place.
     *
     * @param \stdClass $p
     */
    protected static function decode_part(\stdClass $p): void {
        $p->docnum = self::decrypt($p->docnum);
        $flags = json_decode(self::decrypt($p->flags), true) ?: [];
        $p->flags = [];
        foreach (self::FLAGS as $f) {
            $p->flags[$f] = $flags[$f] ?? null;
        }
    }

    /**
     * Adds or updates a participant. Values are stored as given: the checklist reports what's wrong.
     *
     * @param array $fields with docnum and flags in clear
     * @return int participant id
     */
    public static function save_part(array $fields): int {
        global $DB;
        if (array_key_exists('docnum', $fields)) {
            $fields['docnum'] = self::encrypt(nif::clean($fields['docnum']));
        }
        if (array_key_exists('flags', $fields)) {
            $flags = [];
            foreach (self::FLAGS as $f) {
                if (isset($fields['flags'][$f]) && $fields['flags'][$f] !== null && $fields['flags'][$f] !== '') {
                    $flags[$f] = (bool)$fields['flags'][$f];
                }
            }
            $fields['flags'] = $flags ? self::encrypt(json_encode($flags)) : null;
        }
        if (array_key_exists('telefono', $fields)) {
            $fields['telefono'] = fields::phone($fields['telefono'])
                ?? \core_text::substr(trim((string)$fields['telefono']), 0, 12);
        }
        $fields['timemodified'] = time();
        if (!empty($fields['id'])) {
            $DB->update_record('local_bonifica_part', (object)$fields);
            return (int)$fields['id'];
        }
        unset($fields['id']);
        return $DB->insert_record('local_bonifica_part', (object)$fields);
    }

    /**
     * Whether a document number is already in a group.
     *
     * @param int $bgroupid
     * @param string $docnum
     * @param int $exceptid participant to ignore
     * @return bool
     */
    public static function doc_taken(int $bgroupid, string $docnum, int $exceptid = 0): bool {
        $docnum = nif::clean($docnum);
        if ($docnum === '') {
            return false;
        }
        foreach (self::get_parts($bgroupid) as $p) {
            if ($p->id != $exceptid && $p->docnum === $docnum) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cost rows of a group.
     *
     * @param int $bgroupid
     * @return \stdClass[]
     */
    public static function get_costs(int $bgroupid): array {
        global $DB;
        $costs = $DB->get_records('local_bonifica_cost', ['bgroupid' => $bgroupid], 'id');
        foreach ($costs as $c) {
            $c->periodos = json_decode($c->periodos ?? '', true) ?: [];
            $c->company = $c->cif ? $DB->get_record('local_bonifica_company', ['cif' => $c->cif]) : null;
        }
        return $costs;
    }

    /**
     * Adds or updates a cost row.
     *
     * @param array $fields
     * @return int
     */
    public static function save_cost(array $fields): int {
        global $DB;
        if (isset($fields['periodos']) && is_array($fields['periodos'])) {
            $fields['periodos'] = json_encode(array_values($fields['periodos']));
        }
        if (isset($fields['cif'])) {
            $fields['cif'] = nif::clean($fields['cif']);
        }
        if (!empty($fields['id'])) {
            $DB->update_record('local_bonifica_cost', (object)$fields);
            return (int)$fields['id'];
        }
        unset($fields['id']);
        return $DB->insert_record('local_bonifica_cost', (object)$fields);
    }

    /**
     * Creates or updates a client company.
     *
     * @param string $cif
     * @param array $fields razonsocial, plantilla, inforlt
     */
    public static function save_company(string $cif, array $fields): void {
        global $DB;
        $cif = nif::clean($cif);
        $rec = $DB->get_record('local_bonifica_company', ['cif' => $cif]);
        $fields['timemodified'] = time();
        if ($rec) {
            $fields['id'] = $rec->id;
            $DB->update_record('local_bonifica_company', (object)$fields);
        } else {
            $fields['cif'] = $cif;
            $DB->insert_record('local_bonifica_company', (object)$fields);
        }
    }

    /**
     * The role chosen in settings: organizadora, bonificada or gruposempresa.
     *
     * @return string
     */
    public static function role(): string {
        $role = get_config('local_bonifica', 'role');
        return in_array($role, ['organizadora', 'bonificada', 'gruposempresa']) ? $role : 'organizadora';
    }

    /**
     * The schema year chosen in settings.
     *
     * @return int
     */
    public static function schema_year(): int {
        $year = (int)get_config('local_bonifica', 'schemayear');
        return in_array($year, xml\schemas::years()) ? $year : max(xml\schemas::years());
    }

    /**
     * Cost rows of every group, for the company lists of the Pro forms.
     *
     * @param \stdClass[] $groups
     * @return \stdClass[] one row per company
     */
    public static function all_costs(array $groups): array {
        $out = [];
        foreach ($groups as $g) {
            foreach (self::get_costs($g->id) as $c) {
                if ($c->cif !== '' && $c->cif !== null) {
                    $out[$c->cif] = $c;
                }
            }
        }
        return array_values($out);
    }

    /**
     * Tutors typed in any group of the action, by user, to fill the gaps of the start file.
     *
     * @param \stdClass[] $groups
     * @return array userid => tutor fields
     */
    public static function tutors(array $groups): array {
        $out = [];
        foreach ($groups as $g) {
            foreach (['tutores_pr', 'tutores_te'] as $k) {
                foreach ((array)($g->datos[$k] ?? []) as $t) {
                    if (!empty($t['userid'])) {
                        $uid = (int)$t['userid'];
                        $out[$uid] = array_filter((array)$t, fn($v) => $v !== '' && $v !== null) + ($out[$uid] ?? []);
                    }
                }
            }
        }
        return $out;
    }
}
