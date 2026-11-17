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

namespace local_bonifica\admin;

use local_bonifica\rules\nif;

/**
 * Text setting that only accepts a valid Spanish CIF.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_cif extends \admin_setting_configtext {
    /**
     * Validates the CIF, allowing an empty value and the test CIF in test mode.
     *
     * @param string $data
     * @return bool|string true or an error message
     */
    public function validate($data) {
        $data = nif::clean((string)$data);
        if ($data === '' || nif::valid_cif($data, (bool)get_config('local_bonifica', 'testmode'))) {
            return true;
        }
        return get_string('invalidcif', 'local_bonifica');
    }

    /**
     * Saves the CIF in upper case without spaces.
     *
     * @param string $data
     * @return string empty or an error
     */
    public function write_setting($data) {
        return parent::write_setting(nif::clean((string)$data));
    }
}
