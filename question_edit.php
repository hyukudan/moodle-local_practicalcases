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
 * Question edit page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\case_manager;
use local_casospracticos\question_manager;

/**
 * Question edit form.
 */
class question_edit_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $question = $this->_customdata['question'] ?? null;
        $qtype = $this->_customdata['qtype'] ?? ($question->qtype ?? 'multichoice');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'caseid');
        $mform->setType('caseid', PARAM_INT);

        // Question type.
        $qtypes = local_casospracticos_get_supported_qtypes();
        $mform->addElement('select', 'qtype', get_string('questiontype', 'local_casospracticos'), $qtypes);
        $mform->setType('qtype', PARAM_ALPHA);

        // Question text.
        $mform->addElement('editor', 'questiontext_editor', get_string('questiontext', 'local_casospracticos'), [
            'rows' => 5,
        ], [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => false,
            'context' => context_system::instance(),
        ]);
        $mform->setType('questiontext_editor', PARAM_RAW);
        $mform->addRule('questiontext_editor', get_string('required'), 'required', null, 'client');

        // Default mark.
        $mform->addElement('text', 'defaultmark', get_string('defaultmark', 'local_casospracticos'), ['size' => 5]);
        $mform->setType('defaultmark', PARAM_FLOAT);
        $mform->setDefault('defaultmark', 1);

        // Single answer (for multichoice).
        $mform->addElement('select', 'single', get_string('singleanswer', 'local_casospracticos'), [
            1 => get_string('singleanswer', 'local_casospracticos'),
            0 => get_string('multipleanswers', 'local_casospracticos'),
        ]);
        $mform->setType('single', PARAM_INT);
        $mform->hideIf('single', 'qtype', 'neq', 'multichoice');

        // Shuffle answers.
        $mform->addElement('selectyesno', 'shuffleanswers', get_string('shuffleanswers', 'local_casospracticos'));
        $mform->setDefault('shuffleanswers', 1);

        // General feedback.
        $mform->addElement('editor', 'generalfeedback_editor', get_string('generalfeedback', 'local_casospracticos'), [
            'rows' => 3,
        ], [
            'maxfiles' => 0,
            'noclean' => false,
        ]);
        $mform->setType('generalfeedback_editor', PARAM_RAW);

        // Answers section.
        $mform->addElement('header', 'answershdr', get_string('answers', 'local_casospracticos'));
        $mform->setExpanded('answershdr');

        // Number of answers.
        $numanswers = $this->_customdata['numanswers'] ?? 4;
        if ($question && !empty($question->answers)) {
            $numanswers = max($numanswers, count($question->answers));
        }

        // Fractions for grading.
        $fractions = [
            '1.0' => '100%',
            '0.9' => '90%',
            '0.8333333' => '83.33%',
            '0.8' => '80%',
            '0.75' => '75%',
            '0.7' => '70%',
            '0.6666667' => '66.67%',
            '0.6' => '60%',
            '0.5' => '50%',
            '0.4' => '40%',
            '0.3333333' => '33.33%',
            '0.3' => '30%',
            '0.25' => '25%',
            '0.2' => '20%',
            '0.1666667' => '16.67%',
            '0.1428571' => '14.29%',
            '0.125' => '12.5%',
            '0.1111111' => '11.11%',
            '0.1' => '10%',
            '0.05' => '5%',
            '0.0' => get_string('incorrect', 'question'),
            '-0.1' => '-10%',
            '-0.2' => '-20%',
            '-0.25' => '-25%',
            '-0.3333333' => '-33.33%',
            '-0.5' => '-50%',
            '-1.0' => '-100%',
        ];

        for ($i = 0; $i < $numanswers; $i++) {
            $mform->addElement('hidden', "answer_id[{$i}]");
            $mform->setType("answer_id[{$i}]", PARAM_INT);

            $mform->addElement('editor', "answer_editor[{$i}]", get_string('answer', 'local_casospracticos') . ' ' . ($i + 1), [
                'rows' => 2,
            ], [
                'maxfiles' => 0,
                'noclean' => false,
            ]);
            $mform->setType("answer_editor[{$i}]", PARAM_RAW);

            $mform->addElement('select', "fraction[{$i}]", get_string('fraction', 'local_casospracticos'), $fractions);
            // Use PARAM_TEXT as this is a select element with predefined string values.
            // The validation() method validates that the value is a proper float within range.
            $mform->setType("fraction[{$i}]", PARAM_TEXT);
            $mform->setDefault("fraction[{$i}]", '0.0');

            $mform->addElement('editor', "feedback_editor[{$i}]", get_string('feedback', 'local_casospracticos'), [
                'rows' => 1,
            ], [
                'maxfiles' => 0,
                'noclean' => false,
            ]);
            $mform->setType("feedback_editor[{$i}]", PARAM_RAW);
        }

        // Button to add more answers.
        $mform->addElement('hidden', 'numanswers', $numanswers);
        $mform->setType('numanswers', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validation.
     *
     * @param array $data Form data
     * @param array $files Files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check at least one answer with content.
        $hasanswer = false;
        foreach ($data['answer_editor'] as $answer) {
            if (!empty(trim(strip_tags($answer['text'])))) {
                $hasanswer = true;
                break;
            }
        }
        if (!$hasanswer) {
            $errors['answer_editor[0]'] = get_string('required');
        }

        // Validate fraction values are within acceptable range.
        $validfractions = [
            '1.0', '0.9', '0.8333333', '0.8', '0.75', '0.7', '0.6666667', '0.6',
            '0.5', '0.4', '0.3333333', '0.3', '0.25', '0.2', '0.1666667', '0.1428571',
            '0.125', '0.1111111', '0.1', '0.05', '0.0', '-0.1', '-0.2', '-0.25',
            '-0.3333333', '-0.5', '-1.0',
        ];

        // Check at least one correct answer and validate fraction values.
        $hascorrect = false;
        foreach ($data['fraction'] as $i => $fraction) {
            // Validate fraction is from allowed list.
            if (!in_array($fraction, $validfractions)) {
                $errors["fraction[{$i}]"] = 'Invalid fraction value';
                continue;
            }

            $answertext = $data['answer_editor'][$i]['text'] ?? '';
            if (!empty(trim(strip_tags($answertext))) && (float)$fraction > 0) {
                $hascorrect = true;
            }
        }
        if (!$hascorrect && $data['qtype'] !== 'truefalse') {
            $errors['fraction[0]'] = 'At least one correct answer is required';
        }

        return $errors;
    }
}

