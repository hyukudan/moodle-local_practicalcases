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

namespace local_casospracticos;

/**
 * Statistics manager for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stats_manager {

    /**
     * Get comprehensive statistics for a case.
     *
     * @param int $caseid Case ID.
     * @return object Statistics object.
     */
    public static function get_case_stats(int $caseid): object {
        global $DB;

        $stats = new \stdClass();

        // Basic usage stats from local_cp_usage.
        $sql = "SELECT COALESCE(SUM(views), 0) as total_views,
                       COALESCE(SUM(insertions), 0) as total_insertions
                FROM {local_cp_usage}
                WHERE caseid = :caseid";
        $usage = $DB->get_record_sql($sql, ['caseid' => $caseid]);
        $stats->total_views = $usage->total_views ?? 0;
        $stats->total_insertions = $usage->total_insertions ?? 0;

        // Practice attempts stats.
        if ($DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            $sql = "SELECT COUNT(*) as attempts,
                           COALESCE(AVG(percentage), 0) as avg_score
                    FROM {local_cp_practice_attempts}
                    WHERE caseid = :caseid AND status = 'finished'";
            $practice = $DB->get_record_sql($sql, ['caseid' => $caseid]);
            $stats->practice_attempts = $practice->attempts ?? 0;
            $stats->avg_score = $practice->avg_score ?? 0;
        } else {
            $stats->practice_attempts = 0;
            $stats->avg_score = 0;
        }

        // Question-level statistics.
        $stats->question_stats = self::get_question_stats($caseid);

        // Quiz usage details.
        $stats->quiz_usage = self::get_quiz_usage($caseid);

        // Recent practice attempts.
        $stats->recent_attempts = self::get_recent_attempts($caseid, 10);

        // Score distribution.
        $stats->score_distribution = self::get_score_distribution($caseid);

        return $stats;
    }

    /**
     * Get per-question statistics.
     *
     * @param int $caseid Case ID.
     * @return array Question stats.
     */
    public static function get_question_stats(int $caseid): array {
        global $DB;

        // Get questions for this case.
        $questions = $DB->get_records('local_cp_questions', ['caseid' => $caseid], 'sortorder ASC');

        if (empty($questions)) {
            return [];
        }

        $stats = [];

        // Check if practice responses table exists.
        if (!$DB->get_manager()->table_exists('local_cp_practice_responses')) {
            // Return questions without stats.
            foreach ($questions as $question) {
                $stat = clone $question;
                $stat->attempts = 0;
                $stat->correct_rate = 0;
                $stat->avg_points = 0;
                $stats[] = $stat;
            }
            return $stats;
        }

        // Get all question stats in a single query (optimized - avoids N+1).
        $questionids = array_column($questions, 'id');
        if (empty($questionids)) {
            return $stats;
        }

        list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $sql = "SELECT questionid,
                       COUNT(*) as attempts,
                       COALESCE(AVG(CASE WHEN iscorrect = 1 THEN 100 ELSE 0 END), 0) as correct_rate,
                       COALESCE(AVG(score), 0) as avg_points
                FROM {local_cp_practice_responses}
                WHERE questionid $insql
                GROUP BY questionid";
        $allstats = $DB->get_records_sql($sql, $params);

        // Map stats to questions.
        foreach ($questions as $question) {
            $stat = clone $question;
            if (isset($allstats[$question->id])) {
                $qstats = $allstats[$question->id];
                $stat->attempts = $qstats->attempts ?? 0;
                $stat->correct_rate = $qstats->correct_rate ?? 0;
                $stat->avg_points = $qstats->avg_points ?? 0;
            } else {
                $stat->attempts = 0;
                $stat->correct_rate = 0;
                $stat->avg_points = 0;
            }
            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * Get quiz usage details for a case.
     *
     * @param int $caseid Case ID.
     * @return array Quiz usage records.
     */
    public static function get_quiz_usage(int $caseid): array {
        global $DB;

        $sql = "SELECT u.id, u.quizid, u.courseid, u.insertions, u.lastused,
                       q.name as quizname, c.fullname as coursename, cm.id as cmid
                FROM {local_cp_usage} u
                JOIN {quiz} q ON q.id = u.quizid
                JOIN {course} c ON c.id = u.courseid
                JOIN {course_modules} cm ON cm.instance = q.id AND cm.course = c.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
                WHERE u.caseid = :caseid AND u.quizid IS NOT NULL
                ORDER BY u.lastused DESC";

        return $DB->get_records_sql($sql, ['caseid' => $caseid]);
    }

    /**
     * Get recent practice attempts.
     *
     * @param int $caseid Case ID.
     * @param int $limit Number of attempts to return.
     * @return array Recent attempts.
     */
    public static function get_recent_attempts(int $caseid, int $limit = 10): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            return [];
        }

        $sql = "SELECT a.*, u.firstname, u.lastname, u.email
                FROM {local_cp_practice_attempts} a
                JOIN {user} u ON u.id = a.userid
                WHERE a.caseid = :caseid AND a.status = 'finished'
                ORDER BY a.timefinished DESC";

        return $DB->get_records_sql($sql, ['caseid' => $caseid], 0, $limit);
    }

    /**
     * Get score distribution for a case.
     *
     * @param int $caseid Case ID.
     * @return array Score distribution.
     */
    public static function get_score_distribution(int $caseid): array {
        global $DB;

        $distribution = [
            '0-20' => 0,
            '21-40' => 0,
            '41-60' => 0,
            '61-80' => 0,
            '81-100' => 0,
        ];

        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            return $distribution;
        }

        $sql = "SELECT
                    CASE
                        WHEN percentage <= 20 THEN '0-20'
                        WHEN percentage <= 40 THEN '21-40'
                        WHEN percentage <= 60 THEN '41-60'
                        WHEN percentage <= 80 THEN '61-80'
                        ELSE '81-100'
                    END as score_range,
                    COUNT(*) as count
                FROM {local_cp_practice_attempts}
                WHERE caseid = :caseid AND status = 'finished'
                GROUP BY score_range";

        $results = $DB->get_records_sql($sql, ['caseid' => $caseid]);

        foreach ($results as $result) {
            if (isset($distribution[$result->score_range])) {
                $distribution[$result->score_range] = (int)$result->count;
            }
        }

        return $distribution;
    }

    /**
     * Record a case view.
     *
     * @param int $caseid Case ID.
     */
    public static function record_view(int $caseid): void {
        global $DB;

        $record = $DB->get_record('local_cp_usage', ['caseid' => $caseid, 'quizid' => null]);

        if ($record) {
            $record->views++;
            $record->lastused = time();
            $DB->update_record('local_cp_usage', $record);
        } else {
            $record = new \stdClass();
            $record->caseid = $caseid;
            $record->quizid = null;
            $record->courseid = null;
            $record->views = 1;
            $record->insertions = 0;
            $record->lastused = time();
            $record->timecreated = time();
            $DB->insert_record('local_cp_usage', $record);
        }
    }

    /**
     * Record a quiz insertion.
     *
     * @param int $caseid Case ID.
     * @param int $quizid Quiz ID.
     * @param int $courseid Course ID.
     */
    public static function record_insertion(int $caseid, int $quizid, int $courseid): void {
        global $DB;

        $record = $DB->get_record('local_cp_usage', ['caseid' => $caseid, 'quizid' => $quizid]);

        if ($record) {
            $record->insertions++;
            $record->lastused = time();
            $DB->update_record('local_cp_usage', $record);
        } else {
            $record = new \stdClass();
            $record->caseid = $caseid;
            $record->quizid = $quizid;
            $record->courseid = $courseid;
            $record->views = 0;
            $record->insertions = 1;
            $record->lastused = time();
            $record->timecreated = time();
            $DB->insert_record('local_cp_usage', $record);
        }
    }

    /**
     * Record a practice attempt.
     *
     * @param int $caseid Case ID.
     * @param int $userid User ID.
     * @param float $score Score obtained.
     * @param float $maxscore Maximum score.
     * @param array $responses Question responses.
     * @return int Attempt ID.
     */
    public static function record_practice_attempt(int $caseid, int $userid, float $score,
            float $maxscore, array $responses): int {
        global $DB;

        // Check if table exists.
        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            return 0;
        }

        $percentage = $maxscore > 0 ? ($score / $maxscore) * 100 : 0;

        $attempt = new \stdClass();
        $attempt->caseid = $caseid;
        $attempt->userid = $userid;
        $attempt->score = $score;
        $attempt->maxscore = $maxscore;
        $attempt->percentage = $percentage;
        $attempt->status = 'finished';
        $attempt->timestarted = time() - 60; // Approximate.
        $attempt->timefinished = time();
        $attempt->timecreated = time();

        $attemptid = $DB->insert_record('local_cp_practice_attempts', $attempt);

        // Record individual responses.
        if ($DB->get_manager()->table_exists('local_cp_practice_responses')) {
            foreach ($responses as $questionid => $response) {
                $resp = new \stdClass();
                $resp->attemptid = $attemptid;
                $resp->questionid = $questionid;
                $resp->response = is_array($response['selected']) ?
                    json_encode($response['selected']) : $response['selected'];
                $resp->score = $response['score'] ?? 0;
                $resp->iscorrect = ($response['correct'] ?? false) ? 1 : 0;
                $resp->timecreated = time();
                $DB->insert_record('local_cp_practice_responses', $resp);
            }
        }

        // Trigger event.
        $event = \local_casospracticos\event\practice_attempt_completed::create_from_attempt(
            $attemptid, $caseid, $score, $maxscore, $percentage
        );
        $event->trigger();

        // Check for achievements (optional gamification).
        achievements_manager::check_achievements($userid, $caseid, $percentage);

        return $attemptid;
    }

    /**
     * Get global statistics for the plugin dashboard.
     *
     * @return object Global stats.
     */
    public static function get_global_stats(): object {
        global $DB;

        $stats = new \stdClass();

        // Total counts.
        $stats->total_cases = $DB->count_records('local_cp_cases');
        $stats->published_cases = $DB->count_records('local_cp_cases', ['status' => 'published']);
        $stats->total_questions = $DB->count_records('local_cp_questions');
        $stats->total_categories = $DB->count_records('local_cp_categories');

        // Usage totals.
        $sql = "SELECT COALESCE(SUM(views), 0) as views, COALESCE(SUM(insertions), 0) as insertions
                FROM {local_cp_usage}";
        $usage = $DB->get_record_sql($sql);
        $stats->total_views = $usage->views ?? 0;
        $stats->total_insertions = $usage->insertions ?? 0;

        // Practice stats.
        if ($DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            $stats->total_practice_attempts = $DB->count_records('local_cp_practice_attempts', ['status' => 'finished']);
        } else {
            $stats->total_practice_attempts = 0;
        }

        // Most used cases.
        $sql = "SELECT c.id, c.name, COALESCE(SUM(u.views), 0) + COALESCE(SUM(u.insertions), 0) as usage_count
                FROM {local_cp_cases} c
                LEFT JOIN {local_cp_usage} u ON u.caseid = c.id
                GROUP BY c.id, c.name
                ORDER BY usage_count DESC";
        $stats->top_cases = $DB->get_records_sql($sql, [], 0, 5);

        // Recent activity.
        $stats->recent_cases = $DB->get_records('local_cp_cases', [], 'timemodified DESC', 'id, name, status, timemodified', 0, 5);

        return $stats;
    }
}
