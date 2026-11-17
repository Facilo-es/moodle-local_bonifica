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
 * Field rules from the FUNDAE 2026 schemas, run before the schema so messages are plain.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fields {
    /** @var string The schema's own e-mail pattern. */
    const EMAIL = "/^[A-Za-z0-9_-]+([-+.'][A-Za-z0-9_-]+)*@[A-Za-z0-9_]+([-.][A-Za-z0-9_]+)*\\.[A-Za-z0-9_]+([-.][A-Za-z0-9_]+)*$/";

    /** @var float Largest amount the schema allows. */
    const MAX_AMOUNT = 9999999.99;

    /**
     * Normalises a phone number: drops spaces, dots, dashes and a leading +34 or 0034.
     *
     * @param string|null $value
     * @return string|null the digits, or null when it isn't 9 to 12 digits
     */
    public static function phone(?string $value): ?string {
        $v = preg_replace('/[\s.\-()]/u', '', (string)$value);
        $v = preg_replace('/^(\+34|0034)/', '', $v);
        return preg_match('/^\d{9,12}$/', $v) ? $v : null;
    }

    /**
     * Whether an e-mail matches the schema's pattern.
     *
     * @param string|null $value
     * @return bool
     */
    public static function email(?string $value): bool {
        return (bool)preg_match(self::EMAIL, trim((string)$value));
    }

    /**
     * Parses an amount typed in Spanish or English style.
     *
     * Accepts "1.234,56", "1234,56" and "1234.56". Refuses negatives, more than 2 decimals
     * and anything above the schema's limit.
     *
     * @param string|float|int|null $value
     * @return string|null the amount with a dot and 2 decimals, or null when invalid
     */
    public static function amount($value): ?string {
        $v = str_replace([' ', '€', "\u{a0}"], '', trim((string)$value));
        if ($v === '') {
            return null;
        }
        if (strpos($v, ',') !== false) {
            // Spanish style: dots are thousands separators.
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        }
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $v)) {
            return null;
        }
        if ((float)$v > self::MAX_AMOUNT) {
            return null;
        }
        return number_format((float)$v, 2, '.', '');
    }

    /**
     * Whether a FUNDAE action number is valid: 1 to 5 digits, kept as typed.
     *
     * @param string|null $value
     * @return bool
     */
    public static function idaccion(?string $value): bool {
        return (bool)preg_match('/^\d{1,5}$/', trim((string)$value));
    }

    /**
     * Whether a FUNDAE group number is valid: 1 to 5 digits.
     *
     * @param string|null $value
     * @return bool
     */
    public static function idgrupo(?string $value): bool {
        return (bool)preg_match('/^\d{1,5}$/', trim((string)$value));
    }

    /**
     * Formats an amount for Spanish readers, such as 1.820,00 €.
     *
     * @param float $value
     * @return string
     */
    public static function eur(float $value): string {
        return number_format($value, 2, ',', '.') . ' €';
    }
}
