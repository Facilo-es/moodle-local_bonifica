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

namespace local_bonifica\event;

/**
 * A FUNDAE file was generated. It carries ids only, never participant data.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class file_generated extends \core\event\base {
    /**
     * Init.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_bonifica_export';
    }

    /**
     * Name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_file_generated', 'local_bonifica');
    }

    /**
     * Description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' generated Bonifica file '{$this->objectid}'.";
    }

    /**
     * Object id mapping for backup.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_bonifica_export', 'restore' => \core\event\base::NOT_MAPPED];
    }
}
