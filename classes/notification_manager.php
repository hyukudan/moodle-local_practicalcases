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
 * Notification manager for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_manager {

    /**
     * Send notification when a case is published.
     *
     * @param object $case The case that was published.
     * @param array $userids Users to notify (empty = all with view capability).
     */
    public static function notify_case_published(object $case, array $userids = []): void {
        global $DB;

        // Check if notifications are enabled.
        if (!get_config('local_casospracticos', 'notifyonpublish')) {
            return;
        }

        // Get users to notify.
        if (empty($userids)) {
            $context = \context_system::instance();
            $userids = self::get_users_with_capability('local/casospracticos:view', $context);
        }

        if (empty($userids)) {
            return;
        }

        // Get case author. May be false if the author was deleted/anonymized.
        $author = $DB->get_record('user', ['id' => $case->createdby]);
        // Concrete sender/display user, used for both userfrom and fullname().
        $sender = $author ?: \core_user::get_noreply_user();

        // Build notification.
        $caseurl = new \moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);

        // Batch-load all recipients once to avoid an N+1 query pattern in the loop.
        $recipients = $DB->get_records_list('user', 'id', $userids);

        foreach ($userids as $userid) {
            // Don't notify the author.
            if ($userid == $case->createdby) {
                continue;
            }

            $user = $recipients[$userid] ?? null;
            if (!$user) {
                continue;
            }

            $message = new \core\message\message();
            $message->component = 'local_casospracticos';
            $message->name = 'casepublished';
            $message->userfrom = $sender;
            $message->userto = $user;
            $message->subject = get_string('notification:casepublished_subject', 'local_casospracticos', $case->name);
            $message->fullmessage = get_string('notification:casepublished_body', 'local_casospracticos', [
                'casename' => $case->name,
                'author' => fullname($sender),
                'url' => $caseurl->out(false),
            ]);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = get_string('notification:casepublished_body_html', 'local_casospracticos', [
                'casename' => format_string($case->name),
                'author' => fullname($sender),
                'url' => $caseurl->out(false),
            ]);
            $message->smallmessage = get_string('notification:casepublished_small', 'local_casospracticos', $case->name);
            $message->notification = 1;
            $message->contexturl = $caseurl;
            $message->contexturlname = $case->name;

            message_send($message);
        }
    }

    /**
     * Send notification when a case is assigned for review.
     *
     * @param object $review The review assignment.
     * @param object $case The case to review.
     */
    public static function notify_review_assigned(object $review, object $case): void {
        global $DB;

        $reviewer = $DB->get_record('user', ['id' => $review->reviewerid]);
        if (!$reviewer) {
            return;
        }

        // Author may be false if deleted/anonymized; resolve a concrete sender.
        $author = $DB->get_record('user', ['id' => $case->createdby]);
        $sender = $author ?: \core_user::get_noreply_user();
        $caseurl = new \moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);

        $message = new \core\message\message();
        $message->component = 'local_casospracticos';
        $message->name = 'reviewassigned';
        $message->userfrom = $sender;
        $message->userto = $reviewer;
        $message->subject = get_string('notification:reviewassigned_subject', 'local_casospracticos', $case->name);
        $message->fullmessage = get_string('notification:reviewassigned_body', 'local_casospracticos', [
            'casename' => $case->name,
            'author' => fullname($sender),
            'url' => $caseurl->out(false),
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('notification:reviewassigned_body_html', 'local_casospracticos', [
            'casename' => format_string($case->name),
            'author' => fullname($sender),
            'url' => $caseurl->out(false),
        ]);
        $message->smallmessage = get_string('notification:reviewassigned_small', 'local_casospracticos', $case->name);
        $message->notification = 1;
        $message->contexturl = $caseurl;
        $message->contexturlname = $case->name;

        message_send($message);
    }

    /**
     * Send notification when a review is completed.
     *
     * @param object $review The completed review.
     * @param object $case The reviewed case.
     */
    public static function notify_review_completed(object $review, object $case): void {
        global $DB;

        $author = $DB->get_record('user', ['id' => $case->createdby]);
        if (!$author) {
            return;
        }

        // Reviewer may be false if deleted/anonymized; resolve a concrete sender.
        $reviewer = $DB->get_record('user', ['id' => $review->reviewerid]);
        $sender = $reviewer ?: \core_user::get_noreply_user();
        $caseurl = new \moodle_url('/local/casospracticos/case_view.php', ['id' => $case->id]);

        $statusstr = get_string('review_status_' . $review->status, 'local_casospracticos');

        $message = new \core\message\message();
        $message->component = 'local_casospracticos';
        $message->name = 'reviewcompleted';
        $message->userfrom = $sender;
        $message->userto = $author;
        $message->subject = get_string('notification:reviewcompleted_subject', 'local_casospracticos', [
            'casename' => $case->name,
            'status' => $statusstr,
        ]);
        $message->fullmessage = get_string('notification:reviewcompleted_body', 'local_casospracticos', [
            'casename' => $case->name,
            'reviewer' => fullname($sender),
            'status' => $statusstr,
            'comments' => $review->comments ?? '',
            'url' => $caseurl->out(false),
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('notification:reviewcompleted_body_html', 'local_casospracticos', [
            'casename' => format_string($case->name),
            'reviewer' => fullname($sender),
            'status' => $statusstr,
            'comments' => format_text($review->comments ?? '', FORMAT_PLAIN),
            'url' => $caseurl->out(false),
        ]);
        $message->smallmessage = get_string('notification:reviewcompleted_small', 'local_casospracticos', [
            'casename' => $case->name,
            'status' => $statusstr,
        ]);
        $message->notification = 1;
        $message->contexturl = $caseurl;
        $message->contexturlname = $case->name;

        message_send($message);
    }

    /**
     * Send notification when user earns an achievement.
     *
     * @param int $userid User who earned the achievement.
     * @param string $achievementtype Type of achievement.
     * @param object|null $case Related case if applicable.
     */
    public static function notify_achievement_earned(int $userid, string $achievementtype, ?object $case = null): void {
        global $DB;

        // Check if gamification is enabled.
        if (!get_config('local_casospracticos', 'enablegamification')) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        $achievementname = get_string('achievement:' . $achievementtype, 'local_casospracticos');
        $achievementdesc = get_string('achievement:' . $achievementtype . '_desc', 'local_casospracticos');
        $achievementsurl = new \moodle_url('/local/casospracticos/achievements.php');

        $message = new \core\message\message();
        $message->component = 'local_casospracticos';
        $message->name = 'achievementearned';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('notification:achievementearned_subject', 'local_casospracticos', $achievementname);
        $message->fullmessage = get_string('notification:achievementearned_body', 'local_casospracticos', [
            'achievement' => $achievementname,
            'description' => $achievementdesc,
            'url' => $achievementsurl->out(false),
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('notification:achievementearned_body_html', 'local_casospracticos', [
            'achievement' => $achievementname,
            'description' => $achievementdesc,
            'url' => $achievementsurl->out(false),
        ]);
        $message->smallmessage = get_string('notification:achievementearned_small', 'local_casospracticos', $achievementname);
        $message->notification = 1;
        $message->contexturl = $achievementsurl;
        $message->contexturlname = get_string('achievements', 'local_casospracticos');

        message_send($message);
    }

    /**
     * Notify graders that a student submitted a manual-mode deliverable.
     *
     * Recipients are the users who hold mod/casospracticos:grade in the module
     * context. The context link points at the teacher grading report filtered to
     * the submitting student.
     *
     * @param \context_module $context The activity module context.
     * @param \stdClass $attempt The mod_casospracticos attempt row (has userid, caseid, id).
     * @param \stdClass $case The local_cp_cases record (for the case name).
     * @return void
     */
    public static function notify_deliverable_submitted(\context_module $context, \stdClass $attempt,
            \stdClass $case): void {
        global $DB;

        $graders = get_users_by_capability($context, 'mod/casospracticos:grade', 'u.*');
        if (empty($graders)) {
            return;
        }

        $student = $DB->get_record('user', ['id' => $attempt->userid]);
        $sender = $student ?: \core_user::get_noreply_user();
        $studentname = $student ? fullname($student) : get_string('unknownuser', 'moodle');

        // cmid = the module context instance id -> report.php ?id=cmid.
        $reporturl = new \moodle_url('/mod/casospracticos/report.php', [
            'id' => $context->instanceid,
            'studentid' => $attempt->userid,
        ]);

        $a = [
            'casename' => $case->name,
            'student' => $studentname,
            'url' => $reporturl->out(false),
        ];

        foreach ($graders as $grader) {
            $message = new \core\message\message();
            $message->component = 'local_casospracticos';
            $message->name = 'deliverablesubmitted';
            $message->userfrom = $sender;
            $message->userto = $grader;
            $message->subject = get_string('notification:deliverablesubmitted_subject', 'local_casospracticos',
                $case->name);
            $message->fullmessage = get_string('notification:deliverablesubmitted_body', 'local_casospracticos', $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = get_string('notification:deliverablesubmitted_body_html',
                'local_casospracticos', [
                    'casename' => format_string($case->name),
                    'student' => s($studentname),
                    'url' => $reporturl->out(false),
                ]);
            $message->smallmessage = get_string('notification:deliverablesubmitted_small', 'local_casospracticos',
                $case->name);
            $message->notification = 1;
            $message->contexturl = $reporturl;
            $message->contexturlname = $case->name;

            message_send($message);
        }
    }

    /**
     * Notify a student that their deliverable has been graded manually.
     *
     * @param \context_module $context The activity module context.
     * @param \stdClass $attempt The graded attempt row (userid, id, deliverablereviewer,
     *                           deliverablemanualscore, deliverablemaxscore).
     * @param \stdClass $case The local_cp_cases record (for the case name).
     * @return void
     */
    public static function notify_deliverable_graded(\context_module $context, \stdClass $attempt,
            \stdClass $case): void {
        global $DB;

        $student = $DB->get_record('user', ['id' => $attempt->userid]);
        if (!$student) {
            return;
        }

        $reviewer = !empty($attempt->deliverablereviewer)
            ? $DB->get_record('user', ['id' => $attempt->deliverablereviewer]) : false;
        $sender = $reviewer ?: \core_user::get_noreply_user();

        $reviewurl = new \moodle_url('/mod/casospracticos/review.php', [
            'id' => $context->instanceid,
            'attemptid' => $attempt->id,
        ]);

        $score = isset($attempt->deliverablemanualscore) ? (float) $attempt->deliverablemanualscore : 0;
        $max = isset($attempt->deliverablemaxscore) ? (float) $attempt->deliverablemaxscore : 0;
        $scorestr = format_float($score, 2) . ' / ' . format_float($max, 2);

        $a = [
            'casename' => $case->name,
            'score' => $scorestr,
            'url' => $reviewurl->out(false),
        ];

        $message = new \core\message\message();
        $message->component = 'local_casospracticos';
        $message->name = 'deliverablegraded';
        $message->userfrom = $sender;
        $message->userto = $student;
        $message->subject = get_string('notification:deliverablegraded_subject', 'local_casospracticos', $case->name);
        $message->fullmessage = get_string('notification:deliverablegraded_body', 'local_casospracticos', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('notification:deliverablegraded_body_html', 'local_casospracticos', [
            'casename' => format_string($case->name),
            'score' => $scorestr,
            'url' => $reviewurl->out(false),
        ]);
        $message->smallmessage = get_string('notification:deliverablegraded_small', 'local_casospracticos',
            $case->name);
        $message->notification = 1;
        $message->contexturl = $reviewurl;
        $message->contexturlname = $case->name;

        message_send($message);
    }

    /**
     * Get users with a specific capability.
     *
     * @param string $capability The capability to check.
     * @param \context $context The context.
     * @return array Array of user IDs.
     */
    private static function get_users_with_capability(string $capability, \context $context): array {
        $users = get_users_by_capability($context, $capability, 'u.id');
        return array_keys($users);
    }
}
