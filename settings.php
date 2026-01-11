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
 * Settings for local_casospracticos.
 *
 * @package    local_casospracticos
 * @copyright  2026 Sergio C.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create settings page.
    $settings = new admin_settingpage('local_casospracticos', get_string('pluginname', 'local_casospracticos'));

    // Add link to manage cases.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/manageheading',
        get_string('managecases', 'local_casospracticos'),
        html_writer::link(
            new moodle_url('/local/casospracticos/index.php'),
            get_string('managecases', 'local_casospracticos'),
            ['class' => 'btn btn-primary']
        )
    ));

    // Add settings page to admin tree.
    $ADMIN->add('localplugins', $settings);
}
