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
 * Library functions for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation nodes to the navigation tree.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course object
 * @param context $context The context
 */
function local_casospracticos_extend_navigation(global_navigation $navigation) {
    global $CFG, $PAGE;

    if (!has_capability('local/casospracticos:view', context_system::instance())) {
        return;
    }

    $node = $navigation->add(
        get_string('pluginname', 'local_casospracticos'),
        new moodle_url('/local/casospracticos/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'casospracticos',
        new pix_icon('i/folder', '')
    );
}

/**
 * Add settings to the admin tree.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param context $context The context
 */
function local_casospracticos_extend_settings_navigation(settings_navigation $navigation, context $context) {
    // Future: Add settings navigation if needed.
}

/**
 * Get supported question types for practical cases.
 *
 * @return array Array of supported question types.
 */
function local_casospracticos_get_supported_qtypes(): array {
    return [
        'multichoice' => get_string('qtype_multichoice', 'local_casospracticos'),
        'truefalse' => get_string('qtype_truefalse', 'local_casospracticos'),
        'shortanswer' => get_string('qtype_shortanswer', 'local_casospracticos'),
    ];
}

/**
 * Get case status options.
 *
 * @return array Array of status options.
 */
function local_casospracticos_get_status_options(): array {
    return [
        'draft' => get_string('status_draft', 'local_casospracticos'),
        'published' => get_string('status_published', 'local_casospracticos'),
        'archived' => get_string('status_archived', 'local_casospracticos'),
    ];
}

/**
 * Serves plugin files.
 *
 * @param stdClass $course Course object
 * @param stdClass $cm Course module object
 * @param context $context Context object
 * @param string $filearea File area
 * @param array $args Extra arguments
 * @param bool $forcedownload Force download
 * @param array $options Additional options
 * @return bool False if file not found
 */
function local_casospracticos_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();

    if (!has_capability('local/casospracticos:view', $context)) {
        return false;
    }

    // Validate allowed file areas.
    $allowedfileareas = ['case_attachments', 'statement', 'deliverable'];
    if (!in_array($filearea, $allowedfileareas)) {
        return false;
    }

    $fs = get_file_storage();

    // For case_attachments and deliverable, the first arg is the case ID (itemid).
    if ($filearea === 'case_attachments' || $filearea === 'deliverable') {
        $itemid = array_shift($args);
        $relativepath = implode('/', $args);

        // Validate case exists.
        $case = \local_casospracticos\case_manager::get((int)$itemid);
        if (!$case) {
            return false;
        }

        // Security: Prevent path traversal attacks.
        if (strpos($relativepath, '..') !== false) {
            return false;
        }

        $fullpath = "/{$context->id}/local_casospracticos/{$filearea}/{$itemid}/{$relativepath}";
    } else {
        $relativepath = implode('/', $args);

        // Security: Prevent path traversal attacks.
        if (strpos($relativepath, '..') !== false) {
            return false;
        }

        $fullpath = "/{$context->id}/local_casospracticos/{$filearea}/{$relativepath}";
    }

    $file = $fs->get_file_by_hash(sha1($fullpath));

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Get file options for case attachments filearea.
 *
 * @return array File options for the filepicker.
 */
function local_casospracticos_get_attachment_options(): array {
    return [
        'subdirs' => 0,
        'maxbytes' => 10485760, // 10MB max per file.
        'maxfiles' => 10,       // Up to 10 attachments per case.
        'accepted_types' => [
            // Documents.
            '.doc', '.docx', '.odt', '.rtf',
            // Spreadsheets.
            '.xls', '.xlsx', '.ods', '.csv',
            // Presentations.
            '.ppt', '.pptx', '.odp',
            // PDFs.
            '.pdf',
            // Images.
            '.jpg', '.jpeg', '.png', '.gif', '.svg',
            // Archives (for resource bundles).
            '.zip',
        ],
        'context' => context_system::instance(),
    ];
}

/**
 * Get the file type icon class for a given filename.
 *
 * @param string $filename The filename.
 * @return array Array with 'icon' (Font Awesome class) and 'type' (human readable type).
 */
function local_casospracticos_get_file_icon(string $filename): array {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $types = [
        // Word documents.
        'doc' => ['icon' => 'fa-file-word text-primary', 'type' => 'Word'],
        'docx' => ['icon' => 'fa-file-word text-primary', 'type' => 'Word'],
        'odt' => ['icon' => 'fa-file-word text-primary', 'type' => 'Document'],
        'rtf' => ['icon' => 'fa-file-word text-primary', 'type' => 'Document'],
        // Excel/Spreadsheets.
        'xls' => ['icon' => 'fa-file-excel text-success', 'type' => 'Excel'],
        'xlsx' => ['icon' => 'fa-file-excel text-success', 'type' => 'Excel'],
        'ods' => ['icon' => 'fa-file-excel text-success', 'type' => 'Spreadsheet'],
        'csv' => ['icon' => 'fa-file-csv text-success', 'type' => 'CSV'],
        // PowerPoint/Presentations.
        'ppt' => ['icon' => 'fa-file-powerpoint text-danger', 'type' => 'PowerPoint'],
        'pptx' => ['icon' => 'fa-file-powerpoint text-danger', 'type' => 'PowerPoint'],
        'odp' => ['icon' => 'fa-file-powerpoint text-danger', 'type' => 'Presentation'],
        // PDF.
        'pdf' => ['icon' => 'fa-file-pdf text-danger', 'type' => 'PDF'],
        // Images.
        'jpg' => ['icon' => 'fa-file-image text-info', 'type' => 'Image'],
        'jpeg' => ['icon' => 'fa-file-image text-info', 'type' => 'Image'],
        'png' => ['icon' => 'fa-file-image text-info', 'type' => 'Image'],
        'gif' => ['icon' => 'fa-file-image text-info', 'type' => 'Image'],
        'svg' => ['icon' => 'fa-file-image text-info', 'type' => 'Image'],
        // Archives.
        'zip' => ['icon' => 'fa-file-archive text-warning', 'type' => 'ZIP'],
    ];

    return $types[$extension] ?? ['icon' => 'fa-file text-secondary', 'type' => 'File'];
}

/**
 * Get the optional document deliverable definition for a case.
 *
 * Returns the {local_cp_case_deliverable} row only when it exists AND is
 * enabled. Cases with no row, or a disabled row, return false — callers must
 * treat that as "no deliverable" and render nothing new (default-off).
 *
 * @param int $caseid Case ID.
 * @return \stdClass|false Enabled deliverable row, or false.
 */
function local_casospracticos_get_case_deliverable(int $caseid) {
    global $DB;

    // Defensive: if the table is not yet present (mid-upgrade), behave as none.
    if (!$DB->get_manager()->table_exists('local_cp_case_deliverable')) {
        return false;
    }

    $row = $DB->get_record('local_cp_case_deliverable', ['caseid' => $caseid]);
    if (!$row || empty($row->enabled)) {
        return false;
    }

    return $row;
}

/**
 * Get a moodle_url to download the start file of a case's deliverable.
 *
 * @param int $caseid Case ID.
 * @param string $filename Stored start filename.
 * @return \moodle_url URL served by local_casospracticos_pluginfile (deliverable area).
 */
function local_casospracticos_deliverable_startfile_url(int $caseid, string $filename): \moodle_url {
    $systemcontext = context_system::instance();
    return \moodle_url::make_pluginfile_url(
        $systemcontext->id,
        'local_casospracticos',
        'deliverable',
        $caseid,
        '/',
        $filename,
        true
    );
}

/**
 * Load normativa article links for a batch of CP question IDs.
 *
 * @param array $questionids Array of local_cp_questions IDs
 * @return array Associative array [questionid => [link objects]]
 */
function local_casospracticos_get_normativa_links(array $questionids): array {
    global $DB;

    if (empty($questionids)) {
        return [];
    }

    // Check that the normativa plugin tables exist.
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_cp_question_normativa')) {
        return [];
    }

    list($insql, $params) = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

    $sql = "SELECT qn.id, qn.questionid,
                   na.id AS articuloid, na.numero_articulo, na.titulo AS articulo_titulo,
                   nf.titulo_corto AS fuente_titulo, nf.url_boe
              FROM {local_cp_question_normativa} qn
              JOIN {local_normativa_articulos} na ON na.id = qn.articulo_id
              JOIN {local_normativa_fuentes} nf ON nf.id = na.fuente_id
             WHERE qn.questionid $insql
          ORDER BY qn.questionid, nf.titulo_corto, na.numero_articulo";

    $links = $DB->get_records_sql($sql, $params);

    $result = [];
    foreach ($links as $link) {
        $result[$link->questionid][] = $link;
    }

    return $result;
}

