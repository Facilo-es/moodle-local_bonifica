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

namespace local_bonifica\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_bonifica\checklist;
use local_bonifica\data;
use local_bonifica\grid;

/**
 * Saves one participant cell as it's typed and returns the new state of the grid.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_cell extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'partid' => new external_value(PARAM_INT, 'Participant id'),
            'field' => new external_value(PARAM_ALPHANUMEXT, 'Field name or flag_<name>'),
            'value' => new external_value(PARAM_RAW, 'Value as typed'),
        ]);
    }

    /**
     * Saves the cell.
     *
     * @param int $partid
     * @param string $field
     * @param string $value
     * @return array
     */
    public static function execute(int $partid, string $field, string $value): array {
        global $DB;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['partid' => $partid, 'field' => $field, 'value' => $value]
        );
        $part = data::get_part($params['partid']);
        $group = data::get_group((int)$part->bgroupid);
        $courseid = (int)$DB->get_field('local_bonifica_action', 'courseid', ['id' => $group->actionid], MUST_EXIST);
        $action = data::get_action($courseid);
        $context = \context_course::instance($action->courseid);
        self::validate_context($context);
        require_capability('local/bonifica:manage', $context);

        grid::save($part, $params['field'], $params['value'], $context);

        $group = data::get_group((int)$group->id, $action);
        $state = grid::state($action, $group);
        $cells = [];
        foreach ($state['cells'] as $pid => $fields) {
            foreach ($fields as $f => $message) {
                $cells[] = ['partid' => $pid, 'field' => $f, 'message' => $message];
            }
        }
        return ['cells' => $cells, 'ok' => $state['ok'], 'n' => $state['n'], 'pct' => $state['pct'],
            'ready' => $state['ready'], 'sub' => $state['sub'], 'foot' => $state['foot'],
            'footshort' => $state['footshort'],
            'canexport' => checklist::run($action, [$group])['ready']];
    }

    /**
     * Result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cells' => new external_multiple_structure(new external_single_structure([
                'partid' => new external_value(PARAM_INT, 'Participant id'),
                'field' => new external_value(PARAM_ALPHANUMEXT, 'Field'),
                'message' => new external_value(PARAM_TEXT, 'Problem shown under the cell'),
            ])),
            'ok' => new external_value(PARAM_INT, 'Participants ready'),
            'n' => new external_value(PARAM_INT, 'Participants'),
            'pct' => new external_value(PARAM_INT, 'Percent ready'),
            'ready' => new external_value(PARAM_TEXT, 'Summary line'),
            'sub' => new external_value(PARAM_TEXT, 'Summary detail'),
            'foot' => new external_value(PARAM_TEXT, 'Footer line'),
            'footshort' => new external_value(PARAM_TEXT, 'Short footer line for phones'),
            'canexport' => new external_value(PARAM_BOOL, 'Whether the end file can be generated'),
        ]);
    }
}
