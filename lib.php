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
 * Navigation hooks for local_bonifica.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds Bonifica to the course's More menu.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_bonifica_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if ($course->id == SITEID || !has_capability('local/bonifica:manage', $context)) {
        return;
    }
    $navigation->add(
        get_string('pluginname', 'local_bonifica'),
        new moodle_url('/local/bonifica/course.php', ['id' => $course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_bonifica',
        new pix_icon('i/report', '')
    );
}
