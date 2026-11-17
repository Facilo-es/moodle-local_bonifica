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
 * Tests for the privacy provider (test 18).
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bonifica\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_bonifica\data;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\privacy\provider::class)]
/**
 * Privacy provider tests.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass[] */
    protected $users = [];
    /** @var \context_course[] */
    protected $contexts = [];
    /** @var int[] FUNDAE group id per course index */
    protected $groups = [];

    /**
     * Two courses with an action each; user 0 and 1 in course 0, user 1 also in course 1.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $gen->create_user();
        }
        for ($c = 0; $c < 2; $c++) {
            $course = $gen->create_course();
            $this->contexts[$c] = \context_course::instance($course->id);
            $action = data::save_action($course->id, ['idaccion' => (string)($c + 1), 'nombre' => 'Acción ' . $c]);
            $this->groups[$c] = (int)$DB->get_field('local_bonifica_group', 'id', ['actionid' => $action->id]);
        }
        data::save_part(['bgroupid' => $this->groups[0], 'userid' => $this->users[0]->id, 'nombre' => 'Ana',
            'docnum' => '12345678Z', 'flags' => ['erte' => 1]]);
        data::save_part(['bgroupid' => $this->groups[0], 'userid' => $this->users[1]->id, 'nombre' => 'Luis',
            'docnum' => 'X1234567L', 'finalizado' => 1, 'overridereason' => 'Asistió', 'overrideby' => $this->users[2]->id]);
        data::save_part(['bgroupid' => $this->groups[1], 'userid' => $this->users[1]->id, 'nombre' => 'Luis',
            'docnum' => 'X1234567L']);
        $DB->insert_record('local_bonifica_export', (object)['bgroupid' => $this->groups[0], 'kind' => 'fin',
            'schemayear' => 2026, 'userid' => $this->users[2]->id, 'timecreated' => time(), 'sha256' => str_repeat('a', 64)]);
    }

    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('local_bonifica'))->get_collection();
        $this->assertCount(3, $items);
    }

    public function test_get_contexts_for_userid(): void {
        $ids = provider::get_contexts_for_userid($this->users[0]->id)->get_contextids();
        $this->assertEquals([$this->contexts[0]->id], array_values($ids));
        $ids = provider::get_contexts_for_userid($this->users[1]->id)->get_contextids();
        sort($ids);
        $this->assertEquals([$this->contexts[0]->id, $this->contexts[1]->id], $ids);
        // The user who changed a result and generated a file.
        $ids = provider::get_contexts_for_userid($this->users[2]->id)->get_contextids();
        $this->assertEquals([$this->contexts[0]->id], array_values(array_unique($ids)));
    }

    public function test_export_user_data(): void {
        $this->export_all_data_for_user($this->users[0]->id, 'local_bonifica');
        $w = writer::with_context($this->contexts[0]);
        $this->assertTrue($w->has_any_data());
        $data = $w->get_data([get_string('pluginname', 'local_bonifica')]);
        $this->assertCount(1, $data->participations);
        $this->assertEquals('12345678Z', $data->participations[0]->docnum);
        $this->assertEquals('Ana', $data->participations[0]->nombre);
        $this->assertTrue($data->participations[0]->flags['erte']);
        $this->assertFalse(writer::with_context($this->contexts[1])->has_any_data());

        writer::reset();
        $this->export_all_data_for_user($this->users[2]->id, 'local_bonifica');
        $data = writer::with_context($this->contexts[0])->get_data([get_string('pluginname', 'local_bonifica')]);
        $this->assertCount(1, $data->resultchanges);
        $this->assertCount(1, $data->files);
        $this->assertObjectNotHasProperty('participations', $data);
    }

    public function test_delete_data_for_user(): void {
        global $DB;
        $list = new approved_contextlist($this->users[1], 'local_bonifica', [$this->contexts[0]->id]);
        provider::delete_data_for_user($list);
        $this->assertEquals(0, $DB->count_records('local_bonifica_part', ['bgroupid' => $this->groups[0],
            'userid' => $this->users[1]->id]));
        $this->assertEquals(1, $DB->count_records('local_bonifica_part', ['userid' => $this->users[0]->id]));
        $this->assertEquals(1, $DB->count_records('local_bonifica_part', ['bgroupid' => $this->groups[1],
            'userid' => $this->users[1]->id]));

        $list = new approved_contextlist($this->users[2], 'local_bonifica', [$this->contexts[0]->id]);
        provider::delete_data_for_user($list);
        $this->assertEquals(0, $DB->count_records('local_bonifica_part', ['overrideby' => $this->users[2]->id]));
        $this->assertEquals(0, $DB->count_records('local_bonifica_export', ['userid' => $this->users[2]->id]));
        $this->assertEquals(1, $DB->count_records('local_bonifica_export', []));
    }

    public function test_delete_on_user_deletion(): void {
        global $DB;
        // Moodle's user deletion runs the privacy delete for every context of the user.
        $contextlist = provider::get_contexts_for_userid($this->users[1]->id);
        provider::delete_data_for_user(new approved_contextlist(
            $this->users[1],
            'local_bonifica',
            $contextlist->get_contextids()
        ));
        $this->assertEquals(0, $DB->count_records('local_bonifica_part', ['userid' => $this->users[1]->id]));
    }

    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        provider::delete_data_for_all_users_in_context($this->contexts[0]);
        $this->assertEquals(0, $DB->count_records('local_bonifica_part', ['bgroupid' => $this->groups[0]]));
        $this->assertEquals(1, $DB->count_records('local_bonifica_part', ['bgroupid' => $this->groups[1]]));
        $this->assertEquals(0, $DB->count_records('local_bonifica_export', ['userid' => $this->users[2]->id]));
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertEquals(1, $DB->count_records('local_bonifica_part', ['bgroupid' => $this->groups[1]]));
    }

    public function test_users_in_context_and_delete_for_users(): void {
        global $DB;
        $userlist = new userlist($this->contexts[0], 'local_bonifica');
        provider::get_users_in_context($userlist);
        $ids = $userlist->get_userids();
        sort($ids);
        $expected = [$this->users[0]->id, $this->users[1]->id, $this->users[2]->id];
        sort($expected);
        $this->assertEquals($expected, $ids);

        $approved = new approved_userlist($this->contexts[0], 'local_bonifica', [$this->users[0]->id]);
        provider::delete_data_for_users($approved);
        $this->assertEquals(0, $DB->count_records('local_bonifica_part', ['userid' => $this->users[0]->id]));
        $this->assertEquals(2, $DB->count_records('local_bonifica_part', ['userid' => $this->users[1]->id]));
    }
}
