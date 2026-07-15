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
require_once($CFG->dirroot . '/local/casospracticos/lib.php');

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;
use local_casospracticos\question_manager;

// Parameters.
$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$questionid = optional_param('questionid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$preview = optional_param('preview', 0, PARAM_BOOL);

// Context and access.
$context = context_system::instance();
require_login();

// Paywall: NONE is redirected to the CTA; trial (STATEMENT) sees the enunciado only.
$viewaccess = local_casospracticos_require_view_access(LOCAL_CP_ACCESS_STATEMENT);
$statementonly = ($viewaccess !== LOCAL_CP_ACCESS_FULL);

// Load case.
$case = case_manager::get_with_questions($id);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}
if (!case_manager::is_visible_to_user($case, $context)) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

$category = category_manager::get($case->categoryid);

// Page setup.
$PAGE->set_context($context);
$pageurlparams = ['id' => $id];
if ($preview) {
    $pageurlparams['preview'] = 1;
}
$PAGE->set_url(new moodle_url('/local/casospracticos/case_view.php', $pageurlparams));
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

// -------------------------------------------------------------------------
// Build the template context (full migration to local_casospracticos/case_view).
//
// Every region the page historically rendered with html_writer is reproduced
// here. PHP-only regions that are impractical to express in Mustache
// (attachments block, per-question canonical solution feedback + normativa
// panels, the trial upgrade CTA and the teacher/editorial action_menu) are
// pre-rendered in PHP and injected as trusted HTML via triple-mustache.
// -------------------------------------------------------------------------

$statuses = local_casospracticos_get_status_options();
$statusclassmap = [
    'draft' => 'bg-secondary',
    'published' => 'bg-success',
    'archived' => 'bg-warning',
];

$canedit = has_capability('local/casospracticos:edit', $context);
$canviewanswers = has_capability('local/casospracticos:viewanswers', $context)
    || case_manager::can_view_unpublished($context);
$canreviewblocked = $canedit || has_capability('local/casospracticos:review', $context);
if (!$canreviewblocked) {
    $case->questions = question_manager::filter_practice_questions($case->questions);
}
$hasquestions = !empty($case->questions);
$numquestions = count($case->questions);

// Practice / timed / my-attempts buttons: only for full-access viewers with questions.
$showpractice = $hasquestions && !$statementonly;
// Questions + summary block: statement-only trial users and the "Ver supuesto"
// preview route must never see the questions or their answer keys.
$showquestions = !$preview && !$statementonly;

// Teacher / editorial action menu (edit case / new question / insert into quiz),
// built as a core action_menu. Each item is gated by its own capability.
$teachermenuhtml = '';
$menu = new action_menu();
$hasmenuitems = false;
if ($canedit) {
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/local/casospracticos/case_edit.php', ['id' => $id]),
        new pix_icon('t/edit', ''),
        get_string('editcase', 'local_casospracticos')
    ));
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/local/casospracticos/question_edit.php', ['caseid' => $id]),
        new pix_icon('t/add', ''),
        get_string('newquestion', 'local_casospracticos')
    ));
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/local/casospracticos/deliverable_edit.php', ['caseid' => $id]),
        new pix_icon('i/upload', ''),
        get_string('editdeliverable', 'local_casospracticos')
    ));
    $hasmenuitems = true;
}
if ($showpractice && has_capability('local/casospracticos:insertquiz', $context)) {
    $menu->add(new action_menu_link_secondary(
        new moodle_url('/local/casospracticos/insert_quiz.php', ['caseid' => $id]),
        new pix_icon('i/import', ''),
        get_string('insertintoquiz', 'local_casospracticos')
    ));
    $hasmenuitems = true;
}
if ($hasmenuitems) {
    $menu->set_menu_trigger(get_string('actions', 'local_casospracticos'), 'btn btn-outline-secondary');
    $teachermenuhtml = $OUTPUT->render($menu);
}

