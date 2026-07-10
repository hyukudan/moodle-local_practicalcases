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
 * Restore implementation for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/backup/moodle2/restore_local_plugin.class.php');

/**
 * Restore plugin class for local_casospracticos.
 */
class restore_local_casospracticos_plugin extends restore_local_plugin {

    /** @var array Mapping of old category IDs to new ones. */
    protected $categorymapping = [];

    /** @var array Mapping of old case IDs to new ones. */
    protected $casemapping = [];

    /** @var array Mapping of old question IDs to new ones. */
    protected $questionmapping = [];

    /** @var array Mapping of old attempt IDs to new ones. */
    protected $attemptmapping = [];

    /**
     * Define the plugin structure for restore.
     *
     * Element names use cp_ prefix to match the backup structure.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        $elepath = $this->get_pathfor('/cp_categories/cp_category');
        $paths[] = new restore_path_element('casospracticos_category', $elepath);

        $elepath = $this->get_pathfor('/cp_cases/cp_case');
        $paths[] = new restore_path_element('casospracticos_case', $elepath);

        $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_questions/cp_question');
        $paths[] = new restore_path_element('casospracticos_question', $elepath);

        $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_questions/cp_question/cp_answers/cp_answer');
        $paths[] = new restore_path_element('casospracticos_answer', $elepath);

        // Reviews and usage are always backed up (workflow/analytics state).
        $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_reviews/cp_review');
        $paths[] = new restore_path_element('casospracticos_review', $elepath);

        $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_usages/cp_usage');
        $paths[] = new restore_path_element('casospracticos_usage', $elepath);

        // Optional document deliverable definition (structural config).
        $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_deliverables/cp_deliverable');
        $paths[] = new restore_path_element('casospracticos_deliverable', $elepath);

        // Add user-attempt paths if user data is being restored.
        $userinfo = $this->get_setting_value('users');
        if ($userinfo) {
            $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_practice_attempts/cp_attempt');
            $paths[] = new restore_path_element('casospracticos_attempt', $elepath);

            $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_practice_attempts/cp_attempt/cp_responses/cp_response');
            $paths[] = new restore_path_element('casospracticos_response', $elepath);

            $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_timed_attempts/cp_timed_attempt');
            $paths[] = new restore_path_element('casospracticos_timed_attempt', $elepath);

            $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_practice_sessions/cp_practice_session');
            $paths[] = new restore_path_element('casospracticos_session', $elepath);

            $elepath = $this->get_pathfor('/cp_cases/cp_case/cp_achievements/cp_achievement');
            $paths[] = new restore_path_element('casospracticos_achievement', $elepath);
        }

        return $paths;
    }

    /**
     * Process a category element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_category($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Update context to new course context.
        $data->contextid = $this->task->get_contextid();

        // Map parent category if it exists.
        if (!empty($data->parent) && isset($this->categorymapping[$data->parent])) {
            $data->parent = $this->categorymapping[$data->parent];
        } else {
            $data->parent = 0;
        }

        // Check if category with same name exists in this context.
        $existing = $DB->get_record('local_cp_categories', [
            'contextid' => $data->contextid,
            'name' => $data->name,
            'parent' => $data->parent
        ]);

        if ($existing) {
            $newid = $existing->id;
        } else {
            $data->timecreated = time();
            $data->timemodified = time();
            $newid = $DB->insert_record('local_cp_categories', $data);
        }

        $this->categorymapping[$oldid] = $newid;
    }

    /**
     * Process a case element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_case($data) {
        global $DB, $USER;

        $data = (object)$data;
        $oldid = $data->id;

        // Map category ID.
        if (isset($this->categorymapping[$data->categoryid])) {
            $data->categoryid = $this->categorymapping[$data->categoryid];
        } else {
            // Create a default category if mapping doesn't exist.
            $defaultcat = $this->get_or_create_default_category();
            $data->categoryid = $defaultcat->id;
        }

        // Map user ID for createdby. When the backup excluded user data the
        // createdby field is absent, so fall back to the restoring user.
        if (!empty($data->createdby)) {
            $data->createdby = $this->get_mappingid('user', $data->createdby);
        }
        if (empty($data->createdby)) {
            $data->createdby = $USER->id;
        }

        $data->timecreated = time();
        $data->timemodified = time();

        $newid = $DB->insert_record('local_cp_cases', $data);
        $this->casemapping[$oldid] = $newid;

        // Set mapping under the same name used by restore_path_element /
        // get_new_parentid() (and for file restore). Names MUST match.
        $this->set_mapping('casospracticos_case', $oldid, $newid, true);
    }

    /**
     * Process a question element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_question($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Get the parent case ID from the path.
        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid && isset($this->casemapping[$data->caseid])) {
            $caseid = $this->casemapping[$data->caseid];
        }

        if (!$caseid) {
            // Skip question if case doesn't exist.
            return;
        }

        $data->caseid = $caseid;
        $data->timecreated = time();
        $data->timemodified = time();

        $newid = $DB->insert_record('local_cp_questions', $data);
        $this->questionmapping[$oldid] = $newid;

        // Set mapping under the same name used by restore_path_element /
        // get_new_parentid() (and for file restore). Names MUST match.
        $this->set_mapping('casospracticos_question', $oldid, $newid, true);
    }

    /**
     * Process an answer element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_answer($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Get the parent question ID.
        $questionid = $this->get_new_parentid('casospracticos_question');
        if (!$questionid && isset($this->questionmapping[$data->questionid])) {
            $questionid = $this->questionmapping[$data->questionid];
        }

        if (!$questionid) {
            // Skip answer if question doesn't exist.
            return;
        }

        $data->questionid = $questionid;

        $newid = $DB->insert_record('local_cp_answers', $data);

        // Set mapping under the same name used for file restore. Names MUST match.
        $this->set_mapping('casospracticos_answer', $oldid, $newid, true);
    }

    /**
     * Process a practice attempt element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_attempt($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Get the parent case ID.
        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid && isset($this->casemapping[$data->caseid])) {
            $caseid = $this->casemapping[$data->caseid];
        }

        if (!$caseid) {
            return;
        }

        // Map user ID.
        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            // Skip if user doesn't exist in restored context.
            return;
        }

        $data->caseid = $caseid;
        $data->userid = $userid;

        $newid = $DB->insert_record('local_cp_practice_attempts', $data);
        $this->attemptmapping[$oldid] = $newid;
    }

    /**
     * Process a practice response element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_response($data) {
        global $DB;

        $data = (object)$data;

        // Get the parent attempt ID.
        $attemptid = $this->get_new_parentid('casospracticos_attempt');
        if (!$attemptid && isset($this->attemptmapping[$data->attemptid])) {
            $attemptid = $this->attemptmapping[$data->attemptid];
        }

        if (!$attemptid) {
            return;
        }

        // Map question ID.
        $questionid = $this->get_mappingid('casospracticos_question', $data->questionid);
        if (!$questionid && isset($this->questionmapping[$data->questionid])) {
            $questionid = $this->questionmapping[$data->questionid];
        }

        if (!$questionid) {
            return;
        }

        $data->attemptid = $attemptid;
        $data->questionid = $questionid;

        $DB->insert_record('local_cp_practice_responses', $data);
    }

    /**
     * Process a review element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_review($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $data->caseid = $caseid;

        // Map reviewer (present only when user data was included).
        if (!empty($data->reviewerid)) {
            $mapped = $this->get_mappingid('user', $data->reviewerid);
            $data->reviewerid = $mapped ?: 0;
        } else {
            $data->reviewerid = 0;
        }

        unset($data->id);
        $DB->insert_record('local_cp_reviews', $data);
    }

    /**
     * Process a usage element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_usage($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $data->caseid = $caseid;

        // Remap the course id to the course being restored into. The original
        // quiz instance cannot be reliably mapped here, so it is cleared to
        // avoid dangling references.
        $data->courseid = $this->task->get_courseid();
        $data->quizid = null;

        unset($data->id);

        // The (caseid, quizid) index is unique; guard against duplicates.
        if (!$DB->record_exists('local_cp_usage', ['caseid' => $caseid, 'quizid' => null])) {
            $DB->insert_record('local_cp_usage', $data);
        }
    }

    /**
     * Process a deliverable definition element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_deliverable($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $data->caseid = $caseid;
        $data->timecreated = $data->timecreated ?? time();
        $data->timemodified = time();

        unset($data->id);

        // The caseid key is unique; skip if a deliverable already exists for it.
        if (!$DB->record_exists('local_cp_case_deliverable', ['caseid' => $caseid])) {
            $DB->insert_record('local_cp_case_deliverable', $data);
        }
    }

    /**
     * Process a timed attempt element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_timed_attempt($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        $data->caseid = $caseid;
        $data->userid = $userid;

        // Regenerate the token to keep the unique index intact across restores.
        $data->token = bin2hex(random_bytes(32));

        unset($data->id);
        $DB->insert_record('local_cp_timed_attempts', $data);
    }

    /**
     * Process a practice session element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_session($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        $data->caseid = $caseid;
        $data->userid = $userid;

        // Regenerate the token to keep the unique index intact across restores.
        $data->token = bin2hex(random_bytes(32));

        unset($data->id);
        $DB->insert_record('local_cp_practice_sessions', $data);
    }

    /**
     * Process an achievement element.
     *
     * @param array $data The data from backup.
     */
    public function process_casospracticos_achievement($data) {
        global $DB;

        $data = (object)$data;

        $caseid = $this->get_new_parentid('casospracticos_case');
        if (!$caseid) {
            return;
        }

        $userid = $this->get_mappingid('user', $data->userid);
        if (!$userid) {
            return;
        }

        $data->caseid = $caseid;
        $data->userid = $userid;

        unset($data->id);

        // The (userid, achievementtype) index is unique; skip if already present.
        if (!$DB->record_exists('local_cp_achievements',
                ['userid' => $userid, 'achievementtype' => $data->achievementtype])) {
            $DB->insert_record('local_cp_achievements', $data);
        }
    }

