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
 * Event triggered when a user earns an achievement.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievement_earned extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_cp_achievements';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventachievementearned', 'local_casospracticos');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $type = $this->other['achievementtype'] ?? 'unknown';
        return "The user with id '$this->userid' earned the achievement '$type'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/casospracticos/achievements.php');
    }

    /**
     * Create event from achievement data.
     *
     * @param int $userid User ID.
     * @param string $achievementtype Achievement type.
     * @param int|null $caseid Related case ID.
     * @param \context $context The context.
     * @return achievement_earned
     */
    public static function create_from_achievement($userid, $achievementtype, $caseid = null, $context = null) {
        if ($context === null) {
            $context = \context_system::instance();
        }

        $event = self::create([
            'objectid' => 0, // Will be set after insert.
            'context' => $context,
            'userid' => $userid,
            'other' => [
                'achievementtype' => $achievementtype,
                'caseid' => $caseid,
            ],
        ]);
        return $event;
    }
}
