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
 * Configure the optional document deliverable of a practical case.
 *
 * Mirrors the structure and capability gating of case_edit.php. Reachable by
 * direct URL (?caseid=NN) for now; case_view.php wiring is done separately.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/local/casospracticos/lib.php');

use local_casospracticos\case_manager;

/**
 * File manager options for the deliverable start file (single office document).
 *
 * @return array
 */
function local_casospracticos_deliverable_filemanager_options(): array {
    return [
        'subdirs' => 0,
        'maxbytes' => 10485760, // 10MB.
        'maxfiles' => 1,
        'accepted_types' => ['.docx', '.xlsx'],
        'context' => context_system::instance(),
    ];
}

/**
 * Deliverable configuration form.
 */
class local_casospracticos_deliverable_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'caseid');
        $mform->setType('caseid', PARAM_INT);

        // Enabled.
        $mform->addElement('advcheckbox', 'enabled',
            get_string('deliverable:enabled', 'local_casospracticos'), '');
        $mform->addHelpButton('enabled', 'deliverable:enabled', 'local_casospracticos');
        $mform->setDefault('enabled', 0);

        // Expected file type.
        $mform->addElement('select', 'filetype',
            get_string('deliverable:filetype', 'local_casospracticos'), [
                'docx' => 'docx',
                'xlsx' => 'xlsx',
                'any' => get_string('deliverable:filetype:any', 'local_casospracticos'),
            ]);
        $mform->setType('filetype', PARAM_ALPHA);
        $mform->setDefault('filetype', 'docx');

        // Start file (stored in the 'deliverable' filearea, itemid = caseid).
        $mform->addElement('filemanager', 'startfile',
            get_string('deliverable:startfile', 'local_casospracticos'), null,
            local_casospracticos_deliverable_filemanager_options());
        $mform->addHelpButton('startfile', 'deliverable:startfile', 'local_casospracticos');

        // Correction mode.
        $mform->addElement('select', 'correctionmode',
            get_string('deliverable:correctionmode', 'local_casospracticos'), [
                'auto' => get_string('deliverable:correctionmode_auto', 'local_casospracticos'),
                'manual' => get_string('deliverable:correctionmode_manual', 'local_casospracticos'),
            ]);
        $mform->setType('correctionmode', PARAM_ALPHA);
        $mform->setDefault('correctionmode', 'auto');
        $mform->addHelpButton('correctionmode', 'deliverable:correctionmode', 'local_casospracticos');

        // Submission flow.
        $mform->addElement('select', 'submissionflow',
            get_string('deliverable:submissionflow', 'local_casospracticos'), [
                'afterattempt' => get_string('deliverable:submissionflow_afterattempt', 'local_casospracticos'),
                'direct' => get_string('deliverable:submissionflow_direct', 'local_casospracticos'),
            ]);
        $mform->setType('submissionflow', PARAM_ALPHA);
        $mform->setDefault('submissionflow', 'afterattempt');
        $mform->addHelpButton('submissionflow', 'deliverable:submissionflow', 'local_casospracticos');

        // Rubrica (JSON used by the auto corrector).
        $mform->addElement('textarea', 'rubrica',
            get_string('deliverable:rubrica', 'local_casospracticos'),
            ['rows' => 12, 'cols' => 80]);
        $mform->setType('rubrica', PARAM_RAW);
        $mform->addHelpButton('rubrica', 'deliverable:rubrica', 'local_casospracticos');

        // Max score.
        $mform->addElement('text', 'maxscore',
            get_string('deliverable:maxscore', 'local_casospracticos'), ['size' => 10]);
        $mform->setType('maxscore', PARAM_LOCALISEDFLOAT);
        $mform->setDefault('maxscore', 100);

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

        // Strict, locale-aware parse: non-numeric -> false, empty -> null. The
        // max score must be a real number strictly greater than zero.
        $maxscore = isset($data['maxscore']) ? unformat_float($data['maxscore'], true) : null;
        if ($maxscore === false || $maxscore === null || $maxscore <= 0) {
            $errors['maxscore'] = get_string('deliverable:err_maxscore', 'local_casospracticos');
        }

        // Direct submission (deliverable-only, no question attempt) is only
        // supported with manual correction: there is no scored question attempt
        // to feed the Python autograder, so direct+auto is rejected.
        if (($data['submissionflow'] ?? 'afterattempt') === 'direct'
                && ($data['correctionmode'] ?? 'auto') !== 'manual') {
            $errors['submissionflow'] = get_string('deliverable:err_directrequiresmanual', 'local_casospracticos');
        }

        return $errors;
    }
}

// Parameters.
$caseid = required_param('caseid', PARAM_INT);

// Context and access — gated EXACTLY like case_edit.php editing path.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:edit', $context);

// The deliverable always belongs to an existing case.
$case = case_manager::get($caseid);
if (!$case) {
    throw new moodle_exception('error:casenotfound', 'local_casospracticos');
}

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/deliverable_edit.php', ['caseid' => $caseid]));
$PAGE->set_title(get_string('deliverable:editheading', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(format_string($case->name),
    new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]));
$PAGE->navbar->add(get_string('deliverable:editheading', 'local_casospracticos'));

$returnurl = new moodle_url('/local/casospracticos/case_view.php', ['id' => $caseid]);

// Prepare the start-file draft area for this case's deliverable filearea.
$draftitemid = 0;
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_casospracticos',
    'deliverable',
    $caseid,
    local_casospracticos_deliverable_filemanager_options()
);

// Create + populate form.
$form = new local_casospracticos_deliverable_form();

$existing = case_manager::get_deliverable_raw($caseid);
$formdefaults = [
    'caseid' => $caseid,
    'startfile' => $draftitemid,
];
if ($existing) {
    $formdefaults['enabled'] = $existing->enabled;
    $formdefaults['filetype'] = $existing->filetype ?: 'docx';
    $formdefaults['correctionmode'] = $existing->correctionmode ?? 'auto';
    $formdefaults['submissionflow'] = $existing->submissionflow ?? 'afterattempt';
    $formdefaults['rubrica'] = $existing->rubrica;
    $formdefaults['maxscore'] = $existing->maxscore;
}
$form->set_data($formdefaults);

// Process.
if ($form->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $form->get_data()) {
    // Persist the uploaded start file into the deliverable filearea (itemid = caseid).
    file_save_draft_area_files(
        $data->startfile,
        $context->id,
        'local_casospracticos',
        'deliverable',
        $caseid,
        local_casospracticos_deliverable_filemanager_options()
    );

    // Resolve the stored start filename (first file in the area, or none).
    $fs = get_file_storage();
    $areafiles = $fs->get_area_files($context->id, 'local_casospracticos', 'deliverable',
        $caseid, 'filename', false);
    $startfilename = null;
    foreach ($areafiles as $f) {
        $startfilename = $f->get_filename();
        break;
    }

    $record = new stdClass();
    $record->caseid = $caseid;
    $record->enabled = !empty($data->enabled) ? 1 : 0;
    $record->filetype = $data->filetype;
    $record->startfilename = $startfilename;
    $record->rubrica = $data->rubrica;
    $record->maxscore = unformat_float($data->maxscore, true);
    $record->correctionmode = $data->correctionmode;
    $record->submissionflow = $data->submissionflow;

    case_manager::save_deliverable($record);

    \core\notification::success(get_string('deliverable:saved', 'local_casospracticos'));
    redirect($returnurl);
}

// Display.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deliverable:editheading', 'local_casospracticos'));
echo $OUTPUT->box(format_string($case->name), 'generalbox');
$form->display();
echo $OUTPUT->footer();