/**
 * Render the normativa panel HTML for a CP question.
 *
 * @param int $questionid CP question ID
 * @param array $links Array of link objects from local_casospracticos_get_normativa_links
 * @return string HTML
 */
function local_casospracticos_render_normativa_panel(int $questionid, array $links): string {
    global $OUTPUT, $PAGE;

    if (empty($links)) {
        return '';
    }

    // Load article modal JS (once per page).
    static $jsinited = false;
    if (!$jsinited) {
        $PAGE->requires->js_call_amd('local_normativa/article_modal', 'init');
        $jsinited = true;
    }

    // Group links by fuente.
    $byfuente = [];
    foreach ($links as $link) {
        $key = $link->fuente_titulo;
        if (!isset($byfuente[$key])) {
            $byfuente[$key] = [
                'fuente_titulo' => $link->fuente_titulo,
                'url_boe' => $link->url_boe ?? '',
                'articles' => [],
            ];
        }
        $byfuente[$key]['articles'][] = $link;
    }

    // Build template data.
    $templatelinks = [];
    $first = true;
    foreach ($byfuente as $group) {
        $articles = $group['articles'];
        $count = count($articles);

        if ($count <= 3) {
            foreach ($articles as $link) {
                $item = (array)$link;
                $item['questionid'] = $questionid;
                if ($first) {
                    $item['first'] = true;
                    $first = false;
                }
                $templatelinks[] = $item;
            }
        } else {
            // Compact range.
            $nums = array_map(function($a) { return $a->numero_articulo; }, $articles);
            $ids = array_map(function($a) { return (int)$a->articuloid; }, $articles);
            $firstlink = reset($articles);

            $item = (array)$firstlink;
            $item['questionid'] = $questionid;
            $item['numero_articulo'] = reset($nums) . '-' . end($nums);
            $item['articulo_titulo'] = $count . ' artículos';
            $item['is_range'] = true;
            $item['articuloids_json'] = json_encode($ids);
            if ($first) {
                $item['first'] = true;
                $first = false;
            }
            $templatelinks[] = $item;
        }
    }

    $data = [
        'links' => $templatelinks,
        'questionid' => $questionid,
    ];

    // Reuse normativa plugin's template.
    return $OUTPUT->render_from_template('local_normativa/question_normativa_panel', $data);
}

