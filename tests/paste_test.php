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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\paste::class)]
/**
 * Test 10: pasting participants from a spreadsheet.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\paste
 */
final class paste_test extends \advanced_testcase {
    use fin_fixture;

    /**
     * Row 12's document: participant 1012's NIF with a wrong letter.
     *
     * @return string
     */
    protected static function baddoc(): string {
        $doc = self::nif(1012);
        return substr($doc, 0, 8) . (substr($doc, 8) === 'A' ? 'B' : 'A');
    }

    /**
     * A pasted sheet: header and 30 rows, row 12 with a wrong letter and row 20 repeating row 5.
     *
     * @param string $sep
     * @return string
     */
    protected static function sheet(string $sep): string {
        $lines = [implode($sep, ['Nombre', 'NIF', 'Correo', 'Teléfono', 'Categoría', 'Nivel de estudios', 'Diploma'])];
        for ($k = 1; $k <= 30; $k++) {
            $doc = self::nif(1000 + $k);
            if ($k === 12) {
                $doc = self::baddoc();
            } else if ($k === 20) {
                $doc = self::nif(1005);
            }
            $lines[] = implode($sep, ['Participante ' . $k, $doc, 'p' . $k . '@example.com', '600 00 00 ' . sprintf('%02d', $k),
                '3', '4', 'No']);
        }
        return implode("\r\n", $lines) . "\n";
    }

    /**
     * Test 10: 30 rows, one bad NIF and one duplicate: 28 added, 2 listed with their line numbers.
     */
    public function test_paste_thirty_rows(): void {
        $this->resetAfterTest();
        [, , $groups] = $this->make_fixture([0], ['A00000000']);
        $group = reset($groups);

        foreach (["\t", ';'] as $sep) {
            $result = paste::parse(self::sheet($sep), $group->id);
            $this->assertCount(28, $result['rows']);
            $this->assertSame([13, 21], array_column($result['errors'], 'line'));
            $this->assertSame(get_string('paste_baddoc', 'local_bonifica', self::baddoc()), $result['errors'][0]['message']);
            $this->assertSame(get_string('paste_duplicate', 'local_bonifica', self::nif(1005)), $result['errors'][1]['message']);
        }

        $first = $result['rows'][0];
        $this->assertSame('Participante 1', $first['nombre']);
        $this->assertSame(self::nif(1001), $first['docnum']);
        $this->assertSame(rules\nif::TYPE_NIF, $first['tipodoc']);
        $this->assertSame('N', $first['diploma']);

        // Saved the way the participants tab does it.
        foreach ($result['rows'] as $row) {
            data::save_part(['bgroupid' => $group->id] + $row);
        }
        $parts = data::get_parts($group->id);
        $this->assertCount(28, $parts);
        $this->assertSame('600000001', array_values($parts)[0]->telefono);

        // Pasting the same sheet again adds nobody: every document is already in the group.
        $again = paste::parse(self::sheet("\t"), $group->id);
        $this->assertSame([], $again['rows']);
        $this->assertCount(30, $again['errors']);
    }

    /**
     * Without a document column nothing is read, and line 1 says why.
     */
    public function test_paste_without_header(): void {
        $this->resetAfterTest();
        [, , $groups] = $this->make_fixture([0], ['A00000000']);
        $result = paste::parse("Participante 1\tp1@example.com\n", reset($groups)->id);
        $this->assertSame([], $result['rows']);
        $this->assertSame(1, $result['errors'][0]['line']);
    }
}
