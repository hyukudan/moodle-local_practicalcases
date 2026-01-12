<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Scheduled task to expire old timed practice attempts.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos\task;

use local_casospracticos\timed_attempt_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to expire old timed practice attempts.
 */
class expire_timed_attempts extends \core\task\scheduled_task {

    /**
     * Get task name.
     */
    public function get_name() {
        return get_string('task:expiretimedattempts', 'local_casospracticos');
    }

    /**
     * Execute task.
     */
    public function execute() {
        $count = timed_attempt_manager::expire_old_attempts();

        if ($count > 0) {
            mtrace("Expired {$count} timed practice attempts.");
        }
    }
}
