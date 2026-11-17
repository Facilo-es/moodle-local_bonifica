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
 * Validates a file against a bundled FUNDAE schema and turns libxml errors into plain messages.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /**
     * Validates XML against a schema. External entities are never loaded.
     *
     * @param string $xml
     * @param string $xsdpath
     * @return array list of ['line' => int, 'path' => string, 'message' => string]; empty when valid
     */
    public static function validate(string $xml, string $xsdpath): array {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new \DOMDocument();
        $errors = [];
        if (!$doc->loadXML($xml, LIBXML_NONET)) {
            $errors = self::collect(null);
        } else if ($doc->doctype) {
            $errors[] = ['line' => 0, 'path' => '', 'message' => get_string('xml_doctype', 'local_bonifica'),
                'field' => '', 'group' => 0, 'part' => 0, 'cost' => 0];
        } else if (!$doc->schemaValidate($xsdpath)) {
            $errors = self::collect($doc);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $errors;
    }

    /**
     * Validates a DOM built by Bonifica, keeping element line numbers by saving and reloading it.
     *
     * @param \DOMDocument $doc
     * @param string $xsdpath
     * @return array as validate()
     */
    public static function validate_dom(\DOMDocument $doc, string $xsdpath): array {
        return self::validate($doc->saveXML(), $xsdpath);
    }

    /**
     * Turns libxml's errors into messages tied to a group, participant or cost.
     *
     * @param \DOMDocument|null $doc
     * @return array
     */
    protected static function collect(?\DOMDocument $doc): array {
        $lines = $doc ? self::index($doc) : [];
        $out = [];
        foreach (libxml_get_errors() as $e) {
            $where = $lines[$e->line] ?? ['path' => '', 'field' => '', 'group' => 0, 'part' => 0, 'cost' => 0];
            $out[] = $where + ['line' => $e->line, 'message' => self::plain($where, trim($e->message))];
        }
        return $out;
    }

    /**
     * Maps each line of the document to the element on it, with its group, participant and cost numbers.
     *
     * @param \DOMDocument $doc
     * @return array
     */
    protected static function index(\DOMDocument $doc): array {
        $map = [];
        $xp = new \DOMXPath($doc);
        $g = 0;
        foreach ($xp->query('/*/grupo|/*/*[local-name()="grupo"]') as $grupo) {
            $g++;
            $p = 0;
            $c = 0;
            foreach ($xp->query('.//*', $grupo) as $el) {
                if ($el->nodeName === 'participante') {
                    $p++;
                }
                if ($el->nodeName === 'coste') {
                    $c++;
                }
                $inpart = (bool)$xp->query('ancestor::participante', $el)->length || $el->nodeName === 'participante';
                $incost = (bool)$xp->query('ancestor::coste', $el)->length || $el->nodeName === 'coste';
                $map[$el->getLineNo()] = ['path' => $el->getNodePath(), 'field' => $el->nodeName, 'group' => $g,
                    'part' => $inpart ? $p : 0, 'cost' => $incost ? $c : 0];
            }
        }
        return $map;
    }

    /**
     * A plain Spanish (or current language) message for a schema error.
     *
     * @param array $where
     * @param string $raw libxml's message, kept at the end for support
     * @return string
     */
    protected static function plain(array $where, string $raw): string {
        $a = (object)['field' => $where['field'], 'group' => $where['group'], 'part' => $where['part'],
            'cost' => $where['cost'], 'raw' => $raw];
        if ($where['part']) {
            return get_string('xml_err_part', 'local_bonifica', $a);
        }
        if ($where['cost']) {
            return get_string('xml_err_cost', 'local_bonifica', $a);
        }
        if ($where['group']) {
            return get_string('xml_err_group', 'local_bonifica', $a);
        }
        return get_string('xml_err', 'local_bonifica', $a);
    }
}
