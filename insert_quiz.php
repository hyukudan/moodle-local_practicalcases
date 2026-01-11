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
 * Insert case into quiz page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\case_manager;
use local_casospracticos\quiz_integration;

/**
 * Insert quiz form.
 */
class insert_quiz_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $case = $this->_customdata['case'];
        $courses = $this->_customdata['courses'];

        $mform->addElement('hidden', 'caseid', $case->id);
        $mform->setType('caseid', PARAM_INT);

        // Show case info.
        $mform->addElement('static', 'caseinfo', get_string('case', 'local_casospracticos'),
            format_string($case->name) . ' (' . case_manager::count_questions($case->id) . ' preguntas)');

        // Course selector.
        $courseoptions = [];
        foreach ($courses as $course) {
            $courseoptions[$course->id] = format_string($course->fullname);
        }
        $mform->addElement('select', 'courseid', get_string('course'), $courseoptions);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');

        // Quiz will be loaded via AJAX based on course selection.
        $mform->addElement('select', 'quizid', get_string('selectquiz', 'local_casospracticos'), ['' => 'Selecciona un curso primero']);
        $mform->addRule('quizid', get_string('required'), 'required', null, 'client');

        // Or examsimulator.
        $mform->addElement('select', 'examid', 'Examsimulator (opcional)', ['' => 'Ninguno']);

        // Options.
        $mform->addElement('header', 'optionshdr', 'Opciones');

        $mform->addElement('text', 'random_count', get_string('randomquestions', 'local_casospracticos'), ['size' => 5]);
        $mform->setType('random_count', PARAM_INT);
        $mform->setDefault('random_count', 0);
        $mform->addHelpButton('random_count', 'randomquestions', 'local_casospracticos');

        $mform->addElement('selectyesno', 'include_statement', get_string('includestatement', 'local_casospracticos'));
        $mform->setDefault('include_statement', 1);
        $mform->addHelpButton('include_statement', 'includestatement', 'local_casospracticos');

        $mform->addElement('selectyesno', 'shuffle', get_string('shuffleanswers', 'local_casospracticos'));
        $mform->setDefault('shuffle', 0);

        $this->add_action_buttons(true, get_string('insertintoquiz', 'local_casospracticos'));
    }
}

// Parameters.
$caseid = required_param('caseid', PARAM_INT);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:insertquiz', $context);

// Load case.
$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

// Get courses where user can add quiz questions.
$courses = enrol_get_my_courses('', 'fullname ASC');

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/insert_quiz.php', ['caseid' => $caseid]));
$PAGE->set_title(get_string('insertintoquiz', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add(get_string('insertintoquiz', 'local_casospracticos'));

// AJAX handler for getting quizzes.
$courseid = optional_param('courseid', 0, PARAM_INT);
$ajax = optional_param('ajax', 0, PARAM_BOOL);

if ($ajax && $courseid) {
    $quizzes = quiz_integration::get_available_quizzes($courseid);
    $exams = quiz_integration::get_available_examsimulators($courseid);

    $response = [
        'quizzes' => [],
        'exams' => [],
    ];

    foreach ($quizzes as $quiz) {
        $response['quizzes'][] = ['id' => $quiz->id, 'name' => $quiz->name];
    }
    foreach ($exams as $exam) {
        $response['exams'][] = ['id' => $exam->id, 'name' => $exam->name];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$form = new insert_quiz_form(null, ['case' => $case, 'courses' => $courses]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));

} else if ($data = $form->get_data()) {
    $options = [
        'random_count' => $data->random_count,
        'shuffle' => (bool) $data->shuffle,
        'include_statement' => (bool) $data->include_statement,
    ];

    // Insert into quiz or examsimulator.
    if (!empty($data->quizid)) {
        $result = quiz_integration::insert_into_quiz($caseid, $data->quizid, $options);
    } else if (!empty($data->examid)) {
        $result = quiz_integration::insert_into_examsimulator($caseid, $data->examid, $options);
    } else {
        $result = ['success' => false, 'message' => 'Selecciona un quiz o examsimulator'];
    }

    if ($result['success']) {
        \core\notification::success($result['message']);
    } else {
        \core\notification::error($result['message']);
    }

    redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('insertintoquiz', 'local_casospracticos'));

// Add JS for dynamic quiz loading.
$PAGE->requires->js_amd_inline("
require(['jquery'], function($) {
    $('#id_courseid').on('change', function() {
        var courseid = $(this).val();
        if (!courseid) return;

        $.ajax({
            url: window.location.href,
            data: {caseid: {$caseid}, courseid: courseid, ajax: 1},
            dataType: 'json',
            success: function(data) {
                var quizSelect = $('#id_quizid');
                var examSelect = $('#id_examid');

                quizSelect.empty();
                examSelect.empty().append('<option value=\"\">Ninguno</option>');

                if (data.quizzes.length === 0) {
                    quizSelect.append('<option value=\"\">No hay quizzes en este curso</option>');
                } else {
                    $.each(data.quizzes, function(i, quiz) {
                        quizSelect.append('<option value=\"' + quiz.id + '\">' + quiz.name + '</option>');
                    });
                }

                $.each(data.exams, function(i, exam) {
                    examSelect.append('<option value=\"' + exam.id + '\">' + exam.name + '</option>');
                });
            }
        });
    });
});
");

$form->display();

echo $OUTPUT->footer();
