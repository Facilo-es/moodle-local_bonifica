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
 * Privacy API provider.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bonifica\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_bonifica\data;

/**
 * Describes, exports and deletes the personal data Bonifica keeps.
 *
 * Participant rows live in course contexts: part -> group (bgroupid) -> action (actionid) -> course.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Joins from a participant or export row to its course context. */
    const JOIN = "JOIN {local_bonifica_group} g ON g.id = x.bgroupid
                  JOIN {local_bonifica_action} a ON a.id = g.actionid
                  JOIN {context} ctx ON ctx.instanceid = a.courseid AND ctx.contextlevel = :ctxlevel";

    /**
     * Describes the data stored.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_bonifica_part', [
            'userid' => 'privacy:metadata:local_bonifica_part:userid',
            'nombre' => 'privacy:metadata:local_bonifica_part:nombre',
            'tipodoc' => 'privacy:metadata:local_bonifica_part:tipodoc',
            'docnum' => 'privacy:metadata:local_bonifica_part:docnum',
            'email' => 'privacy:metadata:local_bonifica_part:email',
            'telefono' => 'privacy:metadata:local_bonifica_part:telefono',
            'categoria' => 'privacy:metadata:local_bonifica_part:categoria',
            'nivelestudios' => 'privacy:metadata:local_bonifica_part:nivelestudios',
            'diploma' => 'privacy:metadata:local_bonifica_part:diploma',
            'flags' => 'privacy:metadata:local_bonifica_part:flags',
            'horasasistidas' => 'privacy:metadata:local_bonifica_part:horasasistidas',
            'finalizado' => 'privacy:metadata:local_bonifica_part:finalizado',
            'overridereason' => 'privacy:metadata:local_bonifica_part:overridereason',
            'overrideby' => 'privacy:metadata:local_bonifica_part:overrideby',
            'timemodified' => 'privacy:metadata:local_bonifica_part:timemodified',
        ], 'privacy:metadata:local_bonifica_part');
        $collection->add_database_table('local_bonifica_export', [
            'userid' => 'privacy:metadata:local_bonifica_export:userid',
            'kind' => 'privacy:metadata:local_bonifica_export:kind',
            'timecreated' => 'privacy:metadata:local_bonifica_export:timecreated',
            'sha256' => 'privacy:metadata:local_bonifica_export:sha256',
        ], 'privacy:metadata:local_bonifica_export');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        return $collection;
    }

    /**
     * Course contexts where the user is a participant, changed a result, or generated a file.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = ['ctxlevel' => CONTEXT_COURSE, 'u1' => $userid, 'u2' => $userid];
        $contextlist->add_from_sql("SELECT ctx.id FROM {local_bonifica_part} x " . self::JOIN .
            " WHERE x.userid = :u1 OR x.overrideby = :u2", $params);
        $contextlist->add_from_sql("SELECT ctx.id FROM {local_bonifica_export} x " . self::JOIN .
            " WHERE x.userid = :u1", ['ctxlevel' => CONTEXT_COURSE, 'u1' => $userid]);
        return $contextlist;
    }

    /**
     * Users with data in a course context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $params = ['ctxlevel' => CONTEXT_COURSE, 'ctxid' => $context->id];
        $userlist->add_from_sql('userid', "SELECT x.userid FROM {local_bonifica_part} x " . self::JOIN .
            " WHERE ctx.id = :ctxid AND x.userid IS NOT NULL", $params);
        $userlist->add_from_sql('overrideby', "SELECT x.overrideby FROM {local_bonifica_part} x " . self::JOIN .
            " WHERE ctx.id = :ctxid AND x.overrideby IS NOT NULL", $params);
        $userlist->add_from_sql('userid', "SELECT x.userid FROM {local_bonifica_export} x " . self::JOIN .
            " WHERE ctx.id = :ctxid", $params);
    }

    /**
     * FUNDAE group ids of a course context.
     *
     * @param \context $context
     * @return int[]
     */
    protected static function group_ids(\context $context): array {
        global $DB;
        if (!$context instanceof \context_course) {
            return [];
        }
        $sql = "SELECT g.id FROM {local_bonifica_group} g
                  JOIN {local_bonifica_action} a ON a.id = g.actionid
                 WHERE a.courseid = :courseid";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['courseid' => $context->instanceid]));
    }

    /**
     * Exports the user's data, with document numbers readable.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $out = ['participations' => [], 'resultchanges' => [], 'files' => []];
            foreach (self::group_ids($context) as $gid) {
                $idgrupo = $DB->get_field('local_bonifica_group', 'idgrupo', ['id' => $gid]);
                foreach (data::get_parts($gid) as $p) {
                    if ((int)$p->userid === (int)$userid) {
                        $out['participations'][] = (object)[
                            'idgrupo' => $idgrupo,
                            'nombre' => $p->nombre,
                            'tipodoc' => $p->tipodoc,
                            'docnum' => $p->docnum,
                            'email' => $p->email,
                            'telefono' => $p->telefono,
                            'categoria' => $p->categoria,
                            'nivelestudios' => $p->nivelestudios,
                            'diploma' => $p->diploma,
                            'flags' => array_filter($p->flags, fn($v) => $v !== null),
                            'horasasistidas' => $p->horasasistidas,
                            'finalizado' => $p->finalizado === null ? null : transform::yesno($p->finalizado),
                            'overridereason' => $p->overridereason,
                            'timemodified' => transform::datetime($p->timemodified),
                        ];
                    }
                    if ((int)$p->overrideby === (int)$userid) {
                        $out['resultchanges'][] = (object)[
                            'idgrupo' => $idgrupo,
                            'participant' => $p->id,
                            'overridereason' => $p->overridereason,
                            'timemodified' => transform::datetime($p->timemodified),
                        ];
                    }
                }
                foreach ($DB->get_records('local_bonifica_export', ['bgroupid' => $gid, 'userid' => $userid], 'id') as $e) {
                    $out['files'][] = (object)[
                        'idgrupo' => $idgrupo,
                        'kind' => $e->kind,
                        'schemayear' => $e->schemayear,
                        'timecreated' => transform::datetime($e->timecreated),
                        'sha256' => $e->sha256,
                    ];
                }
            }
            $out = array_filter($out);
            if ($out) {
                writer::with_context($context)->export_data([get_string('pluginname', 'local_bonifica')], (object)$out);
            }
        }
    }

    /**
     * Removes the data of some users in a list of group ids.
     *
     * @param int[] $groupids
     * @param int[] $userids
     */
    protected static function delete_users_in_groups(array $groupids, array $userids): void {
        global $DB;
        if (!$groupids || !$userids) {
            return;
        }
        [$gsql, $gparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'g');
        [$usql, $uparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params = $gparams + $uparams;
        $DB->delete_records_select('local_bonifica_part', "bgroupid $gsql AND userid $usql", $params);
        $DB->set_field_select('local_bonifica_part', 'overrideby', null, "bgroupid $gsql AND overrideby $usql", $params);
        $DB->set_field_select('local_bonifica_export', 'userid', 0, "bgroupid $gsql AND userid $usql", $params);
    }

    /**
     * Deletes all users' data in a course context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $groupids = self::group_ids($context);
        if (!$groupids) {
            return;
        }
        [$gsql, $params] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_bonifica_part', "bgroupid $gsql", $params);
        $DB->set_field_select('local_bonifica_export', 'userid', 0, "bgroupid $gsql", $params);
    }

    /**
     * Deletes one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_users_in_groups(self::group_ids($context), [$userid]);
        }
    }

    /**
     * Deletes the data of several users in one context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        self::delete_users_in_groups(self::group_ids($userlist->get_context()), array_map('intval', $userlist->get_userids()));
    }
}
