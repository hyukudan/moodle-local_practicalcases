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
 * Behat step definitions for local_casospracticos.
 *
 * @package    local_casospracticos
 * @category   test
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file is required by Behat before boot.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Tester\Exception\PendingException;

/**
 * Steps for the entitlement model of the practical-case bank.
 *
 * What a learner may see is decided by *entitlement* — an active enrolment in
 * the product course named by the plugin's productcourseid setting — and not by
 * a capability. Scenarios therefore cannot just create a user and log in: an
 * unenrolled user has no access at all, which is why every scenario written
 * before this step existed was describing a journey that no learner could make.
 */
class behat_local_casospracticos extends behat_base {

    /** @var string Shortname of the course these steps use as the product course. */
    const PRODUCT_COURSE_SHORTNAME = 'cpproduct';

    /**
     * Resolve the named pages used by "I am on the ... page".
     *
     * Recognised page names:
     *   Bank        - real_bank.php, the learner's entry point to the case bank
     *   Back-office - index.php, the editorial catalogue (refuses learners)
     *
     * @param string $page Name of the page.
     * @return moodle_url
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            case 'bank':
                return new moodle_url('/local/casospracticos/real_bank.php');
            case 'back-office':
                return new moodle_url('/local/casospracticos/index.php');
            default:
                throw new Exception('Unrecognised local_casospracticos page type "' . $page . '."');
        }
    }

    /**
     * Give a user a level of entitlement to the practical-case bank.
     *
     * Creates the product course on first use, points the plugin's
     * productcourseid setting at it, and enrols the user in the way that
     * produces the requested level:
     *   FULL      - manual enrolment (the paying student)
     *   STATEMENT - self enrolment flagged as trial (customint6 = 1)
     *   NONE      - no enrolment at all
     *
     * @Given /^"(?P<username>[^"]*)" has "(?P<level>NONE|STATEMENT|FULL)" access to the practical-case bank$/
     * @param string $username
     * @param string $level
     */
    public function user_has_access_to_the_case_bank(string $username, string $level): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/local/casospracticos/lib.php');
        require_once($CFG->libdir . '/enrollib.php');

        $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $course = $this->ensure_product_course();

        if ($level === 'NONE') {
            return;
        }

        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
        $plugintype = ($level === 'STATEMENT') ? 'self' : 'manual';

        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => $plugintype]);
        if (!$instance) {
            $plugin = enrol_get_plugin($plugintype);
            $instanceid = $plugin->add_instance($course);
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }

        // customint6 = 1 is what marks a self enrolment as the trial sample.
        $instance->customint6 = ($level === 'STATEMENT') ? 1 : 0;
        $DB->update_record('enrol', $instance);

        enrol_get_plugin($plugintype)->enrol_user($instance, $user->id, $studentroleid);
    }

    /**
     * Create (once) the course whose enrolment grants the entitlement.
     *
     * @return stdClass
     */
    protected function ensure_product_course(): stdClass {
        global $DB;

        $course = $DB->get_record('course', ['shortname' => self::PRODUCT_COURSE_SHORTNAME]);
        if (!$course) {
            $course = $this->get_data_generator()->create_course([
                'shortname' => self::PRODUCT_COURSE_SHORTNAME,
                'fullname' => 'Practical Cases (product course)',
            ]);
        }
        set_config('productcourseid', $course->id, 'local_casospracticos');

        return $course;
    }

    /**
     * Set the trial access policy (what a STATEMENT-level learner is shown).
     *
     * @Given /^the practical-case trial policy is "(?P<policy>statement|full|none)"$/
     * @param string $policy
     */
    public function the_trial_policy_is(string $policy): void {
        set_config('trialaccess', $policy, 'local_casospracticos');
    }

    /**
     * Give a question a canonical reasoned solution.
     *
     * @Given /^the practical-case question "(?P<questiontext>[^"]*)" has reasoning "(?P<reasoning>[^"]*)"$/
     * @param string $questiontext
     * @param string $reasoning
     */
    public function the_question_has_reasoning(string $questiontext, string $reasoning): void {
        global $DB;

        $questionid = $DB->get_field('local_cp_questions', 'id',
            ['questiontext' => $questiontext], IGNORE_MULTIPLE);
        if (!$questionid) {
            throw new Behat\Mink\Exception\ExpectationException(
                'No practical-case question found with text "' . $questiontext . '"',
                $this->getSession());
        }
        $DB->set_field('local_cp_questions', 'reasoning', $reasoning, ['id' => $questionid]);
        $DB->set_field('local_cp_questions', 'reasoningformat', FORMAT_HTML, ['id' => $questionid]);
    }
}
