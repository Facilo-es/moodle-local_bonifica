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

namespace local_bonifica\xml;

/**
 * The bundled FUNDAE schemas, by year, file kind and role.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class schemas {
    /** @var array File name of each schema, by kind and role. */
    const FILES = [
        'fin' => [
            'organizadora' => 'FinalizacionGrupo_Organizadora.xsd',
            'bonificada' => 'FinalizacionGrupo_Bonificada.xsd',
            'gruposempresa' => 'FinalizacionGrupo_GruposEmpresa.xsd',
        ],
        'inicio' => [
            'organizadora' => 'InicioGrupos_Organizadora.xsd',
            'bonificada' => 'InicioGrupos_Bonificada.xsd',
            'gruposempresa' => 'InicioGrupos_GrupoEmpresa.xsd',
        ],
        'aaff' => [
            'organizadora' => 'AAFF_Inicio.xsd',
            'bonificada' => 'AAFF_Inicio.xsd',
            'gruposempresa' => 'AAFF_Inicio.xsd',
        ],
    ];

    /**
     * Schema years bundled with the plugin.
     *
     * @return int[]
     */
    public static function years(): array {
        $years = [];
        foreach (glob(__DIR__ . '/../../schemas/*', GLOB_ONLYDIR) as $dir) {
            if (preg_match('/^\d{4}$/', basename($dir))) {
                $years[] = (int)basename($dir);
            }
        }
        sort($years);
        return $years;
    }

    /**
     * Full path of a schema.
     *
     * @param string $kind fin, inicio or aaff
     * @param string $role organizadora, bonificada or gruposempresa
     * @param int $year
     * @return string
     */
    public static function path(string $kind, string $role, int $year): string {
        $path = realpath(__DIR__ . '/../../schemas/' . $year . '/' . self::FILES[$kind][$role]);
        if (!$path) {
            throw new \coding_exception('Missing schema ' . $kind . ' ' . $role . ' ' . $year);
        }
        return $path;
    }
}
