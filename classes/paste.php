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

use local_bonifica\rules\fields;
use local_bonifica\rules\nif;

/**
 * Reads participants pasted from a spreadsheet: a header row, then tab- or semicolon-separated lines.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class paste {
    /** @var array Column header prefixes (lowercase, no accents), first match wins. */
    const HEADERS = [
        'tipodoc' => ['tipo', 'type'],
        'docnum' => ['numero', 'documento', 'nif', 'dni', 'nie', 'document', 'number'],
        'nombre' => ['participante', 'nombre', 'name', 'alumno'],
        'email' => ['correo', 'email', 'e-mail', 'mail'],
        'telefono' => ['telefono', 'movil', 'phone'],
        'categoria' => ['categoria', 'category'],
        'nivelestudios' => ['nivel', 'estudios', 'education'],
        'diploma' => ['diploma'],
        'erte' => ['erte'],
        'discapacidad' => ['discapacidad', 'disability'],
        'terrorismo' => ['terrorismo', 'terrorism'],
        'violencia' => ['violencia', 'gender'],
        'fijodiscontinuo' => ['fijo', 'discontinuo'],
    ];

    /** @var string[] Category labels by code, lowercase without accents, to accept words as well as numbers. */
    const CATEGORIES = [1 => 'directivo', 2 => 'mando', 3 => 'tecnico', 4 => 'trabajador cualificado', 5 => 'trabajador de baja'];

    /**
     * Lowercase without accents.
     *
     * @param string $value
     * @return string
     */
    public static function plain(string $value): string {
        $value = \core_text::strtolower(trim($value));
        return strtr($value, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /**
     * Maps header cells to field names.
     *
     * @param string[] $cells
     * @return array column index => field
     */
    public static function map_headers(array $cells): array {
        $map = [];
        foreach ($cells as $i => $cell) {
            $cell = self::plain($cell);
            foreach (self::HEADERS as $field => $prefixes) {
                if (in_array($field, $map, true)) {
                    continue;
                }
                foreach ($prefixes as $prefix) {
                    if ($cell !== '' && strpos($cell, $prefix) === 0) {
                        $map[$i] = $field;
                        continue 3;
                    }
                }
            }
        }
        return $map;
    }

    /**
     * Parses pasted text. Rows with a missing, wrong or repeated document are refused with their line number;
     * other gaps are kept so the checklist can point at them.
     *
     * @param string $text
     * @param int $bgroupid group to check duplicates against
     * @return array ['rows' => array[], 'errors' => array of ['line' => int, 'message' => string]]
     */
    public static function parse(string $text, int $bgroupid): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($text, "\r\n"));
        $rows = [];
        $errors = [];
        if (!$lines || trim($lines[0]) === '') {
            return ['rows' => [], 'errors' => []];
        }
        $sep = substr_count($lines[0], "\t") >= substr_count($lines[0], ';') ? "\t" : ';';
        $map = self::map_headers(str_getcsv($lines[0], $sep, '"', ''));
        if (!in_array('docnum', $map, true)) {
            return ['rows' => [], 'errors' => [['line' => 1, 'message' => get_string('paste_noheader', 'local_bonifica')]]];
        }
        $seen = [];
        foreach (array_slice($lines, 1, null, true) as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $lineno = $i + 1;
            $raw = [];
            foreach (str_getcsv($line, $sep, '"', '') as $col => $value) {
                if (isset($map[$col])) {
                    $raw[$map[$col]] = trim($value);
                }
            }
            $row = self::row($raw);
            $doc = $row['docnum'];
            if ($doc === '') {
                $errors[] = ['line' => $lineno, 'message' => get_string('paste_nodoc', 'local_bonifica')];
            } else if (!nif::valid_document($row['tipodoc'], $doc)) {
                $errors[] = ['line' => $lineno, 'message' => get_string('paste_baddoc', 'local_bonifica', s($doc))];
            } else if (isset($seen[$doc]) || data::doc_taken($bgroupid, $doc)) {
                $errors[] = ['line' => $lineno, 'message' => get_string('paste_duplicate', 'local_bonifica', s($doc))];
            } else {
                $seen[$doc] = true;
                $rows[] = $row;
            }
        }
        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * Turns one row of raw cells into participant fields.
     *
     * @param array $raw field => text
     * @return array
     */
    public static function row(array $raw): array {
        $doc = nif::clean($raw['docnum'] ?? '');
        $row = [
            'nombre' => \core_text::substr($raw['nombre'] ?? '', 0, 255),
            'docnum' => $doc,
            'tipodoc' => nif::guess_type($doc, self::plain($raw['tipodoc'] ?? '')),
            'email' => \core_text::substr($raw['email'] ?? '', 0, 255),
            'telefono' => $raw['telefono'] ?? '',
            'categoria' => self::code($raw['categoria'] ?? '', 5, self::CATEGORIES),
            'nivelestudios' => self::code($raw['nivelestudios'] ?? '', 10, []),
            'diploma' => self::yesno($raw['diploma'] ?? '') === null ? '' : (self::yesno($raw['diploma']) ? 'S' : 'N'),
            'flags' => [],
        ];
        foreach (data::FLAGS as $flag) {
            $row['flags'][$flag] = self::yesno($raw[$flag] ?? '');
        }
        return $row;
    }

    /**
     * A code from a number at the start of the cell ("3", "3 - Técnico") or a label.
     *
     * @param string $value
     * @param int $max
     * @param array $labels
     * @return int|null
     */
    protected static function code(string $value, int $max, array $labels): ?int {
        if (preg_match('/^\s*(\d{1,2})\b/', $value, $m) && (int)$m[1] >= 1 && (int)$m[1] <= $max) {
            return (int)$m[1];
        }
        $value = self::plain($value);
        foreach ($labels as $code => $label) {
            if ($value !== '' && strpos($value, $label) === 0) {
                return $code;
            }
        }
        return null;
    }

    /**
     * Yes or no from S, Sí, Si, Yes, true, 1, X; N, No, false, 0. Empty is null.
     *
     * @param string $value
     * @return bool|null
     */
    public static function yesno(string $value): ?bool {
        $value = self::plain($value);
        if (in_array($value, ['s', 'si', 'yes', 'y', 'true', '1', 'x', 'verdadero'], true)) {
            return true;
        }
        if (in_array($value, ['n', 'no', 'false', '0', 'falso'], true)) {
            return false;
        }
        return null;
    }
}