// Parameters.
$id = optional_param('id', 0, PARAM_INT);
$caseid = optional_param('caseid', 0, PARAM_INT);
$qtype = optional_param('qtype', 'multichoice', PARAM_ALPHA);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:edit', $context);

// Load existing question if editing.
$question = null;
if ($id) {
    $question = question_manager::get_with_answers($id);
    if (!$question) {
        throw new moodle_exception('error:questionnotfound', 'local_casospracticos');
    }
    $caseid = $question->caseid;
    $qtype = $question->qtype;
}

// Load case.
$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/question_edit.php', ['id' => $id, 'caseid' => $caseid]));
$PAGE->set_title($id ? get_string('editquestion', 'local_casospracticos') : get_string('newquestion', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add($id ? get_string('editquestion', 'local_casospracticos') : get_string('newquestion', 'local_casospracticos'));

// Create form.
$customdata = [
    'question' => $question,
    'qtype' => $qtype,
    'numanswers' => max(4, $question ? count($question->answers) : 4),
];
$form = new question_edit_form(null, $customdata);

// Set form data.
if ($question) {
    $formdata = clone $question;
    $formdata->questiontext_editor = [
        'text' => $question->questiontext,
        'format' => $question->questiontextformat,
    ];
    $formdata->generalfeedback_editor = [
        'text' => $question->generalfeedback,
        'format' => $question->generalfeedbackformat,
    ];

    // Set answers.
    $i = 0;
    foreach ($question->answers as $answer) {
        $formdata->{"answer_id"}[$i] = $answer->id;
        $formdata->{"answer_editor"}[$i] = [
            'text' => $answer->answer,
            'format' => $answer->answerformat,
        ];
        $formdata->{"fraction"}[$i] = (string)$answer->fraction;
        $formdata->{"feedback_editor"}[$i] = [
            'text' => $answer->feedback,
            'format' => $answer->feedbackformat,
        ];
        $i++;
    }

    $form->set_data($formdata);
} else {
    $form->set_data(['caseid' => $caseid, 'qtype' => $qtype]);
}

// Process form.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
} else if ($data = $form->get_data()) {
    // Prepare question data.
    $record = new stdClass();
    $record->caseid = $data->caseid;
    $record->questiontext = $data->questiontext_editor['text'];
    $record->questiontextformat = $data->questiontext_editor['format'];
    $record->qtype = $data->qtype;
    $record->defaultmark = $data->defaultmark;
    $record->single = $data->single ?? 1;
    $record->shuffleanswers = $data->shuffleanswers;
    $record->generalfeedback = $data->generalfeedback_editor['text'];
    $record->generalfeedbackformat = $data->generalfeedback_editor['format'];

    if ($data->id) {
        // Update question.
        $record->id = $data->id;
        question_manager::update($record);

        // Update answers.
        $existingids = [];
        foreach ($data->answer_editor as $i => $answer) {
            $answertext = trim(strip_tags($answer['text']));
            if (empty($answertext)) {
                continue;
            }

            $answerdata = new stdClass();
            $answerdata->answer = $answer['text'];
            $answerdata->answerformat = $answer['format'];
            $answerdata->fraction = (float)$data->fraction[$i];
            $answerdata->feedback = $data->feedback_editor[$i]['text'];
            $answerdata->feedbackformat = $data->feedback_editor[$i]['format'];
            $answerdata->sortorder = $i + 1;

            if (!empty($data->answer_id[$i])) {
                // Update existing answer.
                $answerdata->id = $data->answer_id[$i];
                question_manager::update_answer($answerdata);
                $existingids[] = $answerdata->id;
            } else {
                // Create new answer.
                $answerdata->questionid = $data->id;
                $newid = question_manager::create_answer($answerdata);
                $existingids[] = $newid;
            }
        }

        // Delete removed answers.
        $oldanswers = question_manager::get_answers($data->id);
        foreach ($oldanswers as $oldanswer) {
            if (!in_array($oldanswer->id, $existingids)) {
                question_manager::delete_answer($oldanswer->id);
            }
        }

        \core\notification::success(get_string('questionupdated', 'local_casospracticos'));
    } else {
        // Create new question with answers.
        $answers = [];
        foreach ($data->answer_editor as $i => $answer) {
            $answertext = trim(strip_tags($answer['text']));
            if (empty($answertext)) {
                continue;
            }

            $answers[] = [
                'answer' => $answer['text'],
                'answerformat' => $answer['format'],
                'fraction' => (float)$data->fraction[$i],
                'feedback' => $data->feedback_editor[$i]['text'],
                'feedbackformat' => $data->feedback_editor[$i]['format'],
                'sortorder' => $i + 1,
            ];
        }
        $record->answers = $answers;

        question_manager::create($record);
        \core\notification::success(get_string('questioncreated', 'local_casospracticos'));
    }

    redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
}

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editquestion', 'local_casospracticos') : get_string('newquestion', 'local_casospracticos'));
echo html_writer::tag('p', get_string('case', 'local_casospracticos') . ': ' . format_string($case->name), ['class' => 'lead']);
$form->display();
echo $OUTPUT->footer();
