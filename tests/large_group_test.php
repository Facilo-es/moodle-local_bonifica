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

use local_bonifica\xml\builder_fin;
use local_bonifica\xml\schemas;
use local_bonifica\xml\validator;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\checklist::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\xml\builder_fin::class)]
/**
 * Test 22: a group of 500 participants is checked, generated and validated in under 3 seconds.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\checklist
 * @covers     \local_bonifica\xml\builder_fin
 */
final class large_group_test extends \advanced_testcase {
    use fin_fixture;

    /**
     * Test 22: 500 participants in under 3 s, free and Pro.
     */
    public function test_five_hundred_participants(): void {
        $this->resetAfterTest();
        [, $action, $groups] = $this->make_fixture([0], ['A00000000']);
        $group = reset($groups);
        for ($n = 1; $n <= 500; $n++) {
            data::save_part(self::part_fields($group->id, $n, ['horasasistidas' => 20]));
        }

        $editions = [false];
        foreach ($editions as $pro) {
            \cache_helper::purge_all();
            $start = microtime(true);
            $result = checklist::run($action, data::get_groups($action), 'organizadora');
            $doc = builder_fin::build('organizadora', $result['data']);
            $errors = validator::validate_dom($doc, schemas::path('fin', 'organizadora', 2026));
            $xml = $doc->saveXML();
            $seconds = microtime(true) - $start;

            $this->assertSame([], $result['issues']);
            $this->assertSame([], $errors);
            $this->assertSame(500, substr_count($xml, '<participante>'));
            $this->assertLessThan(3.0, $seconds, sprintf('%s took %.2f s', $pro ? 'Pro' : 'Free', $seconds));
        }
    }
}
