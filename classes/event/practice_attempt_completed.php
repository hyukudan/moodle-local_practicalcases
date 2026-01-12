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
 * Event triggered when a practice attempt is completed.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class practice_attempt_completed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'local_cp_practice_attempts';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpracticeattemptcompleted', 'local_casospracticos');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $caseid = $this->other['caseid'] ?? 'unknown';
        $score = $this->other['percentage'] ?? 0;
        return "The user with id '$this->userid' completed a practice attempt (id '$this->objectid') " .
               "for case id '$caseid' with a score of {$score}%.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/local/casospracticos/review_attempt.php', ['id' => $this->objectid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' must be set.');
        }
        if (!isset($this->other['caseid'])) {
            throw new \coding_exception('The \'caseid\' must be set in other.');
        }
    }

    /**
     * Create event from attempt data.
     *
     * @param int $attemptid The attempt ID.
     * @param int $caseid The case ID.
     * @param float $score The score obtained.
     * @param float $maxscore The maximum score.
     * @param float $percentage The percentage score.
     * @param \context $context The context.
     * @return practice_attempt_completed
     */
    public static function create_from_attempt($attemptid, $caseid, $score, $maxscore, $percentage, $context = null) {
        if ($context === null) {
            $context = \context_system::instance();
        }

        $event = self::create([
            'objectid' => $attemptid,
            'context' => $context,
            'other' => [
                'caseid' => $caseid,
                'score' => $score,
                'maxscore' => $maxscore,
                'percentage' => round($percentage, 1),
            ],
        ]);
        return $event;
    }
}
