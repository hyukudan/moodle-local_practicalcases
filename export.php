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
use local_casospracticos\pdf_exporter;
use local_casospracticos\csv_exporter;

// Check for direct export via URL (from bulk actions).
$format = optional_param('format', '', PARAM_ALPHA);
$ids = optional_param('ids', '', PARAM_TEXT);

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:export', $context);

// Direct export (from bulk actions).
if (!empty($format) && !empty($ids)) {
    // CSRF protection - require sesskey for direct export.
    require_sesskey();

    // Validate format.
    $validformats = ['xml', 'json', 'pdf', 'csv'];
    if (!in_array($format, $validformats)) {
        throw new moodle_exception('error:invaliddata', 'local_casospracticos');
    }

    $caseids = array_map('intval', explode(',', $ids));
    $caseids = array_filter($caseids);

    if (empty($caseids)) {
        throw new moodle_exception('error:nocases', 'local_casospracticos');
    }

    // Verify all requested cases exist and user has access.
    global $DB, $USER;
    list($insql, $params) = $DB->get_in_or_equal($caseids, SQL_PARAMS_NAMED);
    $existingcases = $DB->get_records_select('local_cp_cases', "id $insql", $params, '', 'id,createdby');
    $existingids = array_keys($existingcases);

    // Only export cases that exist - silently skip non-existent ones.
    $caseids = array_intersect($caseids, $existingids);

    // Security: Verify ownership or editall capability for each case.
    $haseditall = has_capability('local/casospracticos:editall', $context);
    $allowedids = [];
    foreach ($caseids as $caseid) {
        $case = $existingcases[$caseid];
        if ($case->createdby == $USER->id || $haseditall) {
            $allowedids[] = $caseid;
        }
    }
    $caseids = $allowedids;

    if (empty($caseids)) {
        throw new moodle_exception('error:nopermissiontoexport', 'local_casospracticos');
    }

    $filename = 'practical_cases_' . date('Ymd_His');

    switch ($format) {
        case 'pdf':
            $content = pdf_exporter::export_cases($caseids);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            break;

        case 'csv':
            $content = csv_exporter::export_cases($caseids);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            break;

        case 'json':
            $content = exporter::export_json($caseids);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            break;

        case 'xml':
        default:
            $content = exporter::export_xml($caseids);
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
            break;
    }

    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $content;
    exit;
}

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
        $formats = [
            'xml' => 'XML',
            'json' => 'JSON',
            'pdf' => 'PDF',
            'csv' => 'CSV',
        ];
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

    // Get case IDs if filtering by category.
    if (empty($caseids) && !empty($categoryid)) {
        $cases = case_manager::get_by_category($categoryid);
        $caseids = array_keys($cases);
    } else if (empty($caseids)) {
        // Export all.
        $cases = case_manager::get_all();
        $caseids = array_keys($cases);
    }

    // Security: Verify ownership or editall capability for each case.
    global $USER;
    $haseditall = has_capability('local/casospracticos:editall', $context);
    $allowedids = [];
    foreach ($caseids as $caseid) {
        $case = case_manager::get($caseid);
        if ($case->createdby == $USER->id || $haseditall) {
            $allowedids[] = $caseid;
        }
    }
    $caseids = $allowedids;

    if (empty($caseids)) {
        throw new moodle_exception('error:nopermissiontoexport', 'local_casospracticos');
    }

    $filename = 'practical_cases_' . date('Ymd_His');

    switch ($data->format) {
        case 'pdf':
            $content = pdf_exporter::export_cases($caseids);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
            break;

        case 'csv':
            $content = csv_exporter::export_cases($caseids);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            break;

        case 'json':
            $content = exporter::export_json($caseids, $categoryid);
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            break;

        case 'xml':
        default:
            $content = exporter::export_xml($caseids, $categoryid);
            header('Content-Type: application/xml');
            header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
            break;
    }

    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    echo $content;
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportcases', 'local_casospracticos'));

echo html_writer::tag('p', get_string('exporthelp', 'local_casospracticos'), ['class' => 'alert alert-info']);

$form->display();

echo $OUTPUT->footer();
