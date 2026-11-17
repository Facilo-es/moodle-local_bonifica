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
 * A spreadsheet template whose header row the paste box recognises, with one fictional example row.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
require_login($course);
require_capability('local/bonifica:manage', context_course::instance($course->id));

// Spanish headers on purpose: they are FUNDAE's words, and the paste box maps them in any language.
$rows = [
    ['Participante', 'Tipo de documento', 'Número de documento', 'Correo', 'Teléfono', 'Categoría profesional',
        'Nivel de estudios', 'Diploma', 'ERTE', 'Discapacidad', 'Terrorismo', 'Violencia de género', 'Fijo discontinuo'],
    ['Ana Ejemplo Ejemplo', 'NIF', '12345678Z', 'ana@example.com', '600000000', '3', '7', 'S', 'N', 'N', 'N', 'N', 'N'],
];
$csv = "\xEF\xBB\xBF" . implode("\r\n", array_map(fn($r) => implode(';', $r), $rows)) . "\r\n";
send_file($csv, 'bonifica-participantes.csv', 0, 0, true, true, 'text/csv');
