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
 * Category edit page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\category_manager;

/**
 * Category edit form.
 */
class category_edit_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $category = $this->_customdata['category'] ?? null;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('categoryname', 'local_casospracticos'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('editor', 'description_editor', get_string('categorydescription', 'local_casospracticos'), null, [
            'maxfiles' => 0,
            'noclean' => false,
        ]);
        $mform->setType('description_editor', PARAM_RAW);

        // Parent category.
        $parents = category_manager::get_menu($category->id ?? null);
        $mform->addElement('select', 'parent', get_string('parentcategory', 'local_casospracticos'), $parents);
        $mform->setType('parent', PARAM_INT);

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

        return $errors;
    }
}

// Parameters.
$id = optional_param('id', 0, PARAM_INT);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:managecategories', $context);

// Load existing category if editing.
$category = null;
if ($id) {
    $category = category_manager::get($id);
    if (!$category) {
        throw new moodle_exception('error:categorynotfound', 'local_casospracticos');
    }
}

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/category_edit.php', ['id' => $id]));
$PAGE->set_title($id ? get_string('editcategory', 'local_casospracticos') : get_string('newcategory', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add($id ? get_string('editcategory', 'local_casospracticos') : get_string('newcategory', 'local_casospracticos'));

// Create form.
$form = new category_edit_form(null, ['category' => $category]);

// Set form data.
if ($category) {
    $formdata = clone $category;
    $formdata->description_editor = [
        'text' => $category->description,
        'format' => $category->descriptionformat,
    ];
    $form->set_data($formdata);
}

// Process form.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/index.php'));
} else if ($data = $form->get_data()) {
    $record = new stdClass();
    $record->name = $data->name;
    $record->description = $data->description_editor['text'];
    $record->descriptionformat = $data->description_editor['format'];
    $record->parent = $data->parent;

    if ($data->id) {
        $record->id = $data->id;
        category_manager::update($record);
        \core\notification::success(get_string('categoryupdated', 'local_casospracticos'));
    } else {
        category_manager::create($record);
        \core\notification::success(get_string('categorycreated', 'local_casospracticos'));
    }

    redirect(new moodle_url('/local/casospracticos/index.php'));
}

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editcategory', 'local_casospracticos') : get_string('newcategory', 'local_casospracticos'));
$form->display();
echo $OUTPUT->footer();
