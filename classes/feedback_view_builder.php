<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_casospracticos;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the canonical student-facing solution feedback.
 *
 * Presentation code must not decide independently whether to use structured
 * reasoning or the legacy general feedback. Keeping that choice here makes
 * practice, timed review, attempt review and quiz copies consistent.
 */
class feedback_view_builder {
    /**
     * Build template-ready feedback data.
     *
     * @param object $question Practical-case question.
     * @return array
     */
    public static function build(object $question): array {
        $blocked = ($question->feedbackstatus ?? 'legacy') === 'blocked';
        if ($blocked) {
            return [
                'hascontent' => true,
                'isblocked' => true,
                'hasreasoning' => false,
                'reasoning' => '',
                'hasmodelanswer' => false,
                'modelanswer' => '',
                'useslegacy' => false,
            ];
        }

        $reasoning = trim((string) ($question->reasoning ?? ''));
        $reasoningformat = (int) ($question->reasoningformat ?? FORMAT_HTML);
        $legacy = false;

        if ($reasoning === '') {
            $reasoning = trim((string) ($question->generalfeedback ?? ''));
            $reasoningformat = (int) ($question->generalfeedbackformat ?? FORMAT_HTML);
            $legacy = $reasoning !== '';
        }

        $modelanswer = trim((string) ($question->modelanswer ?? ''));
        $modelanswerformat = (int) ($question->modelanswerformat ?? FORMAT_HTML);

        return [
            'hascontent' => $reasoning !== '' || $modelanswer !== '',
            'isblocked' => false,
            'hasreasoning' => $reasoning !== '',
            'reasoning' => $reasoning !== '' ? format_text($reasoning, $reasoningformat) : '',
            'hasmodelanswer' => $modelanswer !== '',
            'modelanswer' => $modelanswer !== '' ? format_text($modelanswer, $modelanswerformat) : '',
            'useslegacy' => $legacy,
        ];
    }

    /**
     * Compose structured feedback for a copied Moodle quiz question.
     *
     * @param object $question Practical-case question.
     * @return string HTML
     */
    public static function compose_for_quiz(object $question): string {
        $data = self::build($question);
        if (!$data['hascontent']) {
            return '';
        }

        $html = '';
        if ($data['hasreasoning']) {
            $html .= \html_writer::tag('h4', get_string('reasoning', 'local_casospracticos'));
            $html .= $data['reasoning'];
        }
        if ($data['hasmodelanswer']) {
            $html .= \html_writer::tag('h4', get_string('modelanswer', 'local_casospracticos'));
            $html .= $data['modelanswer'];
        }
        return $html;
    }
}
