<?php

namespace local_casospracticos;

use advanced_testcase;

/**
 * @covers \local_casospracticos\feedback_view_builder
 */
class feedback_view_builder_test extends advanced_testcase {
    public function test_legacy_feedback_is_used_only_as_fallback(): void {
        $question = (object) [
            'generalfeedback' => '<p>Contenido heredado.</p>',
            'generalfeedbackformat' => FORMAT_HTML,
            'reasoning' => '',
            'reasoningformat' => FORMAT_HTML,
            'modelanswer' => '',
            'modelanswerformat' => FORMAT_HTML,
        ];

        $data = feedback_view_builder::build($question);
        $this->assertTrue($data['hasreasoning']);
        $this->assertTrue($data['useslegacy']);
        $this->assertStringContainsString('Contenido heredado', $data['reasoning']);
    }

    public function test_structured_reasoning_replaces_legacy_and_model_is_separate(): void {
        $question = (object) [
            'generalfeedback' => '<p>No debe mostrarse.</p>',
            'generalfeedbackformat' => FORMAT_HTML,
            'reasoning' => '<p>Razonamiento aplicado.</p>',
            'reasoningformat' => FORMAT_HTML,
            'modelanswer' => '<p>Respuesta modelo.</p>',
            'modelanswerformat' => FORMAT_HTML,
        ];

        $data = feedback_view_builder::build($question);
        $this->assertFalse($data['useslegacy']);
        $this->assertStringNotContainsString('No debe mostrarse', $data['reasoning']);
        $this->assertStringContainsString('Razonamiento aplicado', $data['reasoning']);
        $this->assertStringContainsString('Respuesta modelo', $data['modelanswer']);
    }

    public function test_blocked_feedback_suppresses_every_solution_text(): void {
        $question = (object) [
            'feedbackstatus' => 'blocked',
            'generalfeedback' => '<p>Explicación heredada insegura.</p>',
            'reasoning' => '<p>Razonamiento inseguro.</p>',
            'modelanswer' => '<p>Modelo inseguro.</p>',
        ];

        $data = feedback_view_builder::build($question);
        $this->assertTrue($data['hascontent']);
        $this->assertTrue($data['isblocked']);
        $this->assertFalse($data['hasreasoning']);
        $this->assertFalse($data['hasmodelanswer']);
        $this->assertSame('', $data['reasoning']);
        $this->assertSame('', $data['modelanswer']);
    }
}
