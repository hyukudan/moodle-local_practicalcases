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
 * Workflow manager for case approval process.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workflow_manager {

    /** @var string Status: Draft - initial state. */
    const STATUS_DRAFT = 'draft';

    /** @var string Status: Pending review - submitted for approval. */
    const STATUS_PENDING_REVIEW = 'pending_review';

    /** @var string Status: In review - being reviewed. */
    const STATUS_IN_REVIEW = 'in_review';

    /** @var string Status: Approved - ready for publishing. */
    const STATUS_APPROVED = 'approved';

    /** @var string Status: Published - live. */
    const STATUS_PUBLISHED = 'published';

    /** @var string Status: Archived - no longer active. */
    const STATUS_ARCHIVED = 'archived';

    /** @var string Review status: Pending. */
    const REVIEW_PENDING = 'pending';

    /** @var string Review status: Approved. */
    const REVIEW_APPROVED = 'approved';

    /** @var string Review status: Rejected. */
    const REVIEW_REJECTED = 'rejected';

    /** @var string Review status: Revision requested. */
    const REVIEW_REVISION = 'revision_requested';

    /** @var array Valid status transitions. */
    const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_PENDING_REVIEW, self::STATUS_PUBLISHED],
        self::STATUS_PENDING_REVIEW => [self::STATUS_DRAFT, self::STATUS_IN_REVIEW],
        self::STATUS_IN_REVIEW => [self::STATUS_APPROVED, self::STATUS_DRAFT],
        self::STATUS_APPROVED => [self::STATUS_PUBLISHED, self::STATUS_DRAFT],
        self::STATUS_PUBLISHED => [self::STATUS_ARCHIVED, self::STATUS_DRAFT],
        self::STATUS_ARCHIVED => [self::STATUS_DRAFT, self::STATUS_PUBLISHED],
    ];

    /**
     * Get all valid statuses.
     *
     * @return array Statuses with labels.
     */
    public static function get_all_statuses(): array {
        return [
            self::STATUS_DRAFT => get_string('status_draft', 'local_casospracticos'),
            self::STATUS_PENDING_REVIEW => get_string('status_pending_review', 'local_casospracticos'),
            self::STATUS_IN_REVIEW => get_string('status_in_review', 'local_casospracticos'),
            self::STATUS_APPROVED => get_string('status_approved', 'local_casospracticos'),
            self::STATUS_PUBLISHED => get_string('status_published', 'local_casospracticos'),
            self::STATUS_ARCHIVED => get_string('status_archived', 'local_casospracticos'),
        ];
    }

    /**
     * Check if a transition is valid.
     *
     * @param string $from Current status.
     * @param string $to Target status.
     * @return bool True if transition is valid.
     */
    public static function can_transition(string $from, string $to): bool {
        if (!isset(self::TRANSITIONS[$from])) {
            return false;
        }
        return in_array($to, self::TRANSITIONS[$from]);
    }

    /**
     * Get valid next statuses for current status.
     *
     * @param string $current Current status.
     * @return array Valid next statuses.
     */
    public static function get_next_statuses(string $current): array {
        return self::TRANSITIONS[$current] ?? [];
    }

    /**
     * Submit a case for review.
     *
     * @param int $caseid Case ID.
     * @return bool Success.
     */
    public static function submit_for_review(int $caseid): bool {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], '*', MUST_EXIST);

        if (!self::can_transition($case->status, self::STATUS_PENDING_REVIEW)) {
            throw new \moodle_exception('invalidtransition', 'local_casospracticos');
        }

        // Validate case has at least one question.
        $questioncount = $DB->count_records('local_cp_questions', ['caseid' => $caseid]);
        if ($questioncount === 0) {
            throw new \moodle_exception('noquestionsforsubmit', 'local_casospracticos');
        }

        $DB->set_field('local_cp_cases', 'status', self::STATUS_PENDING_REVIEW, ['id' => $caseid]);
        $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

        // Log action.
        audit_logger::log_case($caseid, audit_logger::ACTION_SUBMIT_REVIEW);

        return true;
    }

    /**
     * Assign a reviewer to a case.
     *
     * @param int $caseid Case ID.
     * @param int $reviewerid Reviewer user ID.
     * @return int Review record ID.
     */
    public static function assign_reviewer(int $caseid, int $reviewerid): int {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], '*', MUST_EXIST);

        if ($case->status !== self::STATUS_PENDING_REVIEW && $case->status !== self::STATUS_IN_REVIEW) {
            throw new \moodle_exception('invalidstatusforreviewer', 'local_casospracticos');
        }

        // Check if reviewer is valid user with review capability.
        $user = $DB->get_record('user', ['id' => $reviewerid, 'deleted' => 0], '*', MUST_EXIST);

        // Create or update review record.
        $existing = $DB->get_record('local_cp_reviews', [
            'caseid' => $caseid,
            'reviewerid' => $reviewerid,
            'status' => self::REVIEW_PENDING,
        ]);

        if ($existing) {
            return $existing->id;
        }

        $review = new \stdClass();
        $review->caseid = $caseid;
        $review->reviewerid = $reviewerid;
        $review->status = self::REVIEW_PENDING;
        $review->timecreated = time();
        $review->timemodified = time();

        $reviewid = $DB->insert_record('local_cp_reviews', $review);

        // Update case status to in_review.
        $DB->set_field('local_cp_cases', 'status', self::STATUS_IN_REVIEW, ['id' => $caseid]);
        $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

        return $reviewid;
    }

    /**
     * Submit a review decision.
     *
     * @param int $reviewid Review record ID.
     * @param string $decision Decision (approved, rejected, revision_requested).
     * @param string $comments Review comments.
     * @return bool Success.
     */
    public static function submit_review(int $reviewid, string $decision, string $comments = ''): bool {
        global $DB, $USER;

        $review = $DB->get_record('local_cp_reviews', ['id' => $reviewid], '*', MUST_EXIST);

        // Verify reviewer is current user.
        if ($review->reviewerid != $USER->id) {
            throw new \moodle_exception('notassignedreviewer', 'local_casospracticos');
        }

        if (!in_array($decision, [self::REVIEW_APPROVED, self::REVIEW_REJECTED, self::REVIEW_REVISION])) {
            throw new \moodle_exception('invaliddecision', 'local_casospracticos');
        }

        // Update review record.
        $review->status = $decision;
        $review->comments = $comments;
        $review->timemodified = time();
        $DB->update_record('local_cp_reviews', $review);

        // Update case status based on decision.
        $case = $DB->get_record('local_cp_cases', ['id' => $review->caseid]);

        switch ($decision) {
            case self::REVIEW_APPROVED:
                $newstatus = self::STATUS_APPROVED;
                $action = audit_logger::ACTION_APPROVE;
                break;
            case self::REVIEW_REJECTED:
                $newstatus = self::STATUS_DRAFT;
                $action = audit_logger::ACTION_REJECT;
                break;
            case self::REVIEW_REVISION:
                $newstatus = self::STATUS_DRAFT;
                $action = audit_logger::ACTION_REJECT;
                break;
            default:
                return false;
        }

        $DB->set_field('local_cp_cases', 'status', $newstatus, ['id' => $review->caseid]);
        $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $review->caseid]);

        // Log action.
        audit_logger::log_case($review->caseid, $action, [
            'decision' => $decision,
            'comments' => $comments,
            'reviewer' => $USER->id,
        ]);

        return true;
    }

    /**
     * Publish an approved case.
     *
     * @param int $caseid Case ID.
     * @return bool Success.
     */
    public static function publish(int $caseid): bool {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], '*', MUST_EXIST);

        // Can publish from approved OR directly from draft (if workflow disabled).
        if (!self::can_transition($case->status, self::STATUS_PUBLISHED)) {
            throw new \moodle_exception('invalidtransition', 'local_casospracticos');
        }

        $DB->set_field('local_cp_cases', 'status', self::STATUS_PUBLISHED, ['id' => $caseid]);
        $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

        // Trigger event.
        $case->status = self::STATUS_PUBLISHED;
        $event = \local_casospracticos\event\case_published::create_from_case($case);
        $event->trigger();

        // Log action.
        audit_logger::log_case($caseid, audit_logger::ACTION_PUBLISH);

        return true;
    }

    /**
     * Archive a case.
     *
     * @param int $caseid Case ID.
     * @return bool Success.
     */
    public static function archive(int $caseid): bool {
        global $DB;

        $case = $DB->get_record('local_cp_cases', ['id' => $caseid], '*', MUST_EXIST);

        if (!self::can_transition($case->status, self::STATUS_ARCHIVED)) {
            throw new \moodle_exception('invalidtransition', 'local_casospracticos');
        }

        $DB->set_field('local_cp_cases', 'status', self::STATUS_ARCHIVED, ['id' => $caseid]);
        $DB->set_field('local_cp_cases', 'timemodified', time(), ['id' => $caseid]);

        // Log action.
        audit_logger::log_case($caseid, audit_logger::ACTION_ARCHIVE);

        return true;
    }

    /**
     * Get review history for a case.
     *
     * @param int $caseid Case ID.
     * @return array Review records.
     */
    public static function get_review_history(int $caseid): array {
        global $DB;

        $reviews = $DB->get_records_sql("
            SELECT r.*, u.firstname, u.lastname, u.email
              FROM {local_cp_reviews} r
              JOIN {user} u ON r.reviewerid = u.id
             WHERE r.caseid = ?
          ORDER BY r.timecreated DESC
        ", [$caseid]);

        foreach ($reviews as $review) {
            $review->reviewername = fullname($review);
            $review->statuslabel = self::get_review_status_label($review->status);
        }

        return array_values($reviews);
    }

    /**
     * Get pending reviews for a reviewer.
     *
     * @param int $reviewerid Reviewer user ID.
     * @return array Pending reviews.
     */
    public static function get_pending_reviews(int $reviewerid): array {
        global $DB;

        $reviews = $DB->get_records_sql("
            SELECT r.*, c.name AS casename, c.categoryid, cat.name AS categoryname
              FROM {local_cp_reviews} r
              JOIN {local_cp_cases} c ON r.caseid = c.id
              JOIN {local_cp_categories} cat ON c.categoryid = cat.id
             WHERE r.reviewerid = ?
               AND r.status = ?
          ORDER BY r.timecreated ASC
        ", [$reviewerid, self::REVIEW_PENDING]);

        return array_values($reviews);
    }

    /**
     * Get review status label.
     *
     * @param string $status Status code.
     * @return string Localized label.
     */
    public static function get_review_status_label(string $status): string {
        $labels = [
            self::REVIEW_PENDING => get_string('review_pending', 'local_casospracticos'),
            self::REVIEW_APPROVED => get_string('review_approved', 'local_casospracticos'),
            self::REVIEW_REJECTED => get_string('review_rejected', 'local_casospracticos'),
            self::REVIEW_REVISION => get_string('review_revision', 'local_casospracticos'),
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Get cases awaiting review.
     *
     * @return array Cases pending review.
     */
    public static function get_cases_awaiting_review(): array {
        global $DB;

        return $DB->get_records_sql("
            SELECT c.*, cat.name AS categoryname,
                   (SELECT COUNT(*) FROM {local_cp_questions} q WHERE q.caseid = c.id) AS questioncount
              FROM {local_cp_cases} c
              JOIN {local_cp_categories} cat ON c.categoryid = cat.id
             WHERE c.status IN (?, ?)
          ORDER BY c.timemodified ASC
        ", [self::STATUS_PENDING_REVIEW, self::STATUS_IN_REVIEW]);
    }

    /**
     * Check if workflow is enabled.
     *
     * @return bool True if approval workflow is enabled.
     */
    public static function is_workflow_enabled(): bool {
        return (bool)get_config('local_casospracticos', 'enableworkflow');
    }
}
