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

use local_bonifica\rules\fields;
use local_bonifica\rules\nif;

/**
 * Builds the end-of-group file (FinalizacionGrupo) in the role's version, in schema element order.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class builder_fin {
    /** @var array Participant flags and their element names, in schema order around the required fields. */
    const FLAG_ELEMENTS = [
        'erte' => 'ERTE_RD_ley',
        'discapacidad' => 'discapacidad',
        'terrorismo' => 'afectadosTerrorismo',
        'violencia' => 'afectadosViolenciaGenero',
        'fijodiscontinuo' => 'fijoDiscontinuo',
    ];

    /**
     * Builds the file.
     *
     * @param string $role organizadora, bonificada or gruposempresa
     * @param array $groups each ['action' => stdClass, 'group' => stdClass, 'parts' => stdClass[], 'costs' => stdClass[]]
     * @return \DOMDocument
     */
    public static function build(string $role, array $groups): \DOMDocument {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->xmlStandalone = true;
        $doc->formatOutput = true;
        $root = $doc->appendChild($doc->createElement('grupos'));
        foreach ($groups as $g) {
            $grupo = $root->appendChild($doc->createElement('grupo'));
            self::text($grupo, 'idAccion', trim((string)$g['action']->idaccion));
            self::text($grupo, 'idGrupo', trim((string)$g['group']->idgrupo));
            $list = $grupo->appendChild($doc->createElement('participantes'));
            foreach ($g['parts'] as $p) {
                self::participant($list, $role, $p);
            }
            $costes = $grupo->appendChild($doc->createElement('costes'));
            foreach ($g['costs'] as $c) {
                self::cost($costes, $role, $c);
            }
        }
        return $doc;
    }

    /**
     * Adds one participant.
     *
     * @param \DOMNode $list
     * @param string $role
     * @param \stdClass $p decrypted participant
     */
    protected static function participant(\DOMNode $list, string $role, \stdClass $p): void {
        $el = $list->appendChild($list->ownerDocument->createElement('participante'));
        $flags = $p->flags ?? [];
        self::text($el, 'nif', nif::clean($p->docnum));
        self::text($el, 'N_TIPO_DOCUMENTO', (string)(int)$p->tipodoc);
        if ($role !== 'gruposempresa') {
            self::flag($el, 'ERTE_RD_ley', $flags['erte'] ?? null);
        }
        self::text($el, 'email', trim((string)$p->email));
        self::text($el, 'telefono', fields::phone($p->telefono) ?? (string)$p->telefono);
        self::flag($el, 'discapacidad', $flags['discapacidad'] ?? null);
        self::flag($el, 'afectadosTerrorismo', $flags['terrorismo'] ?? null);
        self::flag($el, 'afectadosViolenciaGenero', $flags['violencia'] ?? null);
        self::text($el, 'categoriaprofesional', (string)$p->categoria);
        self::text($el, 'nivelestudios', (string)$p->nivelestudios);
        self::text($el, 'DiplomaAcreditativo', (string)$p->diploma);
        self::flag($el, 'fijoDiscontinuo', $flags['fijodiscontinuo'] ?? null);
    }

    /**
     * Adds one cost row.
     *
     * @param \DOMNode $costes
     * @param string $role
     * @param \stdClass $c
     */
    protected static function cost(\DOMNode $costes, string $role, \stdClass $c): void {
        $el = $costes->appendChild($costes->ownerDocument->createElement('coste'));
        if ($role !== 'bonificada') {
            self::text($el, 'cifagrupada', nif::clean($c->cif));
        }
        self::text($el, 'directos', self::amount($c->directos));
        self::text($el, 'indirectos', self::amount($c->indirectos));
        if ($role === 'organizadora') {
            self::text($el, 'organizacion', self::amount($c->organizacion));
        }
        self::text($el, 'salariales', self::amount($c->salariales));
        $periodos = is_array($c->periodos ?? null) ? $c->periodos : (json_decode($c->periodos ?? '', true) ?: []);
        if ($periodos) {
            $ps = $el->appendChild($el->ownerDocument->createElement('periodos'));
            foreach ($periodos as $per) {
                $pe = $ps->appendChild($ps->ownerDocument->createElement('periodo'));
                self::text($pe, 'mes', (string)(int)$per['mes']);
                self::text($pe, 'importe', self::amount($per['importe']));
            }
        }
    }

    /**
     * An amount as the schema wants it, or the raw value so the schema reports it.
     *
     * @param mixed $value
     * @return string
     */
    protected static function amount($value): string {
        return fields::amount((string)$value) ?? (string)$value;
    }

    /**
     * Appends a text element. Text nodes are escaped by DOM.
     *
     * @param \DOMNode $parent
     * @param string $name
     * @param string $value
     */
    protected static function text(\DOMNode $parent, string $name, string $value): void {
        $el = $parent->appendChild($parent->ownerDocument->createElement($name));
        $el->appendChild($parent->ownerDocument->createTextNode($value));
    }

    /**
     * Appends a true/false flag, left out when not set.
     *
     * @param \DOMNode $parent
     * @param string $name
     * @param bool|null $value
     */
    protected static function flag(\DOMNode $parent, string $name, ?bool $value): void {
        if ($value !== null) {
            self::text($parent, $name, $value ? 'true' : 'false');
        }
    }

    /**
     * File name for a set of groups, as in FinalizacionGrupo_{idAccion}_{idGrupo}_{yyyymmdd}.xml.
     *
     * @param array $groups as for build()
     * @param int|null $time
     * @return string
     */
    public static function filename(array $groups, ?int $time = null): string {
        $first = reset($groups);
        $ids = array_map(fn($g) => trim((string)$g['group']->idgrupo), $groups);
        $date = userdate($time ?? time(), '%Y%m%d', \core_date::get_server_timezone());
        return clean_filename('FinalizacionGrupo_' . trim((string)$first['action']->idaccion) . '_' .
            implode('-', $ids) . '_' . $date . '.xml');
    }
}
