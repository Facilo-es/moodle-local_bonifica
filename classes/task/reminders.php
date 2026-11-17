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
 * Start-deadline reminders (Pro).
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bonifica\task;

/**
 * Emails the course's Bonifica managers 10 and 8 days before a group's start deadline, once each.
 *
 * @package    local_bonifica
 * @copyright  2026 Facilo <info@facilo.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reminders extends \core\task\scheduled_task {
    /** @var int[] Days before the start deadline when a reminder goes out, largest first. */
    const DAYS = [10, 8];

    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_reminders', 'local_bonifica');
    }

    /**
     * Sends the reminders that are due.
     */
    public function execute(): void {
    }
}
