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

namespace local_casospracticos\event;

/**
 * Event triggered when a user exceeds the rate limit.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rate_limit_exceeded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventratelimitexceeded', 'local_casospracticos');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $operation = $this->other['operation'] ?? 'unknown';
        $type = $this->other['type'] ?? 'unknown';
        $count = $this->other['count'] ?? 0;

        return "The user with id '$this->userid' exceeded the rate limit for operation '$operation' " .
               "(type: $type, count: $count requests).";
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->other['operation'])) {
            throw new \coding_exception('The \'operation\' must be set in other.');
        }
        if (!isset($this->other['type'])) {
            throw new \coding_exception('The \'type\' must be set in other.');
        }
    }
}
