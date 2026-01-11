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
 * Case view page with questions.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;
use local_casospracticos\question_manager;

// Parameters.
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

// Load case.
$case = case_manager::get_with_questions($id);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

$category = category_manager::get($case->categoryid);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/case_view.php', ['id' => $id]));
$PAGE->set_title(format_string($case->name));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
if ($category) {
    $PAGE->navbar->add(format_string($category->name),
        new moodle_url('/local/casospracticos/index.php', ['category' => $category->id]));
}
$PAGE->navbar->add(format_string($case->name));

// Process actions.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'deletequestion':
            require_capability('local/casospracticos:edit', $context);
            if ($confirm) {
                question_manager::delete($questionid);
                \core\notification::success(get_string('questiondeleted', 'local_casospracticos'));
            } else {
                echo $OUTPUT->header();
                echo $OUTPUT->confirm(
                    get_string('deletequestion', 'local_casospracticos') . '?',
                    new moodle_url('/local/casospracticos/case_view.php', [
                        'id' => $id,
                        'action' => 'deletequestion',
                        'questionid' => $questionid,
                        'confirm' => 1,
                        'sesskey' => sesskey(),
                    ]),
                    new moodle_url('/local/casospracticos/case_view.php', ['id' => $id])
                );
                echo $OUTPUT->footer();
                exit;
            }
            break;

        case 'moveup':
            require_capability('local/casospracticos:edit', $context);
            $question = question_manager::get($questionid);
            if ($question && $question->sortorder > 1) {
                question_manager::reorder($questionid, $question->sortorder - 1);
            }
            break;

        case 'movedown':
            require_capability('local/casospracticos:edit', $context);
            $question = question_manager::get($questionid);
            $maxorder = count($case->questions);
            if ($question && $question->sortorder < $maxorder) {
                question_manager::reorder($questionid, $question->sortorder + 1);
            }
            break;
    }

    redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $id]));
}

echo $OUTPUT->header();

// Case header.
echo html_writer::start_div('case-header mb-4');
echo $OUTPUT->heading(format_string($case->name));

// Status badge.
$statuses = local_casospracticos_get_status_options();
$statusclass = [
    'draft' => 'badge bg-secondary',
    'published' => 'badge bg-success',
    'archived' => 'badge bg-warning',
];
echo html_writer::tag('span', $statuses[$case->status], [
    'class' => $statusclass[$case->status] ?? 'badge bg-secondary',
]);

// Action buttons.
$buttons = [];
if (has_capability('local/casospracticos:edit', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/case_edit.php', ['id' => $id]),
        get_string('editcase', 'local_casospracticos'),
        'get'
    );
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/question_edit.php', ['caseid' => $id]),
        get_string('newquestion', 'local_casospracticos'),
        'get'
    );
}
if (!empty($case->questions)) {
    // Practice button - available to all viewers.
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/practice.php', ['id' => $id]),
        get_string('practice', 'local_casospracticos'),
        'get',
        ['class' => 'btn-info']
    );

    // My attempts button - available to all viewers.
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $id]),
        get_string('viewmyattempts', 'local_casospracticos'),
        'get',
        ['class' => 'btn-outline-info']
    );

    if (has_capability('local/casospracticos:insertquiz', $context)) {
        $buttons[] = $OUTPUT->single_button(
            new moodle_url('/local/casospracticos/insert_quiz.php', ['caseid' => $id]),
            get_string('insertintoquiz', 'local_casospracticos'),
            'get',
            ['class' => 'btn-success']
        );
    }
}
if (has_capability('local/casospracticos:export', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/export.php', ['caseids[]' => $id]),
        get_string('export', 'local_casospracticos'),
        'get'
    );
}
// Stats button (for managers/teachers).
if (has_capability('local/casospracticos:viewaudit', $context)) {
    $buttons[] = $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/case_stats.php', ['id' => $id]),
        get_string('statistics', 'local_casospracticos'),
        'get',
        ['class' => 'btn-info']
    );
}
// Print button.
$buttons[] = html_writer::tag('button', get_string('print', 'local_casospracticos'), [
    'class' => 'btn btn-secondary',
    'onclick' => 'window.print(); return false;',
]);
if (!empty($buttons)) {
    echo html_writer::div(implode(' ', $buttons), 'mt-3');
}
echo html_writer::end_div();

// Statement.
echo html_writer::start_div('case-statement card mb-4');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', get_string('casestatement', 'local_casospracticos'), ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::div(format_text($case->statement, $case->statementformat), 'card-body');
echo html_writer::end_div();

// Questions.
echo html_writer::tag('h4', get_string('questions', 'local_casospracticos') .
    ' (' . count($case->questions) . ')');