// Attachments block (shown before the paywall gate, so trial users see it too).
$attachmentshtml = '';
$attachments = case_manager::get_attachments($id);
if (!empty($attachments)) {
    $attachmentshtml .= html_writer::start_div('case-attachments card mb-4');
    $attachmentshtml .= html_writer::start_div('card-header bg-light');
    $attachmentshtml .= html_writer::tag('h5', get_string('attachments', 'local_casospracticos') .
        ' <span class="badge bg-secondary">' . count($attachments) . '</span>', ['class' => 'mb-0']);
    $attachmentshtml .= html_writer::end_div();
    $attachmentshtml .= html_writer::start_div('card-body');
    $attachmentshtml .= html_writer::start_tag('ul', ['class' => 'list-group list-group-flush']);

    foreach ($attachments as $attachment) {
        $attachmentshtml .= html_writer::start_tag('li',
            ['class' => 'list-group-item d-flex justify-content-between align-items-center']);

        // File info with icon.
        $fileinfo = html_writer::tag('i', '', ['class' => 'fa ' . $attachment->icon . ' me-2', 'aria-hidden' => 'true']);
        $fileinfo .= html_writer::tag('span', s($attachment->filename), ['class' => 'fw-medium']);
        $fileinfo .= html_writer::tag('span', ' (' . $attachment->filesizeformatted . ')', ['class' => 'text-muted small']);
        $attachmentshtml .= html_writer::div($fileinfo);

        // Action buttons.
        $attachmentshtml .= html_writer::start_div('btn-group btn-group-sm');

        // Download button.
        $attachmentshtml .= html_writer::link(
            $attachment->downloadurl,
            html_writer::tag('i', '', ['class' => 'fa fa-download', 'aria-hidden' => 'true']) . ' ' .
            get_string('download', 'moodle'),
            ['class' => 'btn btn-outline-primary btn-sm', 'title' => get_string('download', 'moodle')]
        );

        // View button for embeddable files.
        if ($attachment->isembeddable) {
            $attachmentshtml .= html_writer::link(
                $attachment->viewurl,
                html_writer::tag('i', '', ['class' => 'fa fa-eye', 'aria-hidden' => 'true']) . ' ' .
                get_string('view'),
                ['class' => 'btn btn-outline-secondary btn-sm', 'target' => '_blank', 'title' => get_string('view')]
            );
        }

        $attachmentshtml .= html_writer::end_div(); // btn-group
        $attachmentshtml .= html_writer::end_tag('li');
    }

    $attachmentshtml .= html_writer::end_tag('ul');
    $attachmentshtml .= html_writer::end_div(); // card-body
    $attachmentshtml .= html_writer::end_div(); // card
}

// Upgrade CTA: only for statement-only trial users (empty for full access and preview).
$upgradectahtml = $statementonly ? local_casospracticos_render_upgrade_cta() : '';

