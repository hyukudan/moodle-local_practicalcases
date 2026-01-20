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
 * Manager for timed practice attempts.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage timed practice attempts.
 */
class timed_attempt_manager {

    /** @var string Table name */
    const TABLE = 'local_cp_timed_attempts';

    /** @var string Status: in progress */
    const STATUS_INPROGRESS = 'inprogress';

    /** @var string Status: finished */
    const STATUS_FINISHED = 'finished';

    /** @var string Status: expired */
    const STATUS_EXPIRED = 'expired';

    /**
     * Start a new timed attempt.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     * @param int $timelimit Time limit in minutes
     * @return int Attempt ID
     */
    public static function start_attempt(int $caseid, int $userid, int $timelimit): int {
        global $DB;

        // Clean up any unfinished attempts for this user/case.
        self::cleanup_unfinished_attempts($caseid, $userid);

        // Get questions and shuffle them.
        $questions = question_manager::get_by_case($caseid);
        shuffle($questions);
        $questionids = array_column($questions, 'id');

        $attempt = new \stdClass();
        $attempt->caseid = $caseid;
        $attempt->userid = $userid;
        $attempt->token = self::generate_token();
        $attempt->timelimit = $timelimit * 60; // Convert to seconds.
        $attempt->timestarted = time();
        $attempt->status = self::STATUS_INPROGRESS;
        $attempt->timecreated = time();

        return $DB->insert_record(self::TABLE, $attempt);
    }

    /**
     * Get an attempt by ID.
     *
     * @param int $attemptid Attempt ID
     * @return object|false Attempt record
     */
    public static function get_attempt(int $attemptid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $attemptid]);
    }

    /**
     * Get time left in seconds for an attempt.
     *
     * @param int $attemptid Attempt ID
     * @return int Time left in seconds (0 if expired)
     */
    public static function get_time_left(int $attemptid): int {
        $attempt = self::get_attempt($attemptid);
        if (!$attempt || $attempt->status !== self::STATUS_INPROGRESS) {
            return 0;
        }

        $timeend = $attempt->timestarted + $attempt->timelimit;
        $timeleft = $timeend - time();
        return max(0, $timeleft);
    }

    /**
     * Finish an attempt.
     *
     * @param int $attemptid Attempt ID
     * @param float $score Score achieved
     * @param float $maxscore Maximum possible score
     * @param array $responsedata Response data
     * @param int $timespent Time spent in seconds
     */
    public static function finish_attempt(int $attemptid, float $score, float $maxscore, array $responsedata, int $timespent): void {
        global $DB;

        $attempt = self::get_attempt($attemptid);
        if (!$attempt) {
            return;
        }

        $update = new \stdClass();
        $update->id = $attemptid;
        $update->status = self::STATUS_FINISHED;
        $update->score = $score;
        $update->maxscore = $maxscore;
        $update->percentage = $maxscore > 0 ? round(($score / $maxscore) * 100, 2) : 0;
        $update->responses = json_encode($responsedata);
        $update->timesubmitted = time();

        $DB->update_record(self::TABLE, $update);

        // Also record in regular stats for consistency.
        stats_manager::record_practice_attempt(
            $attempt->caseid,
            $attempt->userid,
            $score,
            $maxscore,
            $responsedata
        );

        // Trigger event.
        $event = \local_casospracticos\event\timed_attempt_submitted::create([
            'context' => \context_system::instance(),
            'objectid' => $attemptid,
            'userid' => $attempt->userid,
            'other' => [
                'caseid' => $attempt->caseid,
                'score' => $score,
                'maxscore' => $maxscore,
                'percentage' => $update->percentage,
                'timespent' => $timespent,
            ],
        ]);
        $event->trigger();
    }

    /**
     * Get all attempts for a user.
     *
     * @param int $userid User ID
     * @param int|null $caseid Optional case ID filter
     * @return array Array of attempts
     */
    public static function get_user_attempts(int $userid, ?int $caseid = null): array {
        global $DB;

        $params = ['userid' => $userid];
        if ($caseid !== null) {
            $params['caseid'] = $caseid;
        }

        return $DB->get_records(self::TABLE, $params, 'timecreated DESC');
    }

    /**
     * Get best attempt for a user on a case.
     *
     * @param int $userid User ID
     * @param int $caseid Case ID
     * @return object|false Best attempt or false
     */
    public static function get_best_attempt(int $userid, int $caseid) {
        global $DB;

        $sql = "SELECT *
                FROM {" . self::TABLE . "}
                WHERE userid = :userid
                  AND caseid = :caseid
                  AND status = :status
                ORDER BY percentage DESC, timesubmitted ASC
                LIMIT 1";

        return $DB->get_record_sql($sql, [
            'userid' => $userid,
            'caseid' => $caseid,
            'status' => self::STATUS_FINISHED
        ]);
    }

    /**
     * Clean up unfinished attempts for a user on a case.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     */
    private static function cleanup_unfinished_attempts(int $caseid, int $userid): void {
        global $DB;

        $DB->delete_records(self::TABLE, [
            'caseid' => $caseid,
            'userid' => $userid,
            'status' => self::STATUS_INPROGRESS
        ]);
    }

    /**
     * Expire old in-progress attempts (called by scheduled task).
     *
     * @return int Number of attempts expired
     */
    public static function expire_old_attempts(): int {
        global $DB;

        // Calculate expired attempts: timestarted + timelimit < now
        $sql = 'status = :status AND (timestarted + timelimit) < :now';
        $params = ['status' => self::STATUS_INPROGRESS, 'now' => time()];

        $count = $DB->count_records_select(self::TABLE, $sql, $params);

        $DB->set_field_select(
            self::TABLE,
            'status',
            self::STATUS_EXPIRED,
            $sql,
            $params
        );

        return $count;
    }

    /**
     * Save partial responses for auto-save functionality.
     *
     * @param int $attemptid Attempt ID
     * @param int $userid User ID (for verification)
     * @param array $responses Array of question responses
     * @return bool Success
     */
    public static function save_responses(int $attemptid, int $userid, array $responses): bool {
        global $DB;

        $attempt = self::get_attempt($attemptid);
        if (!$attempt) {
            return false;
        }

        // Verify attempt belongs to user.
        if ((int)$attempt->userid !== $userid) {
            return false;
        }

        // Only save if attempt is still in progress.
        if ($attempt->status !== self::STATUS_INPROGRESS) {
            return false;
        }

        // Check if time has expired.
        if (self::get_time_left($attemptid) <= 0) {
            return false;
        }

        // Save the responses.
        $update = new \stdClass();
        $update->id = $attemptid;
        $update->responses = json_encode($responses);

        return $DB->update_record(self::TABLE, $update);
    }

    /**
     * Get saved responses for an attempt.
     *
     * @param int $attemptid Attempt ID
     * @return array Saved responses or empty array
     */
    public static function get_saved_responses(int $attemptid): array {
        $attempt = self::get_attempt($attemptid);
        if (!$attempt || empty($attempt->responses)) {
            return [];
        }

        $responses = json_decode($attempt->responses, true);
        return is_array($responses) ? $responses : [];
    }

    /**
     * Generate a unique secure token for an attempt.
     *
     * @return string 64-character hex token
     */
    private static function generate_token(): string {
        return bin2hex(random_bytes(32));
    }
}
