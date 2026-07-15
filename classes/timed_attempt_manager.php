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
        $questions = array_values(question_manager::filter_practice_questions(
            question_manager::get_by_case($caseid)
        ));
        shuffle($questions);
        $questionids = array_map(function($q) {
            return (int) $q->id;
        }, $questions);

        $attempt = new \stdClass();
        $attempt->caseid = $caseid;
        $attempt->userid = $userid;
        $attempt->token = self::generate_token();
        $attempt->timelimit = $timelimit * 60; // Convert to seconds.
        // Persist the shuffled question order so render and submit use the same set/order.
        $attempt->questionorder = json_encode($questionids);
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
     * Finalize an expired attempt from its last autosaved responses.
     *
     * This transition is server-authoritative and intentionally does not need
     * a browser sesskey: ownership, current status and deadline are all checked
     * here, and finish_attempt() performs an idempotent conditional update.
     *
     * @param int $attemptid Attempt ID.
     * @param int $userid Expected owner.
     * @return bool True when the attempt is finalized (including a lost race).
     */
    public static function finalize_expired_attempt(int $attemptid, int $userid): bool {
        $attempt = self::get_attempt($attemptid);
        if (!$attempt || (int) $attempt->userid !== $userid) {
            return false;
        }
        if ($attempt->status !== self::STATUS_INPROGRESS) {
            return $attempt->status === self::STATUS_FINISHED;
        }
        if ((int) $attempt->timestarted + (int) $attempt->timelimit > time()) {
            return false;
        }

        $questions = array_values(question_manager::filter_practice_questions(
            question_manager::get_by_case_with_answers((int) $attempt->caseid)
        ));
        $questionorder = json_decode($attempt->questionorder ?? '', true);
        if (is_array($questionorder) && $questionorder) {
            $byid = [];
            foreach ($questions as $question) {
                $byid[(int) $question->id] = $question;
            }
            $ordered = [];
            foreach ($questionorder as $questionid) {
                if (isset($byid[(int) $questionid])) {
                    $ordered[] = $byid[(int) $questionid];
                }
            }
            $questions = $ordered;
        }

        $saved = self::get_saved_responses($attemptid);
        $submissiondata = [];
        foreach ($questions as $question) {
            if (array_key_exists((string) $question->id, $saved)
                    || array_key_exists((int) $question->id, $saved)) {
                $submissiondata['q' . $question->id] = $saved[$question->id];
            }
        }
        $scored = practice_engine::score_submission($questions, $submissiondata);
        $responsedata = [];
        foreach ($scored['results'] as $questionid => $result) {
            $responsedata[$questionid] = [
                'selected' => $result->selectedids ?? ($result->response ?? ''),
                'score' => $result->score ?? 0,
                'correct' => $result->correct ?? false,
                'requiresgrading' => $result->requiresgrading ?? false,
            ];
        }

        self::finish_attempt($attemptid, $scored['score'], $scored['maxscore'], $responsedata,
            (int) $attempt->timelimit, $scored['gradingstatus']);
        $final = self::get_attempt($attemptid);
        return $final && $final->status === self::STATUS_FINISHED;
    }

    /**
     * Finish an attempt.
     *
     * @param int $attemptid Attempt ID
     * @param float $score Score achieved
     * @param float $maxscore Maximum possible score
     * @param array $responsedata Response data
     * @param int $timespent Time spent in seconds
     * @param string $gradingstatus auto, needsgrading or graded.
     */
    public static function finish_attempt(int $attemptid, float $score, float $maxscore, array $responsedata,
            int $timespent, string $gradingstatus = 'auto'): void {
        global $DB;

        $attempt = self::get_attempt($attemptid);
        if (!$attempt) {
            return;
        }

        // Only finalize an attempt that is still in progress. This makes the
        // transition idempotent: a duplicate/concurrent submit will not re-run
        // stats recording or re-trigger the submitted event.
        if ($attempt->status !== self::STATUS_INPROGRESS) {
            return;
        }

        if (!in_array($gradingstatus, ['auto', 'needsgrading', 'graded'], true)) {
            throw new \coding_exception('Invalid timed attempt grading status');
        }
        $percentage = $gradingstatus === 'needsgrading'
            ? null
            : ($maxscore > 0 ? round(($score / $maxscore) * 100, 2) : 0);

        // Conditional update: only the request that finds the row still in
        // progress wins the transition. We pre-count, run a status-guarded
        // UPDATE, then re-read to confirm this request performed the finalize.
        $now = time();
        $params = ['id' => $attemptid, 'status' => self::STATUS_INPROGRESS];
        $matched = $DB->count_records_select(
            self::TABLE,
            'id = :id AND status = :status',
            $params
        );
        if ($matched < 1) {
            // Lost the race; another request already finalized this attempt.
            return;
        }

        $DB->execute(
            'UPDATE {' . self::TABLE . '}
                SET status = :newstatus,
                    score = :score,
                    maxscore = :maxscore,
                    percentage = :percentage,
                    gradingstatus = :gradingstatus,
                    responses = :responses,
                    timesubmitted = :timesubmitted
              WHERE id = :id AND status = :oldstatus',
            [
                'newstatus' => self::STATUS_FINISHED,
                'score' => $score,
                'maxscore' => $maxscore,
                'percentage' => $percentage,
                'gradingstatus' => $gradingstatus,
                'responses' => json_encode($responsedata),
                'timesubmitted' => $now,
                'id' => $attemptid,
                'oldstatus' => self::STATUS_INPROGRESS,
            ]
        );

        // Verify we won the transition before recording stats / triggering the event.
        $finalized = $DB->get_record(self::TABLE, ['id' => $attemptid]);
        if (!$finalized || $finalized->status !== self::STATUS_FINISHED
                || (int) $finalized->timesubmitted !== $now) {
            return;
        }

        // Also record in regular stats for consistency.
        stats_manager::record_practice_attempt(
            $attempt->caseid,
            $attempt->userid,
            $score,
            $maxscore,
            $responsedata,
            $gradingstatus
        );

        // Trigger event.
        if ($gradingstatus !== 'needsgrading') {
            $event = \local_casospracticos\event\timed_attempt_submitted::create([
            'context' => \context_system::instance(),
            'objectid' => $attemptid,
            'userid' => $attempt->userid,
            'other' => [
                'caseid' => $attempt->caseid,
                'score' => $score,
                'maxscore' => $maxscore,
                'percentage' => $percentage,
                'timespent' => $timespent,
            ],
            ]);
            $event->trigger();
        }
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

        $attempts = $DB->get_records_select(self::TABLE, $sql, $params, '', 'id, userid');
        $count = 0;
        foreach ($attempts as $attempt) {
            if (self::finalize_expired_attempt((int) $attempt->id, (int) $attempt->userid)) {
                $count++;
            }
        }
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

        // Save the responses with a conditional update keyed on userid AND
        // status still in-progress. This prevents an autosave that read an
        // in-progress attempt from overwriting the responses of an attempt that
        // was finalized (submitted) in the meantime.
        $DB->execute(
            'UPDATE {' . self::TABLE . '}
                SET responses = :responses
              WHERE id = :id AND userid = :userid AND status = :status',
            [
                'responses' => json_encode($responses),
                'id' => $attemptid,
                'userid' => $userid,
                'status' => self::STATUS_INPROGRESS,
            ]
        );

        return true;
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
