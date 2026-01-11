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
 * Import page for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\category_manager;
use local_casospracticos\importer;

/**
 * Import form.
 */
class import_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // File picker.
        $mform->addElement('filepicker', 'importfile', get_string('importfile', 'local_casospracticos'), null, [
            'maxbytes' => 10485760, // 10MB.
            'accepted_types' => ['.xml', '.json'],
        ]);
        $mform->addRule('importfile', get_string('required'), 'required', null, 'client');

        // Target category.
        $categories = category_manager::get_menu();
        $categories[0] = get_string('newcategory', 'local_casospracticos') . ' (desde archivo)';
        $mform->addElement('select', 'categoryid', get_string('category', 'local_casospracticos'), $categories);
        $mform->addHelpButton('categoryid', 'category', 'local_casospracticos');

        $this->add_action_buttons(true, get_string('import', 'local_casospracticos'));
    }
}

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:import', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/import.php'));
$PAGE->set_title(get_string('importcases', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('import', 'local_casospracticos'));

$form = new import_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/index.php'));

} else if ($data = $form->get_data()) {
    // Get uploaded file.
    $content = $form->get_file_content('importfile');
    $filename = $form->get_new_filename('importfile');
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Target category.
    $categoryid = !empty($data->categoryid) ? $data->categoryid : null;

    // Validate first.
    $validation = importer::validate($content, $extension);

    if (!$validation['valid']) {
        \core\notification::error(get_string('importerror', 'local_casospracticos', $validation['error']));
        redirect(new moodle_url('/local/casospracticos/import.php'));
    }

    // Import.
    $importerobj = new importer();
    $result = $importerobj->import_content($content, $extension, $categoryid);

    if ($result['success']) {
        \core\notification::success(get_string('importsuccessful', 'local_casospracticos', (object) [
            'cases' => $result['cases'],
            'questions' => $result['questions'],
        ]));
    } else {
        foreach ($result['errors'] as $error) {
            \core\notification::error($error);
        }
    }

    redirect(new moodle_url('/local/casospracticos/index.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importcases', 'local_casospracticos'));

echo html_writer::tag('p', 'Sube un archivo XML o JSON exportado previamente. Las categorías se crearán automáticamente si seleccionas "Nueva categoría (desde archivo)".', ['class' => 'alert alert-info']);

$form->display();

// Show format example.
echo html_writer::tag('h4', 'Formato XML esperado:', ['class' => 'mt-4']);
echo html_writer::start_tag('pre', ['class' => 'bg-light p-3']);
echo htmlspecialchars('<?xml version="1.0" encoding="UTF-8"?>
<casospracticos version="1.0">
  <category>
    <name>Derecho Administrativo</name>
    <case>
      <name>Caso sobre silencio administrativo</name>
      <statement><![CDATA[<p>Enunciado largo del caso...</p>]]></statement>
      <status>published</status>
      <questions>
        <question type="multichoice">
          <text><![CDATA[¿Cuál es el plazo...?]]></text>
          <defaultmark>1</defaultmark>
          <answer fraction="1">
            <text>3 meses</text>
            <feedback>Correcto según art. X</feedback>
          </answer>
          <answer fraction="0">
            <text>6 meses</text>
          </answer>
        </question>
      </questions>
    </case>
  </category>
</casospracticos>');
echo html_writer::end_tag('pre');

echo $OUTPUT->footer();
