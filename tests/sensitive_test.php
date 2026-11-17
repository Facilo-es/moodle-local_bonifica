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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fin_fixture.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\ui::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\grid::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\data::class)]
/**
 * Test 19: special-category flags are hidden from teachers without viewsensitive, and still exported.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\ui
 * @covers     \local_bonifica\grid
 * @covers     \local_bonifica\data
 */
final class sensitive_test extends \advanced_testcase {
    use fin_fixture;

    /**
     * Flag names shown in each participants row.
     *
     * @param array $out ui::participants() output
     * @return array[]
     */
    protected static function shown(array $out): array {
        return array_map(fn($row) => array_column($row['flags'], 'flag'), $out['rows']);
    }

    /**
     * Test 19: a teacher sees no sensitive flags and can't change them; the file still carries them.
     */
    public function test_teacher_without_viewsensitive(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $part = array_values(data::get_parts($group->id))[0];
        data::save_part(['id' => $part->id, 'flags' => ['erte' => true, 'discapacidad' => true, 'terrorismo' => false,
            'violencia' => true, 'fijodiscontinuo' => false]]);
        $context = \context_course::instance($course->id);
        $url = new \moodle_url('/local/bonifica/course.php', ['id' => $course->id]);

        // Stored encrypted: the raw row shows neither the document nor the flags.
        $raw = $DB->get_record('local_bonifica_part', ['id' => $part->id]);
        $this->assertStringNotContainsString('discapacidad', $raw->flags);
        $this->assertStringNotContainsString($part->docnum, $raw->docnum);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertTrue(has_capability('local/bonifica:manage', $context));
        $this->assertFalse(has_capability('local/bonifica:viewsensitive', $context));
        $rows = self::shown(ui::participants($action, $group, $context, $url));
        $this->assertSame([array_values(array_diff(data::FLAGS, data::SENSITIVE))], array_map('array_values', $rows));
        foreach (data::SENSITIVE as $flag) {
            $this->assertNotContains($flag, $rows[0]);
        }

        // The teacher may change a plain flag, but not a sensitive one.
        grid::save(data::get_part($part->id), 'flag_fijodiscontinuo', '1', $context);
        $this->assertTrue(data::get_part($part->id)->flags['fijodiscontinuo']);
        try {
            grid::save(data::get_part($part->id), 'flag_discapacidad', '0', $context);
            $this->fail('A teacher changed a sensitive flag');
        } catch (\required_capability_exception $e) {
            $this->assertTrue(data::get_part($part->id)->flags['discapacidad']);
        }

        // The end file the teacher downloads still carries every flag.
        $result = checklist::run($action, $groups, 'organizadora');
        $this->assertTrue($result['ready']);
        $xp = new \DOMXPath(xml\builder_fin::build('organizadora', $result['data']));
        $this->assertSame('true', $xp->evaluate('string(//participante[1]/discapacidad)'));
        $this->assertSame('true', $xp->evaluate('string(//participante[1]/afectadosViolenciaGenero)'));
        $this->assertSame('false', $xp->evaluate('string(//participante[1]/afectadosTerrorismo)'));
    }

    /**
     * A manager sees and edits every flag.
     */
    public function test_manager_sees_flags(): void {
        $this->resetAfterTest();
        [$course, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $context = \context_course::instance($course->id);
        $manager = $this->getDataGenerator()->create_and_enrol($course, 'manager');
        $this->setUser($manager);
        $this->assertTrue(has_capability('local/bonifica:viewsensitive', $context));
        $rows = self::shown(ui::participants(
            $action,
            $group,
            $context,
            new \moodle_url('/local/bonifica/course.php', ['id' => $course->id])
        ));
        $this->assertSame(data::FLAGS, array_values($rows[0]));
        $part = array_values(data::get_parts($group->id))[0];
        grid::save($part, 'flag_violencia', '1', $context);
        $this->assertTrue(data::get_part($part->id)->flags['violencia']);
    }
}
