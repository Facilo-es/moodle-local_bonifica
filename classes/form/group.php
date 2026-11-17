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

namespace local_bonifica\form;

use local_bonifica\rules\fields;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The group fields every edition needs for the end file.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group extends \moodleform {
    /**
     * Builds the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $p = 'local_bonifica';
        foreach (['id' => PARAM_INT, 'g' => PARAM_INT, 'edit' => PARAM_INT, 'tab' => PARAM_ALPHA] as $k => $type) {
            $mform->addElement('hidden', $k);
            $mform->setType($k, $type);
        }
        $mform->addElement('text', 'idgrupo', get_string('idgrupo', $p), ['size' => 6, 'inputmode' => 'numeric']);
        $mform->setType('idgrupo', PARAM_TEXT);
        $mform->addElement('static', 'idgrupo_desc', '', \html_writer::span(get_string('idgrupo_desc', $p), 'text-muted small'));
        $mform->addElement('date_selector', 'fechainicio', get_string('fechainicio', $p), ['optional' => true]);
        $mform->addElement('date_selector', 'fechafin', get_string('fechafin', $p), ['optional' => true]);
        $mform->addElement('text', 'responsable', get_string('responsable', $p), ['size' => 40]);
        $mform->setType('responsable', PARAM_TEXT);
        $mform->addElement('text', 'telefono', get_string('telefono_resp', $p), ['size' => 14, 'inputmode' => 'tel']);
        $mform->setType('telefono', PARAM_TEXT);
        $this->add_action_buttons(true);
    }

    /**
     * Checks the group number, dates and phone.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['idgrupo']) !== '' && !fields::idgrupo(trim($data['idgrupo']))) {
            $errors['idgrupo'] = get_string('err_idgrupo', 'local_bonifica');
        }
        if (!empty($data['fechainicio']) && !empty($data['fechafin']) && $data['fechafin'] < $data['fechainicio']) {
            $errors['fechafin'] = get_string('err_dates', 'local_bonifica');
        }
        if (trim($data['telefono']) !== '' && fields::phone($data['telefono']) === null) {
            $errors['telefono'] = get_string('err_phone', 'local_bonifica');
        }
        return $errors;
    }

    /**
     * Fields ready for data::save_group().
     *
     * @return array|null
     */
    public function get_fields(): ?array {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        return [
            'idgrupo' => trim($data->idgrupo), 'fechainicio' => $data->fechainicio ?: null, 'fechafin' => $data->fechafin ?: null,
            'responsable' => trim($data->responsable), 'telefono' => fields::phone($data->telefono) ?? '',
        ];
    }
}