// Access levels for the central practical-case bank.
// NONE: authenticated but no entitlement. STATEMENT: trial (enunciado only, no solution).
// FULL: paying student / editorial / admin (statement + solution + practice).
define('LOCAL_CP_ACCESS_NONE', 0);
define('LOCAL_CP_ACCESS_STATEMENT', 10);
define('LOCAL_CP_ACCESS_FULL', 20);

/**
 * Course id whose enrolment gates the central case bank (the paid product).
 *
 * @return int
 */
function local_casospracticos_get_product_courseid(): int {
    $courseid = (int) get_config('local_casospracticos', 'productcourseid');
    return $courseid > 0 ? $courseid : 103;
}

/**
 * Resolve the effective access level of a user to the central case bank.
 *
 * Entitlement is driven by *active* enrolment in the product course, not by a
 * system-level capability (students only ever hold the student role at course
 * level, so a system check blocks everyone — the historical bug). Editorial/admin
 * keep a system-level bypass via local/casospracticos:view (no student holds it
 * at system). Trial (self-enrol with customint6=1) maps to a configurable level
 * (default: statement only); any paid/manual enrolment always wins (FULL).
 *
 * @param int|null $userid Defaults to current user.
 * @return int One of LOCAL_CP_ACCESS_*.
 */
function local_casospracticos_get_view_access(?int $userid = null): int {
    global $USER, $DB;

    $userid = $userid ?? (int) $USER->id;
    if ($userid <= 0 || isguestuser($userid)) {
        return LOCAL_CP_ACCESS_NONE;
    }

    // Editorial / admin bypass at system context (no ordinary student has this).
    $syscontext = context_system::instance();
    if (is_siteadmin($userid)
            || has_capability('local/casospracticos:view', $syscontext, $userid)) {
        return LOCAL_CP_ACCESS_FULL;
    }

    $courseid = local_casospracticos_get_product_courseid();
    try {
        $coursecontext = context_course::instance($courseid, MUST_EXIST);
    } catch (\moodle_exception $e) {
        // Product course misconfigured/missing: grant FULL to logged-in users
        // rather than mass-lock paying students (never repeat the original bug).
        return LOCAL_CP_ACCESS_FULL;
    }

    // Active, capability-bearing enrolment = entitlement to the bank at all.
    if (!is_enrolled($coursecontext, $userid, 'local/casospracticos:view', true)) {
        return LOCAL_CP_ACCESS_NONE;
    }

    // Classify the active enrolment(s): paid/manual beats trial.
    $now = time();
    $sql = "SELECT ue.id, e.enrol, e.customint6, e.status AS estatus,
                   ue.status AS uestatus, ue.timestart, ue.timeend
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid AND e.courseid = :courseid";
    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

    $haspaid = false;
    $hastrial = false;
    $hasother = false;
    foreach ($rows as $row) {
        if ((int) $row->estatus !== 0 || (int) $row->uestatus !== 0) {
            continue; // Suspended enrol instance or user enrolment.
        }
        if (!empty($row->timestart) && $row->timestart > $now) {
            continue;
        }
        if (!empty($row->timeend) && $row->timeend < $now) {
            continue;
        }
        if ($row->enrol === 'self' && (int) $row->customint6 === 1) {
            $hastrial = true;
        } else if ($row->enrol === 'buynow' || $row->enrol === 'manual') {
            $haspaid = true;
        } else {
            $hasother = true;
        }
    }

    if ($haspaid || $hasother) {
        return LOCAL_CP_ACCESS_FULL;
    }
    if ($hastrial) {
        $trial = get_config('local_casospracticos', 'trialaccess');
        if ($trial === 'full') {
            return LOCAL_CP_ACCESS_FULL;
        }
        if ($trial === 'none') {
            return LOCAL_CP_ACCESS_NONE;
        }
        return LOCAL_CP_ACCESS_STATEMENT; // Default policy: enunciado only.
    }

    // Enrolled and active per is_enrolled() but unclassified: do not lock out.
    return LOCAL_CP_ACCESS_FULL;
}

