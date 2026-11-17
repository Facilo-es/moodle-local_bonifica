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

global $CFG;
require_once(__DIR__ . '/fixtures/fin_fixture.php');
require_once($CFG->libdir . '/formslib.php');

use local_bonifica\rules\fields;
use local_bonifica\rules\nif;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\rules\fields::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\rules\nif::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\checklist::class)]
/**
 * Tests 4 to 9: field rules for phones, documents, action and group numbers and amounts.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\rules\fields
 * @covers     \local_bonifica\rules\nif
 * @covers     \local_bonifica\checklist
 */
final class rules_test extends \advanced_testcase {
    use fin_fixture;

    /**
     * Messages of the blocking issues of a checklist run.
     *
     * @param array $result
     * @return string[]
     */
    protected static function messages(array $result): array {
        return array_column($result['issues'], 'message');
    }

    /**
     * Test 4: "+34 612 34 56 78" is cleaned to 612345678 and stored that way.
     */
    public function test_phone_with_prefix_is_cleaned(): void {
        $this->resetAfterTest();
        $this->assertSame('612345678', fields::phone('+34 612 34 56 78'));
        $this->assertSame('612345678', fields::phone('0034 612.34.56.78'));
        $this->assertSame('912345678', fields::phone('(91) 234-56-78'));

        [, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $part = array_values(data::get_parts($group->id))[0];
        data::save_part(['id' => $part->id, 'telefono' => '+34 612 34 56 78']);
        $this->assertSame('612345678', data::get_part($part->id)->telefono);
        $result = checklist::run($action, $groups, 'organizadora');
        $this->assertTrue($result['ready']);
    }

    /**
     * Test 5: "61234" is too short; the checklist blocks the file and names the participant.
     */
    public function test_short_phone_is_blocked(): void {
        $this->resetAfterTest();
        $this->assertNull(fields::phone('61234'));
        $this->assertNull(fields::phone('6123456789012'));

        [, $action, $groups] = $this->make_fixture([2], ['A00000000']);
        $group = reset($groups);
        $second = array_values(data::get_parts($group->id))[1];
        data::save_part(['id' => $second->id, 'telefono' => '61234']);
        $result = checklist::run($action, $groups, 'organizadora');
        $this->assertFalse($result['ready']);
        $expected = get_string('chk_phone', 'local_bonifica', (object)['n' => 2]);
        $this->assertContains($expected, self::messages($result));
        $phone = array_values(array_filter($result['issues'], fn($i) => $i['message'] === $expected))[0];
        $this->assertSame(['partid' => (int)$second->id, 'field' => 'telefono', 'value' => '61234'], $phone['fix']);

        // The group's own contact phone is refused by the form too.
        $form = new form\group(null, null, 'post', '', null, true, ['idgrupo' => '1', 'telefono' => '61234']);
        $errors = $form->validation(['idgrupo' => '1', 'telefono' => '61234'], []);
        $this->assertSame(get_string('err_phone', 'local_bonifica'), $errors['telefono']);
    }

    /**
     * Test 6: NIF and NIE letters.
     */
    public function test_nif_and_nie(): void {
        $this->assertTrue(nif::valid_nif('12345678Z'));
        $this->assertFalse(nif::valid_nif('12345678A'));
        $this->assertTrue(nif::valid_nie('X1234567L'));
        $this->assertTrue(nif::valid_nie('Y1234567X'));
        $this->assertTrue(nif::valid_document(nif::TYPE_NIF, '12345678Z'));
        $this->assertFalse(nif::valid_document(nif::TYPE_NIF, '12345678A'));
        $this->assertTrue(nif::valid_document(nif::TYPE_NIE, 'X1234567L'));
        $this->assertTrue(nif::valid_document(nif::TYPE_NIE, 'Y1234567X'));
        $this->assertFalse(nif::valid_document(nif::TYPE_NIE, 'Y1234567A'));
        // Codes follow the 2026 XSD: 10 NIF, 20 passport, 60 NIE.
        $this->assertSame(10, nif::TYPE_NIF);
        $this->assertSame(20, nif::TYPE_PASSPORT);
        $this->assertSame(60, nif::TYPE_NIE);
        // Company CIF: FUNDAE's sample B00000000 only in test mode.
        $this->assertTrue(nif::valid_cif('B00000000', true));
        $this->assertFalse(nif::valid_cif('B00000000', false));
    }

    /**
     * Test 6 in the checklist: an invalid letter blocks the file with a message for that participant.
     */
    public function test_bad_nif_blocks_file(): void {
        $this->resetAfterTest();
        [, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $part = array_values(data::get_parts($group->id))[0];
        data::save_part(['id' => $part->id, 'tipodoc' => nif::TYPE_NIF, 'docnum' => '12345678A']);
        $result = checklist::run($action, $groups, 'organizadora');
        $this->assertFalse($result['ready']);
        $this->assertSame('docs', $result['issues'][0]['check']);
        $this->assertStringContainsString('12345678A', $result['issues'][0]['message']);
    }

    /**
     * Test 7: idAccion "123456" is refused; leading zeros are kept.
     */
    public function test_idaccion(): void {
        $this->resetAfterTest();
        $this->assertFalse(fields::idaccion('123456'));
        $this->assertTrue(fields::idaccion('00002'));
        $this->assertFalse(fields::idaccion('12a'));

        $form = new form\action(null, null, 'post', '', null, true, []);
        $errors = $form->validation(['idaccion' => '123456', 'horaspr' => '20', 'horaste' => ''], []);
        $this->assertSame(get_string('err_idaccion', 'local_bonifica'), $errors['idaccion']);
        $errors = $form->validation(['idaccion' => '00002', 'horaspr' => '20', 'horaste' => ''], []);
        $this->assertArrayNotHasKey('idaccion', $errors);

        $action = (object)['idaccion' => '123456', 'nombre' => 'Ventas que cierran', 'horaspr' => 20, 'horaste' => 0];
        $result = checklist::run($action, [], 'organizadora');
        $this->assertContains(get_string('chk_idaccion', 'local_bonifica'), self::messages($result));
        $this->assertFalse($result['ready']);
    }

    /**
     * Test 8: idGrupo "123456" is refused, 5 characters at most.
     */
    public function test_idgrupo(): void {
        $this->resetAfterTest();
        $this->assertFalse(fields::idgrupo('123456'));
        $this->assertTrue(fields::idgrupo('12345'));

        $form = new form\group(null, null, 'post', '', null, true, []);
        $errors = $form->validation(['idgrupo' => '123456', 'telefono' => ''], []);
        $this->assertSame(get_string('err_idgrupo', 'local_bonifica'), $errors['idgrupo']);

        [, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $group->idgrupo = '123456';
        $result = checklist::run($action, [$group->id => $group], 'organizadora');
        $this->assertContains(get_string('chk_idgrupo', 'local_bonifica', '123456'), self::messages($result));
    }

    /**
     * Test 9: amounts "12.345", "-1" and "10000000" are all refused.
     */
    public function test_amounts(): void {
        $this->resetAfterTest();
        foreach (['12.345', '-1', '10000000'] as $bad) {
            $this->assertNull(fields::amount($bad), $bad);
        }
        $this->assertSame('1234.56', fields::amount('1.234,56'));
        $this->assertSame('1234.56', fields::amount('1234,56'));
        $this->assertSame('9999999.99', fields::amount('9999999.99'));

        // The checklist refuses each one in a cost row (the database column could not even hold some of them).
        $check = new \ReflectionMethod(checklist::class, 'check_costs');
        $issues = new \ReflectionProperty(checklist::class, 'issues');
        $group = (object)['name' => 'Grupo 1', 'idgrupo' => '1'];
        foreach (['12.345', '-1', '10000000'] as $bad) {
            $c = (new \ReflectionClass(checklist::class))->newInstanceWithoutConstructor();
            $cost = (object)['cif' => 'A00000000', 'directos' => $bad, 'indirectos' => '0', 'organizacion' => '0',
                'salariales' => '0'];
            $check->invoke($c, $group, [$cost], 'organizadora');
            $this->assertSame(
                [get_string('chk_amount', 'local_bonifica', 'A00000000')],
                array_column($issues->getValue($c), 'message'),
                $bad
            );
        }
    }
}
