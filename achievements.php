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
 * User achievements page.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_casospracticos\achievements_manager;

$context = context_system::instance();
require_login();
require_capability('local/casospracticos:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/casospracticos/achievements.php'));
$PAGE->set_title(get_string('achievements', 'local_casospracticos'));
$PAGE->set_heading(get_string('pluginname', 'local_casospracticos'));
$PAGE->set_pagelayout('standard');

$PAGE->navbar->add(get_string('pluginname', 'local_casospracticos'),
    new moodle_url('/local/casospracticos/index.php'));
$PAGE->navbar->add(get_string('achievements', 'local_casospracticos'));

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('achievements', 'local_casospracticos'));

// Check if gamification is enabled.
if (!achievements_manager::is_enabled()) {
    echo html_writer::div(get_string('gamificationdisabled', 'local_casospracticos'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

// Get user stats.
$stats = achievements_manager::get_user_stats($USER->id);

// Stats summary cards.
echo html_writer::start_div('row mb-4');

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center bg-primary text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->total_attempts, ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('totalattempts', 'local_casospracticos'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center bg-success text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->unique_cases, ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('uniquecases', 'local_casospracticos'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center bg-warning');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', $stats->perfect_scores, ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('perfectscores', 'local_casospracticos'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('col-md-3');
echo html_writer::start_div('card text-center bg-info text-white');
echo html_writer::start_div('card-body');
echo html_writer::tag('h3', round($stats->average_score, 1) . '%', ['class' => 'mb-0']);
echo html_writer::tag('small', get_string('averagescore', 'local_casospracticos'));
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

// Get all achievements with progress.
$achievements = achievements_manager::get_achievements_with_progress($USER->id);

// Count earned.
$earnedcount = 0;
foreach ($achievements as $a) {
    if ($a->earned) {
        $earnedcount++;
    }
}

echo html_writer::tag('h4',
    get_string('achievementsprogress', 'local_casospracticos', [
        'earned' => $earnedcount,
        'total' => count($achievements),
    ]),
    ['class' => 'mb-3']
);

// Display achievements grid.
echo html_writer::start_div('row');

foreach ($achievements as $achievement) {
    echo html_writer::start_div('col-md-4 col-lg-3 mb-4');

    $cardclass = 'card h-100 achievement-card';
    if ($achievement->earned) {
        $cardclass .= ' border-success';
    } else {
        $cardclass .= ' border-secondary opacity-75';
    }

    echo html_writer::start_div($cardclass);
    echo html_writer::start_div('card-body text-center');

    // Icon.
    $iconclass = 'fa ' . $achievement->icon . ' fa-3x mb-3';
    if ($achievement->earned) {
        $iconclass .= ' text-success';
    } else {
        $iconclass .= ' text-muted';
    }
    echo html_writer::tag('i', '', ['class' => $iconclass]);

    // Name.
    echo html_writer::tag('h5', $achievement->name, ['class' => 'card-title']);

    // Description.
    echo html_writer::tag('p', $achievement->description, ['class' => 'card-text small text-muted']);

    // Progress bar.
    if (!$achievement->earned) {
        echo html_writer::start_div('progress mt-2');
        echo html_writer::div('', 'progress-bar', [
            'style' => 'width: ' . $achievement->progress->percentage . '%',
            'role' => 'progressbar',
            'aria-valuenow' => $achievement->progress->percentage,
            'aria-valuemin' => '0',
            'aria-valuemax' => '100',
        ]);
        echo html_writer::end_div();
        echo html_writer::tag('small',
            $achievement->progress->current . ' / ' . $achievement->progress->target,
            ['class' => 'text-muted']
        );
    } else {
        echo html_writer::tag('span', get_string('earned', 'local_casospracticos'),
            ['class' => 'badge bg-success']);
    }

    echo html_writer::end_div(); // card-body
    echo html_writer::end_div(); // card
    echo html_writer::end_div(); // col
}

echo html_writer::end_div(); // row

// External plugin info.
if (achievements_manager::has_external_plugin()) {
    $plugin = achievements_manager::get_external_plugin_name();
    echo html_writer::div(
        get_string('externalintegration', 'local_casospracticos', $plugin),
        'alert alert-info mt-4'
    );
}

// Back button.
echo html_writer::start_div('mt-4');
echo html_writer::link(
    new moodle_url('/local/casospracticos/index.php'),
    get_string('backtocases', 'local_casospracticos'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();

echo $OUTPUT->footer();
