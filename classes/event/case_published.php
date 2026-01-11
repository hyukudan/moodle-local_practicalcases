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
 * Event triggered when a case is published.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class case_published extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_cp_cases';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcasepublished', 'local_casospracticos');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' published the practical case with id '$this->objectid'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/casospracticos/case_view.php', ['id' => $this->objectid]);
    }

    /**
     * Create event from case record.
     *
     * @param object $case The case record.
     * @param \context $context The context.
     * @return case_published
     */
    public static function create_from_case($case, $context = null) {
        if ($context === null) {
            $context = \context_system::instance();
        }

        $event = self::create([
            'objectid' => $case->id,
            'context' => $context,
            'other' => [
                'name' => $case->name,
            ],
        ]);
        $event->add_record_snapshot('local_cp_cases', $case);
        return $event;
    }
}
