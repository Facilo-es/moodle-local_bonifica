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
 * The action fields every edition needs for the end file.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class action extends \moodleform {
    /**
     * Builds the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $p = 'local_bonifica';
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'tab', 'accion');
        $mform->setType('tab', PARAM_ALPHA);
        $mform->addElement('text', 'idaccion', get_string('idaccion', $p), ['size' => 8, 'inputmode' => 'numeric']);
        $mform->setType('idaccion', PARAM_TEXT);
        $mform->addElement('static', 'idaccion_desc', '', \html_writer::span(get_string('idaccion_desc', $p), 'text-muted small'));
        $mform->addElement('text', 'nombre', get_string('nombre', $p), ['size' => 60]);
        $mform->setType('nombre', PARAM_TEXT);
        $mform->addElement('select', 'modalidad', get_string('modalidad', $p), [
            'pr' => get_string('mod_pr', $p), 'te' => get_string('mod_te', $p), 'mx' => get_string('mod_mx', $p),
        ]);
        $mform->addElement('text', 'horaspr', get_string('horaspr', $p), ['size' => 5, 'inputmode' => 'decimal']);
        $mform->setType('horaspr', PARAM_RAW_TRIMMED);
        $mform->hideIf('horaspr', 'modalidad', 'eq', 'te');
        $mform->addElement('text', 'horaste', get_string('horaste', $p), ['size' => 5, 'inputmode' => 'decimal']);
        $mform->setType('horaste', PARAM_RAW_TRIMMED);
        $mform->hideIf('horaste', 'modalidad', 'eq', 'pr');
        $this->add_action_buttons(false);
    }

    /**
     * Checks the fields the end file needs.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (trim($data['idaccion']) !== '' && !fields::idaccion(trim($data['idaccion']))) {
            $errors['idaccion'] = get_string('err_idaccion', 'local_bonifica');
        }
        foreach (['horaspr', 'horaste'] as $k) {
            if (self::hours($data[$k] ?? '') === null) {
                $errors[$k] = get_string('err_hours', 'local_bonifica');
            }
        }
        return $errors;
    }

    /**
     * Reads hours typed with a comma or a dot.
     *
     * @param string $value
     * @return float|null null when it isn't a number of hours
     */
    public static function hours(string $value): ?float {
        $value = str_replace(',', '.', trim($value));
        if ($value === '') {
            return 0.0;
        }
        return preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value) ? (float)$value : null;
    }

    /**
     * Fields ready for data::save_action().
     *
     * @return array|null
     */
    public function get_fields(): ?array {
        $data = $this->get_data();
        if (!$data) {
            return null;
        }
        $mod = $data->modalidad;
        return [
            'idaccion' => trim($data->idaccion), 'nombre' => trim($data->nombre), 'modalidad' => $mod,
            'horaspr' => $mod === 'te' ? 0 : self::hours($data->horaspr),
            'horaste' => $mod === 'pr' ? 0 : self::hours($data->horaste),
        ];
    }
}