// Questions (pre-built only when they will actually be shown to this viewer).
$questionsdata = [];
if ($showquestions && $hasquestions) {
    $qtypes = local_casospracticos_get_supported_qtypes();

    // Performance: pre-load all answers and normativa links to avoid N+1 queries.
    $questionids = array_column($case->questions, 'id');
    // Answer keys, feedback and legal-basis links are privileged editorial data.
    // Do not even load them for an ordinary learner opening the case statement.
    $allanswers = $canviewanswers
        ? question_manager::get_answers_for_questions($questionids)
        : [];
    $allnormativa = $canviewanswers
        ? local_casospracticos_get_normativa_links($questionids)
        : [];

    foreach ($case->questions as $index => $question) {
        // Answers.
        $answersdata = [];
        $answers = $canviewanswers ? ($allanswers[$question->id] ?? []) : [];
        foreach ($answers as $answer) {
            $answersdata[] = [
                'id' => $answer->id,
                'answer' => format_text($answer->answer, $answer->answerformat),
                'iscorrect' => ($answer->fraction > 0),
                'showfraction' => ($answer->fraction > 0 && $answer->fraction < 1),
                'fraction' => round($answer->fraction * 100),
                'feedback' => !empty($answer->feedback)
                    ? format_text($answer->feedback, $answer->feedbackformat) : '',
            ];
        }

        // Canonical solution feedback + normativa panel (pre-rendered HTML).
        $qnormativa = $canviewanswers ? ($allnormativa[$question->id] ?? []) : [];
        $solutionhtml = $canviewanswers
            ? local_casospracticos_render_solution_feedback($question, $qnormativa)
            : '';

        $qdata = [
            'id' => $question->id,
            'number' => $index + 1,
            'questiontext' => format_text($question->questiontext, $question->questiontextformat),
            'qtypelabel' => $qtypes[$question->qtype] ?? $question->qtype,
            'defaultmark' => $question->defaultmark,
            'answers' => $answersdata,
            'hasanswers' => !empty($answersdata),
            'solutionhtml' => $solutionhtml,
            'canviewanswers' => $canviewanswers,
            'canedit' => $canedit,
        ];

        // Teacher per-question reorder/edit/delete actions (server-side, as before).
        if ($canedit) {
            $qdata['canmoveup'] = ($question->sortorder > 1);
            $qdata['canmovedown'] = ($question->sortorder < $numquestions);
            $qdata['moveupurl'] = (new moodle_url('/local/casospracticos/case_view.php', [
                'id' => $id, 'action' => 'moveup', 'questionid' => $question->id, 'sesskey' => sesskey(),
            ]))->out(false);
            $qdata['movedownurl'] = (new moodle_url('/local/casospracticos/case_view.php', [
                'id' => $id, 'action' => 'movedown', 'questionid' => $question->id, 'sesskey' => sesskey(),
            ]))->out(false);
            $qdata['editurl'] = (new moodle_url('/local/casospracticos/question_edit.php',
                ['id' => $question->id]))->out(false);
            $qdata['deleteurl'] = (new moodle_url('/local/casospracticos/case_view.php', [
                'id' => $id, 'action' => 'deletequestion', 'questionid' => $question->id, 'sesskey' => sesskey(),
            ]))->out(false);
        }

        $questionsdata[] = $qdata;
    }
}

$templatecontext = [
    'case' => [
        'id' => $case->id,
        'name' => format_string($case->name),
        'statement' => format_text($case->statement, $case->statementformat),
        'statusclass' => $statusclassmap[$case->status] ?? 'bg-secondary',
        'statuslabel' => $statuses[$case->status] ?? $case->status,
        'questioncount' => $numquestions,
        'totalmarks' => case_manager::get_total_marks($id),
        'hasquestions' => $hasquestions,
    ],
    'canedit' => $canedit,
    'showpractice' => $showpractice,
    'showquestions' => $showquestions,
    'printable' => true,
    // Toolbar URLs and capability flags.
    'practiceurl' => (new moodle_url('/local/casospracticos/practice.php', ['id' => $id]))->out(false),
    'timedurl' => (new moodle_url('/local/casospracticos/practice_timed.php', ['id' => $id]))->out(false),
    'myattemptsurl' => (new moodle_url('/local/casospracticos/my_attempts.php', ['caseid' => $id]))->out(false),
    'canexport' => has_capability('local/casospracticos:export', $context),
    'exporturl' => (new moodle_url('/local/casospracticos/export.php', ['caseids[]' => $id]))->out(false),
    'canstats' => has_capability('local/casospracticos:viewaudit', $context),
    'statsurl' => (new moodle_url('/local/casospracticos/case_stats.php', ['id' => $id]))->out(false),
    'newquestionurl' => (new moodle_url('/local/casospracticos/question_edit.php', ['caseid' => $id]))->out(false),
    'backurl' => (new moodle_url('/local/casospracticos/index.php', ['category' => $case->categoryid]))->out(false),
    // Pre-rendered trusted HTML regions.
    'teachermenuhtml' => $teachermenuhtml,
    'attachmentshtml' => $attachmentshtml,
    'upgradectahtml' => $upgradectahtml,
    'questions' => $questionsdata,
];

echo $OUTPUT->render_from_template('local_casospracticos/case_view', $templatecontext);

echo $OUTPUT->footer();
