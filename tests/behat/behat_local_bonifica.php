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
 * Behat steps for Bonifica: fictional FUNDAE data and direct page visits.
 *
 * @package    local_bonifica
 * @category   test
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use local_bonifica\data;
use local_bonifica\rules\nif;

/**
 * Behat steps for Bonifica.
 *
 * @package    local_bonifica
 * @category   test
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_bonifica extends behat_base {
    /**
     * Opens the Bonifica page of a course, optionally on one tab.
     *
     * @Given /^I am on the Bonifica page of course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     * @Given /^I am on the Bonifica "(?P<tab_string>[a-z]+)" tab of course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     *
     * @param string $taborshortname tab name, or the course short name when no tab is given
     * @param string|null $shortname course short name
     */
    public function i_am_on_the_bonifica_page_of_course(string $taborshortname, ?string $shortname = null): void {
        $params = [];
        if ($shortname === null) {
            $shortname = $taborshortname;
        } else {
            $params['tab'] = $taborshortname;
        }
        $params = ['id' => $this->get_course_id($shortname)] + $params;
        $this->execute('behat_general::i_visit', [new moodle_url('/local/bonifica/course.php', $params)]);
    }

    /**
     * Checks that the current user is refused the Bonifica page of a course, then leaves the error page.
     *
     * @Then /^I should not be able to open the Bonifica page of course "(?P<shortname_string>(?:[^"]|\\")*)"$/
     *
     * @param string $shortname
     * @throws \Behat\Mink\Exception\ExpectationException
     */
    public function i_should_not_be_able_to_open_the_bonifica_page(string $shortname): void {
        $url = new moodle_url('/local/bonifica/course.php', ['id' => $this->get_course_id($shortname)]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url(false)));
        $html = $this->getSession()->getPage()->getContent();
        $refused = strpos($html, 'nopermissions') !== false
            || strpos($html, 'do not currently have permissions to do that') !== false;
        $shown = strpos($html, get_string('files_heading', 'local_bonifica')) !== false
            || strpos($html, 'data-courseid=') !== false;
        // Leave the expected error page so the after-step exception check does not fail the scenario.
        $this->getSession()->visit($this->locate_path('/my/courses.php'));
        if (!$refused || $shown) {
            throw new \Behat\Mink\Exception\ExpectationException('The Bonifica page opened for a user without ' .
                'local/bonifica:manage', $this->getSession());
        }
    }

    /**
     * Gives a course a complete Bonifica action: every group dated, with valid participants and costs.
     *
     * Uses fictional data only: FUNDAE-style document numbers, placeholder CIF A00000000 and example.com e-mails.
     *
     * @Given /^the course "(?P<shortname_string>(?:[^"]|\\")*)" has a Bonifica action ready to file$/
     *
     * @param string $shortname
     */
    public function the_course_has_a_bonifica_action_ready_to_file(string $shortname): void {
        $this->make_action($shortname, []);
    }

    // phpcs:disable moodle.Files.LineLength.TooLong
    /**
     * Gives a course a complete Bonifica action plus one participant with the given document number.
     *
     * @Given /^the course "(?P<shortname_string>(?:[^"]|\\")*)" has a Bonifica action with a participant with document "(?P<doc_string>[^"]*)"$/
     *
     * @param string $shortname
     * @param string $doc
     */
    public function the_course_has_a_bonifica_action_with_document(string $shortname, string $doc): void {
        $this->make_action($shortname, [$doc]);
    }
    // phpcs:enable moodle.Files.LineLength.TooLong

    /**
     * Builds the action.
     *
     * @param string $shortname
     * @param string[] $extradocs document numbers of extra participants added to the first group
     */
    protected function make_action(string $shortname, array $extradocs): void {
        set_config('testmode', 1, 'local_bonifica');
        $courseid = $this->get_course_id($shortname);
        $action = data::save_action($courseid, ['idaccion' => '00002', 'nombre' => 'Ventas que cierran',
            'modalidad' => 'pr', 'nivel' => 'b', 'horaspr' => 20, 'horaste' => 0]);
        $tz = \core_date::get_server_timezone();
        $n = 0;
        $first = true;
        foreach (data::get_groups($action) as $g) {
            data::save_group($g->id, ['fechainicio' => make_timestamp(2026, 10, 5, 9, 0, 0, $tz),
                'fechafin' => make_timestamp(2026, 11, 20, 14, 0, 0, $tz)]);
            $docs = [null, null];
            if ($first) {
                $docs = array_merge($docs, $extradocs);
                $first = false;
            }
            foreach ($docs as $doc) {
                $k = ++$n;
                data::save_part(['bgroupid' => $g->id, 'nombre' => 'Participante ' . $k, 'tipodoc' => nif::TYPE_NIF,
                    'docnum' => $doc ?? self::nif($k), 'email' => 'participante' . $k . '@example.com',
                    'telefono' => '6000000' . sprintf('%02d', $k % 100), 'categoria' => 3, 'nivelestudios' => 4,
                    'diploma' => 'N', 'flags' => ['erte' => false, 'discapacidad' => false, 'terrorismo' => false,
                        'violencia' => false, 'fijodiscontinuo' => false]]);
            }
            data::save_cost(['bgroupid' => $g->id, 'cif' => 'A00000000', 'directos' => 600, 'indirectos' => 50,
                'organizacion' => 60, 'salariales' => 300,
                'periodos' => [['mes' => 10, 'importe' => 400], ['mes' => 11, 'importe' => 310]]]);
        }
    }

    /**
     * The id of a course from its short name.
     *
     * @param string $shortname
     * @return int
     */
    protected function get_course_id(string $shortname): int {
        global $DB;
        return (int)$DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
    }

    /**
     * A valid fictional NIF built from a small number, such as 00000001R.
     *
     * @param int $n
     * @return string
     */
    protected static function nif(int $n): string {
        return sprintf('%08d', $n) . nif::LETTERS[$n % 23];
    }
}
