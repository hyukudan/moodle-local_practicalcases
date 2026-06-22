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
 * PDF Library - manage, view, and download practical case PDFs.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Constants.
define('PDF_OUTPUT_DIR', '/home/preparaoposiciones/desarrollo/temario-pdf-generator/output/casos_practicos');
define('PDF_GENERATOR_SCRIPT', '/home/preparaoposiciones/desarrollo/casos_practicos_originales/scripts/generate_pdf.php');
define('PHP_BIN', '/usr/bin/php8.5');

// Context and access.
$context = context_system::instance();
require_login();
require_capability('local/casospracticos:export', $context);

// Parameters.
$action = optional_param('action', '', PARAM_ALPHA);
$catid = optional_param('catid', 0, PARAM_INT);

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/pdf_library.php'));
$PAGE->set_title(get_string('pdflibrary', 'local_casospracticos'));
$PAGE->set_heading(get_string('pdflibrary', 'local_casospracticos'));
$PAGE->set_pagelayout('admin');

// Navigation.
$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('pdflibrary', 'local_casospracticos'));

// ============================================================
// Handle download action (before any output).
// ============================================================
if ($action === 'download' && $catid > 0) {
    require_sesskey();
    $category = $DB->get_record('local_cp_categories', ['id' => $catid], '*', MUST_EXIST);
    $publishedcases = $DB->count_records('local_cp_cases', ['categoryid' => $catid, 'status' => 'published']);
    if ($publishedcases === 0) {
        redirect(
            new moodle_url('/local/casospracticos/pdf_library.php'),
            get_string('nocases', 'local_casospracticos'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }

    $filename = get_pdf_filename($category->name);
    $filepath = PDF_OUTPUT_DIR . '/' . $filename;

    if (file_exists($filepath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        readfile($filepath);
        exit;
    } else {
        \core\notification::error(get_string('pdfmissing', 'local_casospracticos'));
    }
}

// ============================================================
// Handle regeneration actions.
// ============================================================
if ($action === 'regenerate' && confirm_sesskey()) {
    if ($catid > 0) {
        // Regenerate single category.
        $result = regenerate_pdf($catid);
        if ($result['success']) {
            \core\notification::success(get_string('regeneratesuccess', 'local_casospracticos'));
        } else {
            \core\notification::error(get_string('regenerateerror', 'local_casospracticos') . ': ' . $result['error']);
        }
    }
    redirect(new moodle_url('/local/casospracticos/pdf_library.php'));
}

if ($action === 'regenerateall' && confirm_sesskey()) {
    $categories = $DB->get_records('local_cp_categories', [], 'name ASC');
    $success = 0;
    $errors = 0;
    foreach ($categories as $cat) {
        $result = regenerate_pdf($cat->id);
        if ($result['success']) {
            $success++;
        } else {
            $errors++;
        }
    }
    if ($success > 0) {
        \core\notification::success(get_string('regeneratesuccess', 'local_casospracticos') . " ({$success})");
    }
    if ($errors > 0) {
        \core\notification::error(get_string('regenerateerror', 'local_casospracticos') . " ({$errors})");
    }
    redirect(new moodle_url('/local/casospracticos/pdf_library.php'));
}

// ============================================================
// Render page.
// ============================================================
echo $OUTPUT->header();

// Back button + Regenerate All button.
echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');

echo $OUTPUT->single_button(
    new moodle_url('/local/casospracticos/index.php'),
    get_string('back'),
    'get'
);

$regenerateallurl = new moodle_url('/local/casospracticos/pdf_library.php', [
    'action' => 'regenerateall',
    'sesskey' => sesskey(),
]);
echo html_writer::link(
    $regenerateallurl,
    '<i class="fa fa-refresh mr-1"></i>' . get_string('regenerateall', 'local_casospracticos'),
    ['class' => 'btn btn-primary', 'onclick' => 'this.classList.add("disabled"); this.innerHTML="<i class=\'fa fa-spinner fa-spin mr-1\'></i>' . get_string('regenerating', 'local_casospracticos') . '"; return true;']
);

echo html_writer::end_div();

// Load all categories with case and question counts.
$categories = $DB->get_records_sql("
    SELECT cat.id, cat.name,
           COUNT(DISTINCT c.id) AS casecount,
           COALESCE(SUM(qcounts.qcount), 0) AS questioncount,
           MAX(GREATEST(
               COALESCE(c.timecreated, 0),
               COALESCE(c.timemodified, 0),
               COALESCE(qcounts.newest_question_time, 0)
           )) AS newest_content_time
      FROM {local_cp_categories} cat
      LEFT JOIN {local_cp_cases} c
             ON c.categoryid = cat.id
            AND c.status = 'published'
      LEFT JOIN (
          SELECT q.caseid,
                 COUNT(*) AS qcount,
                 MAX(GREATEST(COALESCE(q.timecreated, 0), COALESCE(q.timemodified, 0))) AS newest_question_time
            FROM {local_cp_questions} q
        GROUP BY q.caseid
      ) qcounts ON qcounts.caseid = c.id
  GROUP BY cat.id, cat.name
  ORDER BY cat.name ASC
");

if (empty($categories)) {
    echo html_writer::tag('p', get_string('nocategories', 'local_casospracticos'), ['class' => 'text-muted']);
    echo $OUTPUT->footer();
    exit;
}

// Build the table.
$table = new html_table();
$table->head = [
    get_string('category', 'local_casospracticos'),
    get_string('cases', 'local_casospracticos'),
    get_string('questions', 'local_casospracticos'),
    get_string('pdfstatus', 'local_casospracticos'),
    get_string('pdfsize', 'local_casospracticos'),
    get_string('lastgenerated', 'local_casospracticos'),
    get_string('actions', 'local_casospracticos'),
];
$table->attributes['class'] = 'table table-striped table-hover';

foreach ($categories as $cat) {
    $filename = get_pdf_filename($cat->name);
    $filepath = PDF_OUTPUT_DIR . '/' . $filename;
    $pdfexists = file_exists($filepath);
    $pdfsize = $pdfexists ? filesize($filepath) : 0;
    $pdfmtime = $pdfexists ? filemtime($filepath) : 0;

    // Determine status.
    $newestcontenttime = (int) $cat->newest_content_time;
    $haspublishedcases = (int) $cat->casecount > 0;
    if (!$haspublishedcases) {
        $statushtml = html_writer::tag('span',
            '<i class="fa fa-ban mr-1"></i>' . get_string('nocases', 'local_casospracticos'),
            ['class' => 'badge bg-secondary text-white']
        );
    } else if (!$pdfexists) {
        $statushtml = html_writer::tag('span',
            '<i class="fa fa-times-circle mr-1"></i>' . get_string('pdfmissing', 'local_casospracticos'),
            ['class' => 'badge bg-danger text-white']
        );
    } else if ($newestcontenttime > $pdfmtime) {
        $statushtml = html_writer::tag('span',
            '<i class="fa fa-exclamation-triangle mr-1"></i>' . get_string('pdfoutdated', 'local_casospracticos'),
            ['class' => 'badge bg-warning text-dark']
        );
    } else {
        $statushtml = html_writer::tag('span',
            '<i class="fa fa-check-circle mr-1"></i>' . get_string('pdfgenerated', 'local_casospracticos'),
            ['class' => 'badge bg-success text-white']
        );
    }

    // File size.
    $sizehtml = $pdfexists ? display_size($pdfsize) : '-';

    // Last generated date.
    $datehtml = $pdfexists
        ? userdate($pdfmtime, get_string('strftimedatetime', 'langconfig'))
        : '-';

    // Action buttons.
    $actions = [];

    if ($pdfexists && $haspublishedcases) {
        // Download.
        $downloadurl = new moodle_url('/local/casospracticos/pdf_library.php', [
            'action' => 'download',
            'catid' => $cat->id,
            'sesskey' => sesskey(),
        ]);
        $actions[] = html_writer::link(
            $downloadurl,
            '<i class="fa fa-download mr-1"></i>' . get_string('downloadpdf', 'local_casospracticos'),
            ['class' => 'btn btn-sm btn-outline-primary me-1']
        );

        // Preview (opens in new tab).
        $actions[] = html_writer::link(
            $downloadurl,
            '<i class="fa fa-eye mr-1"></i>' . get_string('preview', 'local_casospracticos'),
            ['class' => 'btn btn-sm btn-outline-secondary me-1', 'target' => '_blank']
        );
    }

    // Regenerate.
    $regenerateurl = new moodle_url('/local/casospracticos/pdf_library.php', [
        'action' => 'regenerate',
        'catid' => $cat->id,
        'sesskey' => sesskey(),
    ]);
    $actions[] = html_writer::link(
        $regenerateurl,
        '<i class="fa fa-refresh mr-1"></i>' . get_string('regenerate', 'local_casospracticos'),
        ['class' => 'btn btn-sm btn-outline-warning me-1', 'onclick' => 'this.classList.add("disabled"); this.innerHTML="<i class=\'fa fa-spinner fa-spin\'></i>"; return true;']
    );

    // Category link.
    $caturl = new moodle_url('/local/casospracticos/index.php', ['category' => $cat->id]);
    $catlink = html_writer::link($caturl, format_string($cat->name));

    $table->data[] = [
        $catlink,
        (int) $cat->casecount,
        (int) $cat->questioncount,
        $statushtml,
        $sizehtml,
        $datehtml,
        implode('', $actions),
    ];
}

echo html_writer::table($table);

// Summary statistics.
$totalpdfs = 0;
$totalsize = 0;
$missing = 0;
$outdated = 0;
foreach ($categories as $cat) {
    $filename = get_pdf_filename($cat->name);
    $filepath = PDF_OUTPUT_DIR . '/' . $filename;
    $haspublishedcases = (int) $cat->casecount > 0;
    if (file_exists($filepath)) {
        $totalpdfs++;
        $totalsize += filesize($filepath);
        if (!$haspublishedcases || (int) $cat->newest_content_time > filemtime($filepath)) {
            $outdated++;
        }
    } else {
        if ($haspublishedcases) {
            $missing++;
        }
    }
}

echo html_writer::start_div('card mt-3');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('statistics', 'local_casospracticos'), ['class' => 'card-title']);
echo html_writer::start_tag('ul', ['class' => 'list-unstyled mb-0']);
echo html_writer::tag('li', '<strong>PDFs:</strong> ' . $totalpdfs . ' / ' . count($categories));
echo html_writer::tag('li', '<strong>' . get_string('pdfsize', 'local_casospracticos') . ':</strong> ' . display_size($totalsize));
if ($missing > 0) {
    echo html_writer::tag('li', '<span class="text-danger"><i class="fa fa-times-circle mr-1"></i>' .
        get_string('pdfmissing', 'local_casospracticos') . ': ' . $missing . '</span>');
}
if ($outdated > 0) {
    echo html_writer::tag('li', '<span class="text-warning"><i class="fa fa-exclamation-triangle mr-1"></i>' .
        get_string('pdfoutdated', 'local_casospracticos') . ': ' . $outdated . '</span>');
}
echo html_writer::end_tag('ul');
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();


// ============================================================
// Helper functions.
// ============================================================

/**
 * Generate a filesystem-safe filename from a category name.
 * Must match the logic in generate_pdf.php generate_safe_filename().
 *
 * @param string $catname The category name.
 * @return string The PDF filename.
 */
function get_pdf_filename(string $catname): string {
    $map = [
        "\xC3\xA1" => 'a', "\xC3\xA9" => 'e', "\xC3\xAD" => 'i', "\xC3\xB3" => 'o', "\xC3\xBA" => 'u',
        "\xC3\x81" => 'A', "\xC3\x89" => 'E', "\xC3\x8D" => 'I', "\xC3\x93" => 'O', "\xC3\x9A" => 'U',
        "\xC3\xB1" => 'n', "\xC3\x91" => 'N', "\xC3\xBC" => 'u', "\xC3\x9C" => 'U',
    ];
    $safe = strtr($catname, $map);
    $safe = preg_replace('/[^a-zA-Z0-9]+/', '_', $safe);
    $safe = trim($safe, '_');
    $safe = strtolower($safe);
    if (strlen($safe) > 60) {
        $safe = substr($safe, 0, 60);
    }
    return 'casos_practicos_' . $safe . '.pdf';
}

/**
 * Regenerate PDF for a single category by invoking the generator script.
 *
 * The generator script is a CLI tool that accepts a --category parameter.
 * All arguments are properly escaped with escapeshellarg() to prevent injection.
 *
 * @param int $catid Category ID.
 * @return array ['success' => bool, 'error' => string]
 */
function regenerate_pdf(int $catid): array {
    $cmd = sprintf(
        '%s %s --category=%d 2>&1',
        escapeshellarg(PHP_BIN),
        escapeshellarg(PDF_GENERATOR_SCRIPT),
        $catid
    );

    $output = [];
    $retval = 0;
    exec($cmd, $output, $retval);

    if ($retval !== 0) {
        return [
            'success' => false,
            'error' => implode("\n", $output),
        ];
    }

    return [
        'success' => true,
        'error' => '',
    ];
}