    /**
     * After restore, process files.
     */
    public function after_restore_course() {
        // Restore files for statements. Itemnames MUST match the set_mapping names.
        $this->add_related_files('local_casospracticos', 'statement', 'casospracticos_case');
        // Deliverable start file (itemid = caseid).
        $this->add_related_files('local_casospracticos', 'deliverable', 'casospracticos_case');
        $this->add_related_files('local_casospracticos', 'questiontext', 'casospracticos_question');
        $this->add_related_files('local_casospracticos', 'answer', 'casospracticos_answer');
        $this->add_related_files('local_casospracticos', 'feedback', 'casospracticos_answer');
    }

    /**
     * Get or create a default category for orphaned cases.
     *
     * @return object The category record.
     */
    protected function get_or_create_default_category() {
        global $DB;

        $contextid = $this->task->get_contextid();
        $name = get_string('defaultcategory', 'local_casospracticos');

        $category = $DB->get_record('local_cp_categories', [
            'contextid' => $contextid,
            'name' => $name,
            'parent' => 0
        ]);

        if (!$category) {
            $category = new \stdClass();
            $category->name = $name;
            $category->description = '';
            $category->descriptionformat = FORMAT_HTML;
            $category->parent = 0;
            $category->sortorder = 0;
            $category->contextid = $contextid;
            $category->timecreated = time();
            $category->timemodified = time();
            $category->id = $DB->insert_record('local_cp_categories', $category);
        }

        return $category;
    }
}