/**
 * Require at least $minlevel of access; otherwise redirect to the purchase CTA.
 *
 * @param int $minlevel Minimum LOCAL_CP_ACCESS_* required.
 * @param moodle_url|null $returnurl Where to come back after purchasing.
 * @return int The user's actual access level (>= $minlevel).
 */
function local_casospracticos_require_view_access(int $minlevel = LOCAL_CP_ACCESS_STATEMENT,
        ?moodle_url $returnurl = null): int {
    global $PAGE;

    require_login();

    $access = local_casospracticos_get_view_access();
    if ($access >= $minlevel) {
        return $access;
    }

    $returnurl = $returnurl ?? $PAGE->url;
    $ctaurl = new moodle_url('/local/casospracticos/access.php', [
        'returnurl' => $returnurl ? $returnurl->out_as_local_url(false) : '',
    ]);
    redirect($ctaurl);
}

/**
 * CTA banner shown to trial users on the statement-only view.
 *
 * @return string HTML
 */
function local_casospracticos_render_upgrade_cta(): string {
    global $OUTPUT;

    $courseid = local_casospracticos_get_product_courseid();
    $buyurl = new moodle_url('/enrol/index.php', ['id' => $courseid]);
    $body = html_writer::tag('h5', get_string('cta:solutionlocked_title', 'local_casospracticos'));
    $body .= html_writer::tag('p', get_string('cta:solutionlocked_body', 'local_casospracticos'));
    $body .= $OUTPUT->single_button($buyurl, get_string('cta:unlock', 'local_casospracticos'), 'get',
        ['class' => 'btn-primary']);
    return html_writer::div($body, 'alert alert-warning cp-upgrade-cta mt-4');
}
