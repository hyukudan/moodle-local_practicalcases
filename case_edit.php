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
 * Case edit page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;

/**
 * Case edit form.
 */
class case_edit_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Name.
        $mform->addElement('text', 'name', get_string('casename', 'local_casospracticos'), ['size' => 80]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Category.
        $categories = category_manager::get_menu();
        unset($categories[0]); // Remove "Top level" option.
        $mform->addElement('select', 'categoryid', get_string('category', 'local_casospracticos'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->addRule('categoryid', get_string('required'), 'required', null, 'client');

        // Statement (long text).
        $mform->addElement('editor', 'statement_editor', get_string('casestatement', 'local_casospracticos'), [
            'rows' => 15,
        ], [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => false,
            'context' => context_system::instance(),
        ]);
        $mform->setType('statement_editor', PARAM_RAW);
        $mform->addRule('statement_editor', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('statement_editor', 'casestatement', 'local_casospracticos');

        // Status.
        $statuses = local_casospracticos_get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'local_casospracticos'), $statuses);
        $mform->setType('status', PARAM_ALPHA);
        $mform->setDefault('status', 'draft');

        // Difficulty.
        $difficulties = ['' => '-'];
        for ($i = 1; $i <= 5; $i++) {
            $difficulties[$i] = $i;
        }
        $mform->addElement('select', 'difficulty', get_string('difficulty', 'local_casospracticos'), $difficulties);
        $mform->setType('difficulty', PARAM_INT);
        $mform->addHelpButton('difficulty', 'difficulty', 'local_casospracticos');

        // Tags.
        $mform->addElement('text', 'tags', get_string('tags', 'local_casospracticos'), ['size' => 80]);
        $mform->setType('tags', PARAM_TEXT);

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

        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('required');
        }

        if (empty($data['categoryid'])) {
            $errors['categoryid'] = get_string('required');
        }

        return $errors;
    }
}

// Parameters.
$id = optional_param('id', 0, PARAM_INT);
$categoryid = optional_param('category', 0, PARAM_INT);

// Context and access.
$context = context_system::instance();
require_login();

if ($id) {
    require_capability('local/casospracticos:edit', $context);
} else {
    require_capability('local/casospracticos:create', $context);
}

// Load existing case if editing.
$case = null;
if ($id) {
    $case = case_manager::get($id);
    if (!$case) {
        throw new moodle_exception('error:casenotfound', 'local_casospracticos');
    }
    $categoryid = $case->categoryid;
}

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/case_edit.php', ['id' => $id, 'category' => $categoryid]));
$PAGE->set_title($id ? get_string('editcase', 'local_casospracticos') : get_string('newcase', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
if ($categoryid) {
    $category = category_manager::get($categoryid);
    if ($category) {
        $PAGE->navbar->add(format_string($category->name),
            new moodle_url('/local/casospracticos/index.php', ['category' => $categoryid]));
    }
}
$PAGE->navbar->add($id ? get_string('editcase', 'local_casospracticos') : get_string('newcase', 'local_casospracticos'));

// Create form.
$form = new case_edit_form();

// Set form data.
if ($case) {
    $formdata = clone $case;
    $formdata->statement_editor = [
        'text' => $case->statement,
        'format' => $case->statementformat,
    ];
    $formdata->tags = implode(', ', case_manager::decode_tags($case->tags));
    $form->set_data($formdata);
} else if ($categoryid) {
    $form->set_data(['categoryid' => $categoryid]);
}

// Process form.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/index.php', ['category' => $categoryid]));
} else if ($data = $form->get_data()) {
    $record = new stdClass();
    $record->name = $data->name;
    $record->categoryid = $data->categoryid;
    $record->statement = $data->statement_editor['text'];
    $record->statementformat = $data->statement_editor['format'];
    $record->status = $data->status;
    $record->difficulty = !empty($data->difficulty) ? $data->difficulty : null;
    $record->tags = $data->tags;

    if ($data->id) {
        $record->id = $data->id;
        case_manager::update($record);
        \core\notification::success(get_string('caseupdated', 'local_casospracticos'));
        $redirectcategory = $record->categoryid;
    } else {
        $newid = case_manager::create($record);
        \core\notification::success(get_string('casecreated', 'local_casospracticos'));
        // Redirect to the case view to add questions.
        redirect(new moodle_url('/local/casospracticos/case_view.php', ['id' => $newid]));
    }

    redirect(new moodle_url('/local/casospracticos/index.php', ['category' => $data->categoryid]));
}

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editcase', 'local_casospracticos') : get_string('newcase', 'local_casospracticos'));
$form->display();
echo $OUTPUT->footer();
