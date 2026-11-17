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

namespace local_bonifica\rules;

/**
 * Spanish identity document checks: NIF, NIE and company CIF.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class nif {
    /** @var int FUNDAE document type for NIF. */
    const TYPE_NIF = 10;
    /** @var int FUNDAE document type for passport. */
    const TYPE_PASSPORT = 20;
    /** @var int FUNDAE document type for NIE. */
    const TYPE_NIE = 60;

    /** @var string Check letters for NIF and NIE. */
    const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

    /**
     * Upper-cases a document number and drops spaces, dots and dashes.
     *
     * @param string|null $value
     * @return string
     */
    public static function clean(?string $value): string {
        return strtoupper(preg_replace('/[\s.\-]/u', '', (string)$value));
    }

    /**
     * Whether a NIF (8 digits and a letter) has the right letter.
     *
     * @param string $value
     * @return bool
     */
    public static function valid_nif(string $value): bool {
        $value = self::clean($value);
        if (!preg_match('/^(\d{8})([A-Z])$/', $value, $m)) {
            return false;
        }
        return self::LETTERS[(int)$m[1] % 23] === $m[2];
    }

    /**
     * Whether a NIE (X, Y or Z, 7 digits and a letter) has the right letter.
     *
     * @param string $value
     * @return bool
     */
    public static function valid_nie(string $value): bool {
        $value = self::clean($value);
        if (!preg_match('/^([XYZ])(\d{7})([A-Z])$/', $value, $m)) {
            return false;
        }
        $num = (int)(strpos('XYZ', $m[1]) . $m[2]);
        return self::LETTERS[$num % 23] === $m[3];
    }

    /**
     * Whether a passport number fits the schema (8 to 10 letters or digits).
     *
     * @param string $value
     * @return bool
     */
    public static function valid_passport(string $value): bool {
        return (bool)preg_match('/^[A-Z0-9]{8,10}$/', self::clean($value));
    }

    /**
     * Checks a participant's document by its FUNDAE type.
     *
     * @param int $type 10, 20 or 60
     * @param string $value
     * @return bool
     */
    public static function valid_document(int $type, string $value): bool {
        switch ($type) {
            case self::TYPE_NIF:
                return self::valid_nif($value);
            case self::TYPE_NIE:
                return self::valid_nie($value);
            case self::TYPE_PASSPORT:
                return self::valid_passport($value);
        }
        return false;
    }

    /**
     * Guesses the FUNDAE document type from a number, or from a label such as "NIE".
     *
     * @param string $value document number
     * @param string $label optional label typed by the user
     * @return int
     */
    public static function guess_type(string $value, string $label = ''): int {
        $label = \core_text::strtolower(trim($label));
        if ($label !== '') {
            if ($label === '60' || strpos($label, 'nie') === 0) {
                return self::TYPE_NIE;
            }
            if ($label === '20' || strpos($label, 'pas') === 0) {
                return self::TYPE_PASSPORT;
            }
            if ($label === '10' || strpos($label, 'nif') === 0 || strpos($label, 'dni') === 0) {
                return self::TYPE_NIF;
            }
        }
        return preg_match('/^[XYZ]/', self::clean($value)) ? self::TYPE_NIE : self::TYPE_NIF;
    }

    /**
     * Whether a company CIF is valid. Also accepts a NIF or NIE (sole traders).
     *
     * @param string $value
     * @param bool $testmode accept FUNDAE's sample CIF B00000000
     * @return bool
     */
    public static function valid_cif(string $value, bool $testmode = false): bool {
        $value = self::clean($value);
        if ($value === 'B00000000') {
            return $testmode;
        }
        if (self::valid_nif($value) || self::valid_nie($value)) {
            return true;
        }
        if (!preg_match('/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/', $value, $m)) {
            return false;
        }
        $digits = $m[2];
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $d = (int)$digits[$i];
            if ($i % 2 === 0) {
                $d *= 2;
                $d = intdiv($d, 10) + $d % 10;
            }
            $sum += $d;
        }
        $control = (10 - $sum % 10) % 10;
        $letter = 'JABCDEFGHI'[$control];
        $first = $m[1];
        if (strpos('PQRSNW', $first) !== false) {
            return $m[3] === $letter;
        }
        if (strpos('ABEH', $first) !== false) {
            return $m[3] === (string)$control;
        }
        return $m[3] === (string)$control || $m[3] === $letter;
    }
}
