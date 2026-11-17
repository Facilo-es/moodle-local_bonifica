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

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\xml\builder_fin::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\xml\validator::class)]
/**
 * Tests 1 to 3: FUNDAE's samples validate, and the generated end file validates in each role's version.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\xml\builder_fin
 * @covers     \local_bonifica\xml\validator
 */
final class xml_fin_test extends \advanced_testcase {
    use fin_fixture;

    /** @var array Sample file for each role. */
    const SAMPLES = [
        'organizadora' => 'FinGrupoOrganizadora.xml',
        'bonificada' => 'FinGrupoBonificada.xml',
        'gruposempresa' => 'FinGrupoGruposEmpresa.xml',
    ];

    /**
     * Element names under the first match of an XPath query, in document order.
     *
     * @param \DOMDocument $doc
     * @param string $query
     * @return string[]
     */
    protected static function children(\DOMDocument $doc, string $query): array {
        $node = (new \DOMXPath($doc))->query($query)->item(0);
        $out = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $out[] = $child->nodeName;
            }
        }
        return $out;
    }

    /**
     * The end file for the fixture, built through the checklist like the download page does.
     *
     * @param string $role
     * @return \DOMDocument
     */
    protected function generated(string $role): \DOMDocument {
        [, $action, $groups] = $this->make_fixture([2, 1], ['A00000000', 'B00000000']);
        $result = checklist::run($action, $groups, $role);
        $this->assertSame([], $result['issues'], $role);
        $this->assertTrue($result['ready'], $role);
        return builder_fin::build($role, $result['data']);
    }

    /**
     * Test 1: FUNDAE's three 2026 end samples validate against their schemas, so the validator works.
     */
    public function test_samples_validate(): void {
        foreach (self::SAMPLES as $role => $file) {
            $xml = file_get_contents(__DIR__ . '/fixtures/fundae2026/' . $file);
            $this->assertSame([], validator::validate($xml, schemas::path('fin', $role, 2026)), $file);
        }
    }

    /**
     * Test 1, negative side: a broken sample is refused with a message tied to the participant.
     */
    public function test_validator_reports_errors(): void {
        $xml = file_get_contents(__DIR__ . '/fixtures/fundae2026/FinGrupoOrganizadora.xml');
        $bad = preg_replace('#<telefono>666666661</telefono>#', '<telefono>12</telefono>', $xml, 1);
        $errors = validator::validate($bad, schemas::path('fin', 'organizadora', 2026));
        $this->assertNotEmpty($errors);
        $this->assertEquals(1, $errors[0]['part']);
        $this->assertEquals('telefono', $errors[0]['field']);
        // A DOCTYPE (possible entity attack) is refused before the schema step.
        $doctype = str_replace('<grupos>', '<!DOCTYPE grupos [<!ENTITY x "y">]><grupos>', $xml);
        $this->assertNotEmpty(validator::validate($doctype, schemas::path('fin', 'organizadora', 2026)));
    }

    /**
     * Test 2: the organizadora end file validates and follows the sample's element order.
     */
    public function test_organizadora_validates_in_sample_order(): void {
        $this->resetAfterTest();
        $doc = $this->generated('organizadora');
        $this->assertSame([], validator::validate_dom($doc, schemas::path('fin', 'organizadora', 2026)));

        $xp = new \DOMXPath($doc);
        $this->assertEquals(2, $xp->query('/grupos/grupo')->length);
        $this->assertEquals(3, $xp->query('//participante')->length);
        $this->assertEquals(4, $xp->query('//coste')->length);
        $this->assertEquals('00002', $xp->evaluate('string(/grupos/grupo[1]/idAccion)'));

        $sample = new \DOMDocument();
        $sample->load(__DIR__ . '/fixtures/fundae2026/FinGrupoOrganizadora.xml');
        foreach (['/grupos/grupo[1]', '//participante[1]', '//coste[1]', '//periodo[1]'] as $q) {
            $this->assertSame(self::children($sample, $q), self::children($doc, $q), $q);
        }
        $this->assertEquals('600.00', $xp->evaluate('string(//coste[1]/directos)'));
        $this->assertEquals('A00000000', $xp->evaluate('string(//coste[1]/cifagrupada)'));
    }

    /**
     * Test 3: bonificada and gruposempresa versions validate, without the elements their schema leaves out.
     */
    public function test_bonificada_and_gruposempresa(): void {
        $this->resetAfterTest();
        foreach (['bonificada', 'gruposempresa'] as $role) {
            $doc = $this->generated($role);
            $this->assertSame([], validator::validate_dom($doc, schemas::path('fin', $role, 2026)), $role);
            $xp = new \DOMXPath($doc);
            $this->assertEquals(0, $xp->query('//organizacion')->length, $role);
            $this->assertEquals(3, $xp->query('//participante')->length, $role);
            if ($role === 'bonificada') {
                $this->assertEquals(0, $xp->query('//cifagrupada')->length);
            } else {
                $this->assertEquals(4, $xp->query('//cifagrupada')->length);
            }
            $sample = new \DOMDocument();
            $sample->load(__DIR__ . '/fixtures/fundae2026/' . self::SAMPLES[$role]);
            $this->assertSame(self::children($sample, '//coste[1]'), self::children($doc, '//coste[1]'), $role);
        }
    }

    /**
     * Unset flags are left out, and the file name follows FUNDAE's pattern.
     */
    public function test_flags_left_out_and_filename(): void {
        $this->resetAfterTest();
        [, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        $part = array_values(data::get_parts($group->id))[0];
        data::save_part(['id' => $part->id, 'flags' => []]);
        $set = ['action' => $action, 'group' => $group, 'parts' => data::get_parts($group->id),
            'costs' => data::get_costs($group->id)];
        $doc = builder_fin::build('organizadora', [$set]);
        $this->assertSame([], validator::validate_dom($doc, schemas::path('fin', 'organizadora', 2026)));
        $this->assertEquals(0, (new \DOMXPath($doc))->query('//discapacidad|//ERTE_RD_ley')->length);
        $tz = \core_date::get_server_timezone();
        $this->assertEquals(
            'FinalizacionGrupo_00002_1_20261130.xml',
            builder_fin::filename([$set], make_timestamp(2026, 11, 30, 12, 0, 0, $tz))
        );
    }
}
