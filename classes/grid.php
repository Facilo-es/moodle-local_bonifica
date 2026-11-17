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

/**
 * Per-cell problems and the summary of the participants grid.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grid {
    /** Fields a coordinator can edit in the grid. */
    const FIELDS = ['nombre', 'tipodoc', 'docnum', 'email', 'telefono', 'categoria', 'nivelestudios', 'diploma'];

    /**
     * Problems per participant and field, plus the summary texts.
     *
     * @param \stdClass $action
     * @param \stdClass $group
     * @return array ['cells' => [partid => [field => message]], 'ok' => int, 'n' => int, 'ready' => string,
     *               'sub' => string, 'foot' => string, 'footshort' => string, 'pct' => int, 'allready' => bool]
     */
    public static function state(\stdClass $action, \stdClass $group): array {
        $result = checklist::run($action, [$group]);
        $cells = [];
        foreach ($result['issues'] as $issue) {
            if (empty($issue['fix']['partid'])) {
                continue;
            }
            $pid = $issue['fix']['partid'];
            $field = $issue['fix']['field'];
            if (!isset($cells[$pid][$field])) {
                $cells[$pid][$field] = self::short($issue['message']);
            }
        }
        $parts = data::get_parts($group->id);
        $n = count($parts);
        $bad = count(array_intersect(array_keys($cells), array_keys($parts)));
        $ok = $n - $bad;
        $p = 'local_bonifica';
        $year = data::schema_year();
        if (!$n) {
            $ready = get_string('ready_none', $p);
            $sub = get_string('ready_none_sub', $p);
            $foot = '';
            $footshort = '';
        } else if ($bad) {
            $ready = get_string('ready_some', $p, (object)['ok' => $ok, 'n' => $n]);
            $sub = $bad === 1 ? get_string('missing_one', $p) : get_string('missing_many', $p, $bad);
            $foot = $bad === 1 ? get_string('foot_one', $p) : get_string('foot_many', $p, $bad);
            $footshort = $bad === 1 ? get_string('foot_short_one', $p) : get_string('foot_short_many', $p, $bad);
        } else {
            $ready = get_string('ready_all', $p, $n);
            $sub = get_string('ready_sub', $p, $year);
            $foot = get_string('ready', $p, $year);
            $footshort = get_string('foot_short_ready', $p);
        }
        return ['cells' => $cells, 'ok' => $ok, 'n' => $n, 'ready' => $ready, 'sub' => $sub, 'foot' => $foot,
            'footshort' => $footshort, 'pct' => $n ? (int)round(100 * $ok / $n) : 0, 'allready' => $n && !$bad];
    }

    /**
     * Turns "Participante 4: el NIF ... no es válido." into "El NIF ... no es válido." for a cell.
     *
     * @param string $message
     * @return string
     */
    public static function short(string $message): string {
        $text = preg_replace('/^[^:]+:\s*/u', '', $message, 1);
        return \core_text::strtoupper(\core_text::substr($text, 0, 1)) . \core_text::substr($text, 1);
    }

    /**
     * Saves one cell, as typed. Checks happen in the checklist, so a bad value is kept and shown in red.
     *
     * @param \stdClass $part
     * @param string $field a FIELDS entry or flag_<name>
     * @param string $value
     * @param \context $context
     */
    public static function save(\stdClass $part, string $field, string $value, \context $context): void {
        $value = trim($value);
        if (strpos($field, 'flag_') === 0) {
            $flag = substr($field, 5);
            if (!in_array($flag, data::FLAGS, true)) {
                throw new \invalid_parameter_exception('field');
            }
            if (in_array($flag, data::SENSITIVE, true)) {
                require_capability('local/bonifica:viewsensitive', $context);
            }
            $flags = $part->flags;
            $flags[$flag] = $value === '1' ? true : null;
            data::save_part(['id' => $part->id, 'flags' => $flags]);
            return;
        }
        if (!in_array($field, self::FIELDS, true)) {
            throw new \invalid_parameter_exception('field');
        }
        switch ($field) {
            case 'tipodoc':
                $value = in_array((int)$value, [rules\nif::TYPE_NIF, rules\nif::TYPE_PASSPORT, rules\nif::TYPE_NIE], true)
                    ? (int)$value : rules\nif::TYPE_NIF;
                break;
            case 'categoria':
            case 'nivelestudios':
                $value = $value === '' ? null : (int)$value;
                break;
            case 'diploma':
                $value = in_array($value, ['S', 'N'], true) ? $value : null;
                break;
            case 'nombre':
            case 'email':
                $value = clean_param(\core_text::substr($value, 0, 255), PARAM_TEXT);
                break;
            case 'docnum':
                $value = \core_text::substr($value, 0, 20);
                break;
        }
        data::save_part(['id' => $part->id, $field => $value]);
    }
}