if (empty($case->questions)) {
    echo html_writer::tag('p', get_string('noquestions', 'local_casospracticos'), ['class' => 'text-muted']);
} else {
    $qtypes = local_casospracticos_get_supported_qtypes();

    foreach ($case->questions as $index => $question) {
        echo html_writer::start_div('question-item card mb-3');
        echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');

        // Question number and type.
        $qnumber = $index + 1;
        echo html_writer::tag('span', "#{$qnumber} - " . ($qtypes[$question->qtype] ?? $question->qtype), [
            'class' => 'badge bg-primary me-2',
        ]);
        echo html_writer::tag('span', get_string('defaultmark', 'local_casospracticos') . ': ' . $question->defaultmark, [
            'class' => 'badge bg-info',
        ]);

        // Actions.
        if (has_capability('local/casospracticos:edit', $context)) {
            echo html_writer::start_div('question-actions');

            // Move up/down.
            if ($question->sortorder > 1) {
                $upurl = new moodle_url('/local/casospracticos/case_view.php', [
                    'id' => $id,
                    'action' => 'moveup',
                    'questionid' => $question->id,
                    'sesskey' => sesskey(),
                ]);
                echo html_writer::link($upurl, $OUTPUT->pix_icon('t/up', get_string('moveup')));
            }
            if ($question->sortorder < count($case->questions)) {
                $downurl = new moodle_url('/local/casospracticos/case_view.php', [
                    'id' => $id,
                    'action' => 'movedown',
                    'questionid' => $question->id,
                    'sesskey' => sesskey(),
                ]);
                echo html_writer::link($downurl, $OUTPUT->pix_icon('t/down', get_string('movedown')));
            }

            // Edit.
            $editurl = new moodle_url('/local/casospracticos/question_edit.php', ['id' => $question->id]);
            echo html_writer::link($editurl, $OUTPUT->pix_icon('t/edit', get_string('edit')));

            // Delete.
            $deleteurl = new moodle_url('/local/casospracticos/case_view.php', [
                'id' => $id,
                'action' => 'deletequestion',
                'questionid' => $question->id,
                'sesskey' => sesskey(),
            ]);
            echo html_writer::link($deleteurl, $OUTPUT->pix_icon('t/delete', get_string('delete')));

            echo html_writer::end_div();
        }

        echo html_writer::end_div(); // card-header

        echo html_writer::start_div('card-body');

        // Question text.
        echo html_writer::div(format_text($question->questiontext, $question->questiontextformat), 'question-text mb-3');

        // Answers.
        $answers = question_manager::get_answers($question->id);
        if (!empty($answers)) {
            echo html_writer::start_tag('ul', ['class' => 'list-group']);
            foreach ($answers as $answer) {
                $class = 'list-group-item';
                $icon = '';
                if ($answer->fraction > 0) {
                    $class .= ' list-group-item-success';
                    $icon = $OUTPUT->pix_icon('i/valid', get_string('correctanswer', 'local_casospracticos'));
                } else {
                    $icon = $OUTPUT->pix_icon('i/invalid', get_string('incorrectanswer', 'local_casospracticos'));
                }

                $answertext = format_text($answer->answer, $answer->answerformat);
                if ($answer->fraction > 0 && $answer->fraction < 1) {
                    $answertext .= ' <span class="badge bg-info">' . round($answer->fraction * 100) . '%</span>';
                }

                $content = html_writer::div($icon . ' ' . $answertext);

                // Show answer feedback if available.
                if (!empty($answer->feedback)) {
                    $content .= html_writer::div(
                        $OUTPUT->pix_icon('i/info', '') . ' ' .
                        html_writer::tag('em', format_text($answer->feedback, $answer->feedbackformat)),
                        'answer-feedback small text-muted mt-1 ps-4'
                    );
                }

                echo html_writer::tag('li', $content, ['class' => $class]);
            }
            echo html_writer::end_tag('ul');
        }

        // General feedback.
        if (!empty($question->generalfeedback)) {
            echo html_writer::start_div('mt-3 alert alert-info');
            echo html_writer::tag('strong', get_string('generalfeedback', 'local_casospracticos') . ': ');
            echo format_text($question->generalfeedback, $question->generalfeedbackformat);
            echo html_writer::end_div();
        }

        echo html_writer::end_div(); // card-body
        echo html_writer::end_div(); // card
    }
}

// Summary.
echo html_writer::start_div('case-summary card mt-4');
echo html_writer::start_div('card-body');
$totalmarks = case_manager::get_total_marks($id);
echo html_writer::tag('p', '<strong>' . get_string('numquestions', 'local_casospracticos', count($case->questions)) . '</strong>');
echo html_writer::tag('p', '<strong>' . get_string('defaultmark', 'local_casospracticos') . ':</strong> ' . $totalmarks);
echo html_writer::end_div();
echo html_writer::end_div();

// Back button.
echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/local/casospracticos/index.php', ['category' => $case->categoryid]),
        get_string('backtocases', 'local_casospracticos'),
        'get'
    ),
    'mt-4'
);

echo $OUTPUT->footer();
