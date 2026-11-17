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
 * Shared fixture for the end-file tests: one action, its groups, participants and costs, with fictional data only.
 *
 * Document numbers follow FUNDAE's sample style (00000001R, ...), companies use FUNDAE's placeholder CIFs
 * (A00000000 is a valid CIF, B00000000 needs test mode) and e-mails use example.com.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait fin_fixture {
    /**
     * A valid fictional NIF built from a small number, such as 00000001R.
     *
     * @param int $n
     * @return string
     */
    protected static function nif(int $n): string {
        return sprintf('%08d', $n) . rules\nif::LETTERS[$n % 23];
    }

    /**
     * Participant fields for data::save_part().
     *
     * @param int $bgroupid
     * @param int $n
     * @param array $extra fields to override
     * @return array
     */
    protected static function part_fields(int $bgroupid, int $n, array $extra = []): array {
        return $extra + [
            'bgroupid' => $bgroupid, 'nombre' => 'Participante ' . $n, 'tipodoc' => rules\nif::TYPE_NIF,
            'docnum' => self::nif($n), 'email' => 'participante' . $n . '@example.com',
            'telefono' => '6000000' . sprintf('%02d', $n % 100),
            'categoria' => 3, 'nivelestudios' => 4, 'diploma' => 'N',
            'flags' => ['erte' => false, 'discapacidad' => false, 'terrorismo' => false, 'violencia' => false,
                'fijodiscontinuo' => false],
        ];
    }

    /**
     * Builds an action in a new course with one Bonifica group per Moodle group.
     *
     * @param int[] $partspergroup participants in each group, such as [2, 1]
     * @param string[] $cifs companies; each group gets one cost row per company
     * @param array $action action fields to override
     * @return array [course, action, groups (decorated, keyed by id)]
     */
    protected function make_fixture(
        array $partspergroup = [2, 1],
        array $cifs = ['A00000000', 'B00000000'],
        array $action = []
    ): array {
        set_config('testmode', 1, 'local_bonifica');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        foreach (array_keys($partspergroup) as $i) {
            $gen->create_group(['courseid' => $course->id, 'name' => 'Grupo ' . ($i + 1)]);
        }
        $rec = data::save_action($course->id, $action + ['idaccion' => '00002', 'nombre' => 'Ventas que cierran',
            'modalidad' => 'pr', 'nivel' => 'b', 'horaspr' => 20, 'horaste' => 0]);
        $tz = \core_date::get_server_timezone();
        $n = 0;
        $i = 0;
        foreach (data::get_groups($rec) as $g) {
            data::save_group($g->id, ['idgrupo' => (string)($i + 1),
                'fechainicio' => make_timestamp(2026, 10, 5, 9, 0, 0, $tz),
                'fechafin' => make_timestamp(2026, 11, 20, 14, 0, 0, $tz)]);
            for ($k = 0; $k < $partspergroup[$i]; $k++) {
                $n++;
                data::save_part(self::part_fields($g->id, $n));
            }
            foreach ($cifs as $c => $cif) {
                data::save_cost(['bgroupid' => $g->id, 'cif' => $cif, 'directos' => 600 + 100 * $c, 'indirectos' => 50,
                    'organizacion' => 60, 'salariales' => 300,
                    'periodos' => $c === 0 ? [['mes' => 10, 'importe' => 400], ['mes' => 11, 'importe' => 310]] : []]);
            }
            $i++;
        }
        return [$course, $rec, data::get_groups($rec)];
    }
}
