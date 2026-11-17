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
 * Admin settings and the summary page for local_bonifica.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $p = 'local_bonifica';
    $ADMIN->add('localplugins', new admin_category('local_bonifica', get_string('pluginname', 'local_bonifica')));
    $settings = new admin_settingpage('local_bonifica_settings', get_string('settings', 'local_bonifica'));
    $ADMIN->add('local_bonifica', $settings);

    if ($ADMIN->fulltree) {
        $roles = [];
        foreach (['organizadora', 'bonificada', 'gruposempresa'] as $r) {
            $roles[$r] = get_string('role_' . $r, $p);
        }
        $settings->add(new admin_setting_configselect(
            "$p/role",
            get_string('role', $p),
            get_string('role_desc', $p),
            'organizadora',
            $roles
        ));
        $settings->add(new \local_bonifica\admin\setting_cif(
            "$p/entitycif",
            get_string('entitycif', $p),
            get_string('entitycif_desc', $p),
            '',
            PARAM_ALPHANUM,
            12
        ));
        $settings->add(new admin_setting_configtext("$p/entityname", get_string('entityname', $p), '', '', PARAM_TEXT));
        $settings->add(new admin_setting_configtext("$p/contact", get_string('contact', $p), '', '', PARAM_TEXT));
        $settings->add(new admin_setting_configtext(
            "$p/contactphone",
            get_string('contactphone', $p),
            '',
            '',
            PARAM_ALPHANUMEXT,
            14
        ));
        $years = [];
        foreach (\local_bonifica\xml\schemas::years() as $y) {
            $years[$y] = (string)$y;
        }
        $settings->add(new admin_setting_configselect(
            "$p/schemayear",
            get_string('schemayear', $p),
            get_string('schemayear_desc', $p),
            max(array_keys($years)),
            $years
        ));
        $settings->add(new admin_setting_configcheckbox(
            "$p/testmode",
            get_string('testmode', $p),
            get_string('testmode_desc', $p),
            0
        ));
    }
}
