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
 * Export page for practical cases.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_casospracticos\category_manager;
use local_casospracticos\case_manager;
use local_casospracticos\exporter;

/**
 * Export form.
 */
class export_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // Format.
        $formats = exporter::get_formats();
        $mform->addElement('select', 'format', get_string('exportformat', 'local_casospracticos'), $formats);
        $mform->setDefault('format', 'xml');

        // Category filter.
        $categories = category_manager::get_menu();
        $categories[0] = get_string('all');
        $mform->addElement('select', 'categoryid', get_string('category', 'local_casospracticos'), $categories);

        // Or select specific cases.
        $cases = case_manager::get_all();
        $caseoptions = [];
        foreach ($cases as $case) {
            $caseoptions[$case->id] = format_string($case->name);
        }
        $select = $mform->addElement('select', 'caseids', get_string('cases', 'local_casospracticos'), $caseoptions);
        $select->setMultiple(true);
        $mform->addHelpButton('caseids', 'cases', 'local_casospracticos');

        $this->add_action_buttons(true, get_string('export', 'local_casospracticos'));
    }
}

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:export', $context);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/export.php'));
$PAGE->set_title(get_string('exportcases', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('export', 'local_casospracticos'));

$form = new export_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/casospracticos/index.php'));

} else if ($data = $form->get_data()) {
    // Generate export.
    $caseids = $data->caseids ?? [];
    $categoryid = !empty($data->categoryid) ? $data->categoryid : null;

    if ($data->format === 'json') {
        $content = exporter::export_json($caseids, $categoryid);
    } else {
        $content = exporter::export_xml($caseids, $categoryid);
    }

    // Generate filename.
    $filename = 'casos_practicos_' . date('Ymd_His') . '.' . exporter::get_extension($data->format);

    // Send file.
    header('Content-Type: ' . exporter::get_mime_type($data->format));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $content;
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportcases', 'local_casospracticos'));

echo html_writer::tag('p', 'Selecciona los casos a exportar o una categoría completa. Si no seleccionas ningún caso específico y la categoría está en "Todos", se exportarán todos los casos.', ['class' => 'alert alert-info']);

$form->display();

echo $OUTPUT->footer();
