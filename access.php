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
 * Purchase / access CTA shown when a logged-in user is not entitled to the
 * central practical-case bank.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/casospracticos/lib.php');

require_login();

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/access.php'));
$PAGE->set_title(get_string('access:pagetitle', 'local_casospracticos'));
$PAGE->set_heading(get_string('access:pagetitle', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

// If they already have access, don't dead-end them here.
$access = local_casospracticos_get_view_access();
if ($access >= LOCAL_CP_ACCESS_STATEMENT) {
    redirect($returnurl ? new moodle_url($returnurl)
        : new moodle_url('/local/casospracticos/real_bank.php'));
}

$courseid = local_casospracticos_get_product_courseid();
$totalcases = $DB->count_records('local_cp_cases', ['status' => 'published']);
$buyurl = new moodle_url('/enrol/index.php', ['id' => $courseid]);

echo $OUTPUT->header();

echo html_writer::start_div('cp-access-cta card mx-auto');
echo html_writer::start_div('card-body text-center p-5');
echo html_writer::tag('div',
    $OUTPUT->pix_icon('i/lock', '', 'moodle', ['class' => 'fa-3x text-warning']),
    ['class' => 'mb-3']);
echo $OUTPUT->heading(get_string('access:required_title', 'local_casospracticos'), 3);
echo html_writer::tag('p',
    get_string('access:required_body', 'local_casospracticos', $totalcases),
    ['class' => 'lead']);
echo html_writer::link($buyurl,
    $OUTPUT->pix_icon('i/lock', '', 'moodle', ['aria-hidden' => 'true']) . ' '
        . get_string('access:goto', 'local_casospracticos'),
    ['class' => 'btn btn-primary btn-lg']);
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
