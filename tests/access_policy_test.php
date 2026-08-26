<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_casospracticos;

use advanced_testcase;
use context_system;

/**
 * The access policy of the case bank: who may open it, who may see the solution.
 *
 * These rules were duplicated across pages and drifted apart, which is how a
 * paying student ended up unable to see any solution at all. They now live in
 * lib.php and this is where their contract is pinned down.
 *
 * @covers ::local_casospracticos_get_view_access
 * @covers ::local_casospracticos_can_view_answers
 * @covers ::local_casospracticos_can_see_blocked_questions
 * @covers ::local_casospracticos_get_root_url
 */
class access_policy_test extends advanced_testcase {

    /** @var \stdClass The course whose enrolment grants the entitlement. */
    protected $productcourse;

    protected function setUp(): void {
        parent::setUp();
        global $CFG;
        require_once($CFG->dirroot . '/local/casospracticos/lib.php');
        $this->resetAfterTest(true);
        $this->productcourse = $this->getDataGenerator()->create_course();
        set_config('productcourseid', $this->productcourse->id, 'local_casospracticos');
    }

    /**
     * Enrol a fresh user and return them.
     *
     * @param string $enrol 'manual' (paid) or 'self' (trial when $trial is true)
     * @param bool $trial Flag the self enrolment as the trial sample.
     * @return \stdClass
     */
    protected function enrolled_user(string $enrol = 'manual', bool $trial = false) {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->productcourse->id, 'student', $enrol);
        if ($trial) {
            $instance = $DB->get_record('enrol',
                ['courseid' => $this->productcourse->id, 'enrol' => $enrol], '*', MUST_EXIST);
            $instance->customint6 = 1;
            $DB->update_record('enrol', $instance);
        }
        return $user;
    }

    /**
     * A paying student is entitled to the bank in full.
     */
    public function test_manual_enrolment_grants_full_access(): void {
        $user = $this->enrolled_user('manual');
        $this->assertSame(LOCAL_CP_ACCESS_FULL, local_casospracticos_get_view_access($user->id));
    }

    /**
     * Nobody without an enrolment gets anything.
     */
    public function test_unenrolled_user_gets_nothing(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertSame(LOCAL_CP_ACCESS_NONE, local_casospracticos_get_view_access($user->id));
    }

    /**
     * The trial is a sample: the statement, never the solution.
     */
    public function test_trial_enrolment_is_statement_only(): void {
        set_config('trialaccess', 'statement', 'local_casospracticos');
        $user = $this->enrolled_user('self', true);
        $this->assertSame(LOCAL_CP_ACCESS_STATEMENT, local_casospracticos_get_view_access($user->id));
    }

    /**
     * The regression this whole change exists for: paying buys the solution.
     */
    public function test_paying_student_may_see_answers_and_solution(): void {
        $user = $this->enrolled_user('manual');
        $this->setUser($user);
        $this->assertTrue(local_casospracticos_can_view_answers(context_system::instance()));
    }

    /**
     * ...and not paying does not.
     */
    public function test_trial_and_outsiders_may_not_see_answers(): void {
        set_config('trialaccess', 'statement', 'local_casospracticos');
        $context = context_system::instance();

        $this->setUser($this->enrolled_user('self', true));
        $this->assertFalse(local_casospracticos_can_view_answers($context));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(local_casospracticos_can_view_answers($context));
    }

    /**
     * A broken product course must deny, not hand the product to everyone.
     */
    public function test_missing_product_course_fails_closed(): void {
        $user = $this->enrolled_user('manual');
        set_config('productcourseid', 99999999, 'local_casospracticos');

        $this->assertSame(LOCAL_CP_ACCESS_NONE, local_casospracticos_get_view_access($user->id));
    }

    /**
     * Blocked questions are editorial-only, whatever the learner has paid.
     */
    public function test_blocked_questions_are_editorial_only(): void {
        $context = context_system::instance();

        $this->setUser($this->enrolled_user('manual'));
        $this->assertFalse(local_casospracticos_can_see_blocked_questions($context));

        $this->setAdminUser();
        $this->assertTrue(local_casospracticos_can_see_blocked_questions($context));
    }

    /**
     * A learner's root is the bank; the back-office is for editorial. Pointing
     * learner breadcrumbs at index.php put a permission error one click away
     * from every case.
     */
    public function test_root_url_sends_learners_to_the_bank_and_editors_to_the_backoffice(): void {
        $this->setUser($this->enrolled_user('manual'));
        $this->assertStringContainsString('real_bank.php',
            local_casospracticos_get_root_url()->out(false));

        $this->setAdminUser();
        $this->assertStringContainsString('index.php',
            local_casospracticos_get_root_url()->out(false));
    }

    /**
     * The back-office parameters must never leak into the learner URL.
     */
    public function test_root_url_ignores_backoffice_params_for_learners(): void {
        $this->setUser($this->enrolled_user('manual'));
        $url = local_casospracticos_get_root_url(['category' => 42])->out(false);

        $this->assertStringContainsString('real_bank.php', $url);
        $this->assertStringNotContainsString('category=42', $url);
    }

    /**
     * A card must not advertise questions the case will refuse to serve.
     */
    public function test_learner_question_count_excludes_blocked(): void {
        $generator = $this->getDataGenerator()->get_plugin_generator('local_casospracticos');
        $case = $generator->create_case(['status' => 'published']);
        $generator->create_question(['caseid' => $case->id, 'questiontext' => 'Visible']);
        $generator->create_question([
            'caseid' => $case->id,
            'questiontext' => 'Blocked',
            'feedbackstatus' => 'blocked',
        ]);

        $editorial = case_manager::get_with_counts(null, null, false);
        $learner = case_manager::get_with_counts(null, null, true);

        $this->assertEquals(2, $editorial[$case->id]->questioncount);
        $this->assertEquals(1, $learner[$case->id]->questioncount);
    }
}
