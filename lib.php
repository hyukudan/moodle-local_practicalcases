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
        'essay' => get_string('qtype_essay', 'local_casospracticos'),
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

    // Serve attachments to any user with at least statement-level access
    // (paywall enrolment in the product course), mirroring case_view.php.
    // Editorial/admin keep access via the FULL branch of get_view_access().
    if (local_casospracticos_get_view_access() < LOCAL_CP_ACCESS_STATEMENT) {
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

        // Validate case exists AND is still visible to this user. Checking mere
        // existence meant archiving a case pulled it from every page while its
        // attachments stayed downloadable by direct URL.
        $case = \local_casospracticos\case_manager::get((int)$itemid);
        if (!$case || !\local_casospracticos\case_manager::is_visible_to_user($case, $context)) {
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
    if (!$dbman->table_exists('local_cp_question_normativa')
            || !$dbman->table_exists('local_normativa_articulos')
            || !$dbman->table_exists('local_normativa_fuentes')) {
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
        // Keep every article explicit. Turning four or more links into a visual
        // range is misleading when the stored articles are not contiguous.
        foreach ($articles as $link) {
            $item = (array) $link;
            $item['questionid'] = $questionid;
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

/**
 * Render the canonical solution and its linked legislation.
 *
 * @param object $question Practical-case question.
 * @param array $normativa Normativa links preloaded for this question.
 * @return string HTML
 */
function local_casospracticos_render_solution_feedback(object $question, array $normativa = []): string {
    global $OUTPUT;

    $data = \local_casospracticos\feedback_view_builder::build($question);
    $html = $OUTPUT->render_from_template('local_casospracticos/solution_feedback', $data);
    if (!empty($normativa)) {
        $html .= local_casospracticos_render_normativa_panel((int) $question->id, $normativa);
    }
    return $html;
}

// Access levels for the central practical-case bank.
// NONE: authenticated but no entitlement. STATEMENT: trial (enunciado only, no solution).
// FULL: paying student / editorial / admin (statement + solution + practice).
define('LOCAL_CP_ACCESS_NONE', 0);
define('LOCAL_CP_ACCESS_STATEMENT', 10);
define('LOCAL_CP_ACCESS_FULL', 20);

// Upper bound for the per-case deliverable maxfiles setting (student uploads
// per submission). Sensible ceiling so a typo can't request thousands of files.
define('LOCAL_CP_DELIVERABLE_MAXFILES_CAP', 10);

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
        // Fail closed. Granting FULL here handed the entire product — statements,
        // answer keys and canonical solutions — to every authenticated user the
        // moment a config typo or a deleted course made the product course
        // unresolvable, and did it silently. Editorial and site admins are already
        // let through above, so they keep access and can repair the setting.
        local_casospracticos_alert_missing_product_course($courseid);
        return LOCAL_CP_ACCESS_NONE;
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
 * Shout when the product course cannot be resolved.
 *
 * This is a configuration emergency: with the course missing, nobody but
 * editorial can reach the bank at all. Silence is what let the previous
 * fail-open behaviour go unnoticed, so this always leaves a trace — throttled to
 * once an hour so a broken setting cannot flood the log on every page view.
 *
 * @param int $courseid The unresolvable product course id.
 */
function local_casospracticos_alert_missing_product_course(int $courseid): void {
    $now = time();
    try {
        $last = (int) get_config('local_casospracticos', 'missingcoursealert');
    } catch (\Throwable $e) {
        $last = 0; // Database unhappy too: shout anyway, that is the whole point.
    }
    if ($last && ($now - $last) < HOURSECS) {
        return;
    }
    // Log BEFORE touching the database: if set_config() throws, losing the alert
    // is the worst possible outcome. Two racing requests may log twice; that is
    // cheaper than a silent configuration emergency.
    error_log('local_casospracticos: EL CURSO PRODUCTO ' . $courseid . ' NO EXISTE. '
        . 'El banco de casos queda cerrado para todos los alumnos hasta que se corrija '
        . 'el ajuste productcourseid del plugin.');
    try {
        set_config('missingcoursealert', $now, 'local_casospracticos');
    } catch (\Throwable $e) {
        // Throttling is a nicety; never let it break the access decision.
        return;
    }
}

/**
 * May this user see answer keys, per-answer feedback and the canonical solution?
 *
 * Single home for the rule. It used to live duplicated in case_view.php and in
 * external\api::can_view_answer_keys(), and the two drifted: both required the
 * editorial capability local/casospracticos:viewanswers, which no student ever
 * holds, so a paying student opening a link labelled "Ver caso con solución"
 * got the questions with the solution stripped out. Entitlement to the product
 * (LOCAL_CP_ACCESS_FULL) is what buys the solution.
 *
 * The :viewanswers capability is kept only for backwards compatibility: it no
 * longer decides anything a learner sees, and it never gated drafts either —
 * that is can_view_unpublished(), which tests :edit.
 *
 * @param context $context Context to test the editorial capabilities in.
 * @param int|null $viewaccess Pre-computed access level, to save a second lookup.
 * @return bool
 */
function local_casospracticos_can_view_answers(context $context, ?int $viewaccess = null): bool {
    $viewaccess = $viewaccess ?? local_casospracticos_get_view_access();
    if ($viewaccess >= LOCAL_CP_ACCESS_FULL) {
        return true;
    }
    return has_capability('local/casospracticos:viewanswers', $context)
        || \local_casospracticos\case_manager::can_view_unpublished($context);
}

/**
 * May this user see questions whose feedback is flagged 'blocked'?
 *
 * Blocked questions are ones editorial has pulled from circulation. Only people
 * who can edit or review them have any business receiving them. Kept here rather
 * than inline so the web view and the external API cannot drift apart — the web
 * filtered them out and the API did not, which handed learners questions the
 * page had deliberately removed.
 *
 * @param context $context
 * @return bool
 */
function local_casospracticos_can_see_blocked_questions(context $context): bool {
    return has_capability('local/casospracticos:edit', $context)
        || has_capability('local/casospracticos:review', $context);
}

/**
 * Root URL of the case bank for the current user.
 *
 * index.php is the back-office catalogue (pagelayout 'admin', CRUD actions) and
 * deliberately keeps its system-level capability gate. Student-facing pages must
 * therefore never hardcode it in a breadcrumb or a back button: doing so put a
 * "No tiene permiso para esta acción" one click away from every case. The
 * student's canonical root is the real bank.
 *
 * The decision is always taken against the system context, never against a
 * caller-supplied one: index.php gates on the capability at system context, so
 * testing anywhere else could hand back a link its own target would reject.
 *
 * @param array $backofficeparams Extra params, honoured only for the back-office URL.
 * @return moodle_url
 */
function local_casospracticos_get_root_url(array $backofficeparams = []): moodle_url {
    if (has_capability('local/casospracticos:view', context_system::instance())) {
        // Back-office only: category filters etc. are meaningless in the student bank.
        return new moodle_url('/local/casospracticos/index.php', $backofficeparams);
    }
    return new moodle_url('/local/casospracticos/real_bank.php');
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
    $body .= html_writer::link($buyurl,
        $OUTPUT->pix_icon('t/unlock', '', 'moodle', ['aria-hidden' => 'true']) . ' '
            . get_string('cta:unlock', 'local_casospracticos'),
        ['class' => 'btn btn-primary']);
    return html_writer::div($body, 'alert alert-warning cp-upgrade-cta mt-4');
}
