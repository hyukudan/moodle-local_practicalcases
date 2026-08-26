<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_casospracticos;

use advanced_testcase;

/**
 * Deterministic tests for the complete practice question contract.
 *
 * @covers \local_casospracticos\practice_engine
 * @covers \local_casospracticos\question_manager
 */
class practice_engine_test extends advanced_testcase {
    /**
     * Essay submissions have no final percentage until a teacher grades them.
     */
    public function test_essay_marks_submission_as_needing_grading(): void {
        $question = (object) [
            'id' => 7,
            'qtype' => question_manager::QTYPE_ESSAY,
            'defaultmark' => 10,
            'answers' => [],
        ];

        $submission = practice_engine::score_submission([$question], ['q7' => 'Mi respuesta razonada']);

        $this->assertSame('needsgrading', $submission['gradingstatus']);
        $this->assertNull($submission['percentage']);
        $this->assertSame(0, $submission['maxscore']);
        $this->assertTrue($submission['results'][7]->requiresgrading);
        $this->assertSame('Mi respuesta razonada', $submission['results'][7]->response);
    }

    /**
     * Fully automatic sets retain the ordinary percentage contract.
     */
    public function test_automatic_submission_keeps_numeric_percentage(): void {
        $question = (object) [
            'id' => 8,
            'qtype' => question_manager::QTYPE_TRUEFALSE,
            'defaultmark' => 2,
            'answers' => [
                (object) ['id' => 1, 'fraction' => 1.0, 'feedback' => '', 'feedbackformat' => FORMAT_HTML],
                (object) ['id' => 2, 'fraction' => 0.0, 'feedback' => '', 'feedbackformat' => FORMAT_HTML],
            ],
        ];

        $submission = practice_engine::score_submission([$question], ['q8' => 1]);

        $this->assertSame('auto', $submission['gradingstatus']);
        $this->assertSame(100.0, (float) $submission['percentage']);
        $this->assertFalse($submission['results'][8]->requiresgrading ?? false);
    }

    /**
     * Matching is rejected before scoring rather than silently stored as 0 points.
     */
    public function test_matching_is_rejected_consistently(): void {
        $question = (object) [
            'id' => 9,
            'qtype' => question_manager::QTYPE_MATCHING,
            'defaultmark' => 1,
        ];

        $this->expectException(\moodle_exception::class);
        practice_engine::score_submission([$question], []);
    }

    /**
     * Every accepted type is available to all practice surfaces.
     */
    public function test_accepted_and_practice_qtypes_are_identical(): void {
        $this->assertSame(question_manager::valid_qtypes(), question_manager::practice_qtypes());
        $this->assertNotContains(question_manager::QTYPE_MATCHING, question_manager::valid_qtypes());
        $this->assertContains(question_manager::QTYPE_ESSAY, question_manager::valid_qtypes());
    }

    /**
     * Editorially blocked items never reach learner rendering or denominators.
     */
    public function test_blocked_questions_are_filtered_from_practice(): void {
        $questions = [
            1 => (object) ['id' => 1, 'feedbackstatus' => 'verified'],
            2 => (object) ['id' => 2, 'feedbackstatus' => 'blocked'],
            3 => (object) ['id' => 3, 'feedbackstatus' => 'legacy'],
        ];

        $filtered = question_manager::filter_practice_questions($questions);

        $this->assertSame([1, 3], array_keys($filtered));
    }

    /**
     * The persistent attempt and response both retain the manual grading state.
     */
    public function test_manual_grading_state_is_persisted(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_casospracticos');
        $case = $generator->create_case();
        $questionid = question_manager::create((object) [
            'caseid' => $case->id,
            'questiontext' => 'Razone la respuesta.',
            'qtype' => question_manager::QTYPE_ESSAY,
        ]);

        $attemptid = stats_manager::record_practice_attempt($case->id, $USER->id, 0, 0, [
            $questionid => [
                'selected' => 'Respuesta pendiente',
                'score' => 0,
                'correct' => false,
                'requiresgrading' => true,
            ],
        ], 'needsgrading');

        $attempt = $DB->get_record('local_cp_practice_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        $response = $DB->get_record('local_cp_practice_responses', ['attemptid' => $attemptid], '*', MUST_EXIST);
        $this->assertSame('needsgrading', $attempt->gradingstatus);
        $this->assertNull($attempt->percentage);
        $this->assertEquals(1, $response->requiresgrading);
    }

    /**
     * Expiry finalization uses autosaved data and never needs a request sesskey.
     */
    public function test_expired_timed_attempt_is_finalized_idempotently(): void {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_casospracticos');
        $case = $generator->create_case();
        $questionid = question_manager::create((object) [
            'caseid' => $case->id,
            'questiontext' => 'Verdadero o falso.',
            'qtype' => question_manager::QTYPE_TRUEFALSE,
            'correctanswer' => true,
        ]);
        $question = question_manager::get_by_case_with_answers($case->id)[$questionid];
        $correctanswer = reset(array_filter($question->answers, static function($answer): bool {
            return (float) $answer->fraction > 0;
        }));

        $attemptid = timed_attempt_manager::start_attempt($case->id, $USER->id, 1);
        $DB->set_field('local_cp_timed_attempts', 'responses', json_encode([
            $questionid => $correctanswer->id,
        ]), ['id' => $attemptid]);
        $DB->set_field('local_cp_timed_attempts', 'timestarted', time() - 120, ['id' => $attemptid]);

        $this->assertTrue(timed_attempt_manager::finalize_expired_attempt($attemptid, $USER->id));
        $this->assertTrue(timed_attempt_manager::finalize_expired_attempt($attemptid, $USER->id));
        $attempt = timed_attempt_manager::get_attempt($attemptid);
        $this->assertSame(timed_attempt_manager::STATUS_FINISHED, $attempt->status);
        $this->assertEquals(100.0, $attempt->percentage);
    }
}
