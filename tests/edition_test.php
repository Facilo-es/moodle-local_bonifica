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

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fin_fixture.php');

#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\edition::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_bonifica\ui::class)]
/**
 * Test 20: the free build hides Pro and shows the upsell; Pro over free keeps every row.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_bonifica\edition
 * @covers     \local_bonifica\ui
 */
final class edition_test extends \advanced_testcase {
    use fin_fixture;

    /** @var string[] Tables that hold customer data. */
    const TABLES = ['local_bonifica_action', 'local_bonifica_group', 'local_bonifica_part', 'local_bonifica_cost',
        'local_bonifica_company', 'local_bonifica_check', 'local_bonifica_export'];

    protected function tearDown(): void {
        edition::$listingoverride = null;
        parent::tearDown();
    }

    /**
     * The files tab as the page renders it.
     *
     * @param \stdClass $course
     * @param \stdClass $action
     * @param \stdClass $group
     * @return string HTML
     */
    protected function files_html(\stdClass $course, \stdClass $action, \stdClass $group): string {
        global $OUTPUT, $PAGE;
        $url = new \moodle_url('/local/bonifica/course.php', ['id' => $course->id, 'tab' => 'archivos']);
        $PAGE->set_url($url);
        $PAGE->set_context(\context_course::instance($course->id));
        return $OUTPUT->render_from_template('local_bonifica/files', ui::files($action, $group, $url));
    }

    /**
     * Test 20: free build, Pro buttons absent and the upsell card present; Pro shows the buttons and no upsell.
     */
    public function test_free_hides_pro(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $action, $groups] = $this->make_fixture([1], ['A00000000']);
        $group = reset($groups);
        edition::$listingoverride = false;

        $this->assertFalse(edition::is_pro());
        $html = $this->files_html($course, $action, $group);
        $this->assertStringContainsString(get_string('gen_fin', 'local_bonifica'), $html);
        $this->assertStringNotContainsString('value="aaff"', $html);
        $this->assertStringNotContainsString('value="inicio"', $html);
        $this->assertStringNotContainsString(get_string('gen_aaff', 'local_bonifica'), $html);
        // Marketplace build removes from here: @upsell-start.
        $this->assertStringContainsString('bA-up', $html);
        $this->assertStringContainsString(get_string('upsell', 'local_bonifica'), $html);
        // Marketplace build removes up to here: @upsell-end.
        try {
            edition::require_pro();
            $this->fail('A Pro-only request ran in the free build');
        } catch (\moodle_exception $e) {
            $this->assertSame('pro_only', $e->errorcode);
        }

        edition::$listingoverride = true;
        $html = $this->files_html($course, $action, $group);
        $this->assertStringContainsString(get_string('gen_fin', 'local_bonifica'), $html);
        $this->assertStringNotContainsString('bA-up', $html, 'The Marketplace build must not advertise Pro');
        edition::$listingoverride = null;
    }
}
