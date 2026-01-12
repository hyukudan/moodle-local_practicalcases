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
 * Achievements manager for gamification.
 *
 * Provides optional integration with external achievement plugins or uses
 * internal achievements table as fallback.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievements_manager {

    /** Achievement: First practice attempt */
    const ACHIEVEMENT_FIRST_ATTEMPT = 'first_attempt';

    /** Achievement: Complete 5 cases */
    const ACHIEVEMENT_FIVE_CASES = 'five_cases';

    /** Achievement: Complete 10 cases */
    const ACHIEVEMENT_TEN_CASES = 'ten_cases';

    /** Achievement: Complete 25 cases */
    const ACHIEVEMENT_TWENTYFIVE_CASES = 'twentyfive_cases';

    /** Achievement: Perfect score (100%) */
    const ACHIEVEMENT_PERFECT_SCORE = 'perfect_score';

    /** Achievement: 5 perfect scores */
    const ACHIEVEMENT_FIVE_PERFECT = 'five_perfect';

    /** Achievement: Pass 10 cases in a row */
    const ACHIEVEMENT_STREAK_10 = 'streak_10';

    /** Achievement: Practice every day for a week */
    const ACHIEVEMENT_WEEK_STREAK = 'week_streak';

    /** Achievement: Try all questions in a category */
    const ACHIEVEMENT_CATEGORY_COMPLETE = 'category_complete';

    /** Achievement: Score above 90% average */
    const ACHIEVEMENT_HIGH_ACHIEVER = 'high_achiever';

    /**
     * Check if gamification is enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return get_config('local_casospracticos', 'enablegamification') ?? true;
    }

    /**
     * Check if an external achievements plugin is available.
     *
     * @return bool
     */
    public static function has_external_plugin(): bool {
        global $DB;

        // Check for common achievement plugin tables.
        $dbman = $DB->get_manager();

        // Check various possible achievement plugin tables.
        $tables = [
            'local_achievements',
            'block_game_achievements',
            'local_gamification_achievements',
            'block_xp_levels',
        ];

        foreach ($tables as $table) {
            if ($dbman->table_exists($table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the name of the external plugin if available.
     *
     * @return string|null
     */
    public static function get_external_plugin_name(): ?string {
        global $DB;
        $dbman = $DB->get_manager();

        if ($dbman->table_exists('local_achievements')) {
            return 'local_achievements';
        }
        if ($dbman->table_exists('block_game_achievements')) {
            return 'block_game';
        }
        if ($dbman->table_exists('block_xp_levels')) {
            return 'block_xp';
        }

        return null;
    }

    /**
     * Check and award achievements after a practice attempt.
     *
     * @param int $userid User ID.
     * @param int $caseid Case ID.
     * @param float $percentage Score percentage.
     */
    public static function check_achievements(int $userid, int $caseid, float $percentage): void {
        if (!self::is_enabled()) {
            return;
        }

        global $DB;

        // Check if achievements table exists.
        if (!$DB->get_manager()->table_exists('local_cp_achievements')) {
            return;
        }

        $achievements = [];

        // Get user's practice stats.
        $stats = self::get_user_stats($userid);

        // First attempt ever.
        if ($stats->total_attempts == 1) {
            $achievements[] = self::ACHIEVEMENT_FIRST_ATTEMPT;
        }

        // Milestone achievements.
        if ($stats->unique_cases >= 5 && !self::has_achievement($userid, self::ACHIEVEMENT_FIVE_CASES)) {
            $achievements[] = self::ACHIEVEMENT_FIVE_CASES;
        }
        if ($stats->unique_cases >= 10 && !self::has_achievement($userid, self::ACHIEVEMENT_TEN_CASES)) {
            $achievements[] = self::ACHIEVEMENT_TEN_CASES;
        }
        if ($stats->unique_cases >= 25 && !self::has_achievement($userid, self::ACHIEVEMENT_TWENTYFIVE_CASES)) {
            $achievements[] = self::ACHIEVEMENT_TWENTYFIVE_CASES;
        }

        // Perfect score.
        if ($percentage >= 100) {
            if (!self::has_achievement($userid, self::ACHIEVEMENT_PERFECT_SCORE)) {
                $achievements[] = self::ACHIEVEMENT_PERFECT_SCORE;
            }

            // 5 perfect scores.
            if ($stats->perfect_scores >= 5 && !self::has_achievement($userid, self::ACHIEVEMENT_FIVE_PERFECT)) {
                $achievements[] = self::ACHIEVEMENT_FIVE_PERFECT;
            }
        }

        // High achiever (90%+ average after 10+ attempts).
        if ($stats->total_attempts >= 10 && $stats->average_score >= 90 &&
            !self::has_achievement($userid, self::ACHIEVEMENT_HIGH_ACHIEVER)) {
            $achievements[] = self::ACHIEVEMENT_HIGH_ACHIEVER;
        }

        // Check streak.
        $passthreshold = get_config('local_casospracticos', 'passthreshold') ?? 70;
        if ($percentage >= $passthreshold) {
            $streak = self::calculate_streak($userid, $passthreshold);
            if ($streak >= 10 && !self::has_achievement($userid, self::ACHIEVEMENT_STREAK_10)) {
                $achievements[] = self::ACHIEVEMENT_STREAK_10;
            }
        }

        // Check week streak.
        if (self::has_week_streak($userid) && !self::has_achievement($userid, self::ACHIEVEMENT_WEEK_STREAK)) {
            $achievements[] = self::ACHIEVEMENT_WEEK_STREAK;
        }

        // Award achievements.
        foreach ($achievements as $achievement) {
            self::award_achievement($userid, $achievement, $caseid);
        }
    }

    /**
     * Get user's practice statistics.
     *
     * @param int $userid User ID.
     * @return object Stats object.
     */
    public static function get_user_stats(int $userid): object {
        global $DB;

        $stats = new \stdClass();

        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            $stats->total_attempts = 0;
            $stats->unique_cases = 0;
            $stats->perfect_scores = 0;
            $stats->average_score = 0;
            return $stats;
        }

        $sql = "SELECT
                    COUNT(*) as total_attempts,
                    COUNT(DISTINCT caseid) as unique_cases,
                    SUM(CASE WHEN percentage >= 100 THEN 1 ELSE 0 END) as perfect_scores,
                    AVG(percentage) as average_score
                FROM {local_cp_practice_attempts}
                WHERE userid = :userid AND status = 'finished'";

        $result = $DB->get_record_sql($sql, ['userid' => $userid]);

        $stats->total_attempts = (int) ($result->total_attempts ?? 0);
        $stats->unique_cases = (int) ($result->unique_cases ?? 0);
        $stats->perfect_scores = (int) ($result->perfect_scores ?? 0);
        $stats->average_score = (float) ($result->average_score ?? 0);

        return $stats;
    }

    /**
     * Check if user has a specific achievement.
     *
     * @param int $userid User ID.
     * @param string $achievementtype Achievement type.
     * @return bool
     */
    public static function has_achievement(int $userid, string $achievementtype): bool {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_achievements')) {
            return false;
        }

        return $DB->record_exists('local_cp_achievements', [
            'userid' => $userid,
            'achievementtype' => $achievementtype,
        ]);
    }

    /**
     * Award an achievement to a user.
     *
     * @param int $userid User ID.
     * @param string $achievementtype Achievement type.
     * @param int|null $caseid Related case ID.
     */
    public static function award_achievement(int $userid, string $achievementtype, ?int $caseid = null): void {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_achievements')) {
            return;
        }

        // Don't award if already has it.
        if (self::has_achievement($userid, $achievementtype)) {
            return;
        }

        $achievement = new \stdClass();
        $achievement->userid = $userid;
        $achievement->achievementtype = $achievementtype;
        $achievement->caseid = $caseid;
        $achievement->timecreated = time();

        $DB->insert_record('local_cp_achievements', $achievement);

        // Trigger event.
        $event = \local_casospracticos\event\achievement_earned::create_from_achievement(
            $userid, $achievementtype, $caseid
        );
        $event->trigger();

        // Try to integrate with external plugin.
        self::notify_external_plugin($userid, $achievementtype);
    }

    /**
     * Notify external achievement plugin if available.
     *
     * @param int $userid User ID.
     * @param string $achievementtype Achievement type.
     */
    protected static function notify_external_plugin(int $userid, string $achievementtype): void {
        $plugin = self::get_external_plugin_name();

        if ($plugin === null) {
            return;
        }

        // Integration with block_xp (experience points).
        if ($plugin === 'block_xp') {
            self::award_xp($userid, $achievementtype);
        }

        // Other plugin integrations can be added here.
    }

    /**
     * Award XP points if block_xp is installed.
     *
     * @param int $userid User ID.
     * @param string $achievementtype Achievement type.
     */
    protected static function award_xp(int $userid, string $achievementtype): void {
        // Map achievements to XP points.
        $xpmap = [
            self::ACHIEVEMENT_FIRST_ATTEMPT => 10,
            self::ACHIEVEMENT_FIVE_CASES => 50,
            self::ACHIEVEMENT_TEN_CASES => 100,
            self::ACHIEVEMENT_TWENTYFIVE_CASES => 250,
            self::ACHIEVEMENT_PERFECT_SCORE => 25,
            self::ACHIEVEMENT_FIVE_PERFECT => 100,
            self::ACHIEVEMENT_STREAK_10 => 150,
            self::ACHIEVEMENT_WEEK_STREAK => 200,
            self::ACHIEVEMENT_CATEGORY_COMPLETE => 75,
            self::ACHIEVEMENT_HIGH_ACHIEVER => 300,
        ];

        $xp = $xpmap[$achievementtype] ?? 10;

        // Try to use block_xp API if available.
        if (class_exists('\block_xp\local\xp\user_state_store')) {
            try {
                $world = \block_xp\di::get('course_world_factory')->get_world(SITEID);
                $store = $world->get_store();
                $store->increase($userid, $xp);
            } catch (\Exception $e) {
                // Plugin might not be configured, ignore.
                debugging('block_xp integration failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Calculate current passing streak.
     *
     * @param int $userid User ID.
     * @param float $threshold Pass threshold.
     * @return int Streak count.
     */
    protected static function calculate_streak(int $userid, float $threshold): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            return 0;
        }

        $sql = "SELECT id, percentage
                FROM {local_cp_practice_attempts}
                WHERE userid = :userid AND status = 'finished'
                ORDER BY timefinished DESC";

        $attempts = $DB->get_records_sql($sql, ['userid' => $userid]);

        $streak = 0;
        foreach ($attempts as $attempt) {
            if ($attempt->percentage >= $threshold) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Check if user has practiced every day for the last 7 days.
     *
     * @param int $userid User ID.
     * @return bool
     */
    protected static function has_week_streak(int $userid): bool {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_practice_attempts')) {
            return false;
        }

        // Get unique days with attempts in the last 7 days.
        $sql = "SELECT DISTINCT DATE(FROM_UNIXTIME(timefinished)) as attemptdate
                FROM {local_cp_practice_attempts}
                WHERE userid = :userid
                AND status = 'finished'
                AND timefinished >= :weekago";

        $weekago = time() - (7 * 24 * 60 * 60);
        $days = $DB->get_records_sql($sql, ['userid' => $userid, 'weekago' => $weekago]);

        return count($days) >= 7;
    }

    /**
     * Get all achievements for a user.
     *
     * @param int $userid User ID.
     * @return array List of achievements.
     */
    public static function get_user_achievements(int $userid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_cp_achievements')) {
            return [];
        }

        $achievements = $DB->get_records('local_cp_achievements', ['userid' => $userid], 'timecreated DESC');

        // Enrich with display info.
        foreach ($achievements as $achievement) {
            $achievement->name = get_string('achievement:' . $achievement->achievementtype, 'local_casospracticos');
            $achievement->description = get_string('achievement:' . $achievement->achievementtype . '_desc', 'local_casospracticos');
            $achievement->icon = self::get_achievement_icon($achievement->achievementtype);
        }

        return $achievements;
    }

    /**
     * Get all available achievements with progress for a user.
     *
     * @param int $userid User ID.
     * @return array List of all achievements with progress.
     */
    public static function get_achievements_with_progress(int $userid): array {
        $all = [
            self::ACHIEVEMENT_FIRST_ATTEMPT,
            self::ACHIEVEMENT_FIVE_CASES,
            self::ACHIEVEMENT_TEN_CASES,
            self::ACHIEVEMENT_TWENTYFIVE_CASES,
            self::ACHIEVEMENT_PERFECT_SCORE,
            self::ACHIEVEMENT_FIVE_PERFECT,
            self::ACHIEVEMENT_STREAK_10,
            self::ACHIEVEMENT_WEEK_STREAK,
            self::ACHIEVEMENT_HIGH_ACHIEVER,
        ];

        $stats = self::get_user_stats($userid);
        $earned = self::get_user_achievements($userid);
        $earnedtypes = array_column($earned, 'achievementtype');

        $result = [];
        foreach ($all as $type) {
            $achievement = new \stdClass();
            $achievement->type = $type;
            $achievement->name = get_string('achievement:' . $type, 'local_casospracticos');
            $achievement->description = get_string('achievement:' . $type . '_desc', 'local_casospracticos');
            $achievement->icon = self::get_achievement_icon($type);
            $achievement->earned = in_array($type, $earnedtypes);
            $achievement->progress = self::calculate_progress($type, $stats);

            $result[] = $achievement;
        }

        return $result;
    }

    /**
     * Calculate progress towards an achievement.
     *
     * @param string $type Achievement type.
     * @param object $stats User stats.
     * @return object Progress with current, target, percentage.
     */
    protected static function calculate_progress(string $type, object $stats): object {
        $progress = new \stdClass();

        switch ($type) {
            case self::ACHIEVEMENT_FIRST_ATTEMPT:
                $progress->current = min($stats->total_attempts, 1);
                $progress->target = 1;
                break;
            case self::ACHIEVEMENT_FIVE_CASES:
                $progress->current = min($stats->unique_cases, 5);
                $progress->target = 5;
                break;
            case self::ACHIEVEMENT_TEN_CASES:
                $progress->current = min($stats->unique_cases, 10);
                $progress->target = 10;
                break;
            case self::ACHIEVEMENT_TWENTYFIVE_CASES:
                $progress->current = min($stats->unique_cases, 25);
                $progress->target = 25;
                break;
            case self::ACHIEVEMENT_PERFECT_SCORE:
                $progress->current = min($stats->perfect_scores, 1);
                $progress->target = 1;
                break;
            case self::ACHIEVEMENT_FIVE_PERFECT:
                $progress->current = min($stats->perfect_scores, 5);
                $progress->target = 5;
                break;
            case self::ACHIEVEMENT_STREAK_10:
                $progress->current = 0; // Would need to calculate.
                $progress->target = 10;
                break;
            case self::ACHIEVEMENT_WEEK_STREAK:
                $progress->current = 0; // Would need to calculate.
                $progress->target = 7;
                break;
            case self::ACHIEVEMENT_HIGH_ACHIEVER:
                $progress->current = $stats->total_attempts >= 10 ? round($stats->average_score) : 0;
                $progress->target = 90;
                break;
            default:
                $progress->current = 0;
                $progress->target = 1;
        }

        $progress->percentage = $progress->target > 0 ?
            min(100, round(($progress->current / $progress->target) * 100)) : 0;

        return $progress;
    }

    /**
     * Get icon for achievement type.
     *
     * @param string $type Achievement type.
     * @return string Icon name or emoji.
     */
    protected static function get_achievement_icon(string $type): string {
        $icons = [
            self::ACHIEVEMENT_FIRST_ATTEMPT => 'fa-flag',
            self::ACHIEVEMENT_FIVE_CASES => 'fa-star',
            self::ACHIEVEMENT_TEN_CASES => 'fa-star',
            self::ACHIEVEMENT_TWENTYFIVE_CASES => 'fa-trophy',
            self::ACHIEVEMENT_PERFECT_SCORE => 'fa-check-circle',
            self::ACHIEVEMENT_FIVE_PERFECT => 'fa-medal',
            self::ACHIEVEMENT_STREAK_10 => 'fa-fire',
            self::ACHIEVEMENT_WEEK_STREAK => 'fa-calendar-check',
            self::ACHIEVEMENT_CATEGORY_COMPLETE => 'fa-folder-open',
            self::ACHIEVEMENT_HIGH_ACHIEVER => 'fa-award',
        ];

        return $icons[$type] ?? 'fa-certificate';
    }
}
