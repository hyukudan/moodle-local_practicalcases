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
 * Practice session manager for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager for practice sessions with secure token-based storage.
 */
class practice_session_manager {

    /** @var string Table name */
    const TABLE = 'local_cp_practice_sessions';

    /** @var int Session expiry time in seconds (2 hours) */
    const SESSION_EXPIRY = 7200;

    /**
     * Create a new practice session.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     * @param array $questionorder Question IDs in order
     * @return string Session token
     */
    public static function create_session(int $caseid, int $userid, array $questionorder): string {
        global $DB;

        // Clean up any existing session for this user/case.
        self::cleanup_user_session($caseid, $userid);

        $record = new \stdClass();
        $record->caseid = $caseid;
        $record->userid = $userid;
        $record->questionorder = json_encode($questionorder);
        $record->token = bin2hex(random_bytes(32)); // Secure random token.
        $record->timecreated = time();
        $record->timeexpiry = time() + self::SESSION_EXPIRY;

        $DB->insert_record(self::TABLE, $record);

        return $record->token;
    }

    /**
     * Get session by token.
     *
     * @param string $token Session token
     * @return object|false Session record or false
     */
    public static function get_session(string $token) {
        global $DB;

        $session = $DB->get_record(self::TABLE, ['token' => $token]);

        if (!$session) {
            return false;
        }

        // Check if expired.
        if ($session->timeexpiry < time()) {
            self::delete_session($token);
            return false;
        }

        return $session;
    }

    /**
     * Get question order from session.
     *
     * @param string $token Session token
     * @return array|false Question IDs in order or false
     */
    public static function get_question_order(string $token) {
        $session = self::get_session($token);

        if (!$session) {
            return false;
        }

        return json_decode($session->questionorder, true);
    }

    /**
     * Verify session belongs to user.
     *
     * @param string $token Session token
     * @param int $userid User ID
     * @return bool True if session belongs to user
     */
    public static function verify_session_ownership(string $token, int $userid): bool {
        $session = self::get_session($token);

        if (!$session) {
            return false;
        }

        return $session->userid == $userid;
    }

    /**
     * Delete a session.
     *
     * @param string $token Session token
     */
    public static function delete_session(string $token): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['token' => $token]);
    }

    /**
     * Clean up user's existing session for a case.
     *
     * @param int $caseid Case ID
     * @param int $userid User ID
     */
    private static function cleanup_user_session(int $caseid, int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['caseid' => $caseid, 'userid' => $userid]);
    }

    /**
     * Clean up expired sessions (called by scheduled task).
     *
     * @return int Number of sessions deleted
     */
    public static function cleanup_expired_sessions(): int {
        global $DB;

        $count = $DB->count_records_select(self::TABLE, 'timeexpiry < :now', ['now' => time()]);
        $DB->delete_records_select(self::TABLE, 'timeexpiry < :now', ['now' => time()]);

        return $count;
    }
}
