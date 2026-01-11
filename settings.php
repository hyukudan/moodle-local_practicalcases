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

    // Add to admin tree.
    $ADMIN->add('localplugins', $settings);

    // General settings header.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/generalheader',
        get_string('settings:general', 'local_casospracticos'),
        ''
    ));

    // Enable quiz integration.
    $settings->add(new admin_setting_configcheckbox(
        'local_casospracticos/enablequizintegration',
        get_string('settings:enablequizintegration', 'local_casospracticos'),
        get_string('settings:enablequizintegration_desc', 'local_casospracticos'),
        1
    ));

    // Enable search indexing.
    $settings->add(new admin_setting_configcheckbox(
        'local_casospracticos/enablesearch',
        get_string('settings:enablesearch', 'local_casospracticos'),
        get_string('settings:enablesearch_desc', 'local_casospracticos'),
        1
    ));

    // Default difficulty level.
    $difficulties = [
        1 => get_string('difficulty1', 'local_casospracticos'),
        2 => get_string('difficulty2', 'local_casospracticos'),
        3 => get_string('difficulty3', 'local_casospracticos'),
        4 => get_string('difficulty4', 'local_casospracticos'),
        5 => get_string('difficulty5', 'local_casospracticos'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_casospracticos/defaultdifficulty',
        get_string('settings:defaultdifficulty', 'local_casospracticos'),
        get_string('settings:defaultdifficulty_desc', 'local_casospracticos'),
        3,
        $difficulties
    ));

    // Import/Export settings header.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/importexportheader',
        get_string('settings:importexport', 'local_casospracticos'),
        ''
    ));

    // Maximum import file size.
    $settings->add(new admin_setting_configtext(
        'local_casospracticos/maximportsize',
        get_string('settings:maximportsize', 'local_casospracticos'),
        get_string('settings:maximportsize_desc', 'local_casospracticos'),
        '10485760', // 10MB default.
        PARAM_INT
    ));

    // Default export format.
    $formats = [
        'xml' => 'XML',
        'json' => 'JSON',
    ];
    $settings->add(new admin_setting_configselect(
        'local_casospracticos/defaultexportformat',
        get_string('settings:defaultexportformat', 'local_casospracticos'),
        get_string('settings:defaultexportformat_desc', 'local_casospracticos'),
        'xml',
        $formats
    ));

    // Question types header.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/qtypesheader',
        get_string('settings:questiontypes', 'local_casospracticos'),
        get_string('settings:questiontypes_desc', 'local_casospracticos')
    ));

    // Allowed question types.
    $qtypes = [
        'multichoice' => get_string('qtype_multichoice', 'local_casospracticos'),
        'truefalse' => get_string('qtype_truefalse', 'local_casospracticos'),
        'shortanswer' => get_string('qtype_shortanswer', 'local_casospracticos'),
        'matching' => get_string('qtype_matching', 'local_casospracticos'),
    ];
    $settings->add(new admin_setting_configmulticheckbox(
        'local_casospracticos/allowedqtypes',
        get_string('settings:allowedqtypes', 'local_casospracticos'),
        get_string('settings:allowedqtypes_desc', 'local_casospracticos'),
        ['multichoice' => 1, 'truefalse' => 1, 'shortanswer' => 1],
        $qtypes
    ));

    // Display settings header.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/displayheader',
        get_string('settings:display', 'local_casospracticos'),
        ''
    ));

    // Cases per page.
    $settings->add(new admin_setting_configtext(
        'local_casospracticos/casesperpage',
        get_string('settings:casesperpage', 'local_casospracticos'),
        get_string('settings:casesperpage_desc', 'local_casospracticos'),
        25,
        PARAM_INT
    ));

    // Show question count in list.
    $settings->add(new admin_setting_configcheckbox(
        'local_casospracticos/showquestioncount',
        get_string('settings:showquestioncount', 'local_casospracticos'),
        get_string('settings:showquestioncount_desc', 'local_casospracticos'),
        1
    ));

    // Show difficulty in list.
    $settings->add(new admin_setting_configcheckbox(
        'local_casospracticos/showdifficulty',
        get_string('settings:showdifficulty', 'local_casospracticos'),
        get_string('settings:showdifficulty_desc', 'local_casospracticos'),
        1
    ));

    // Notifications header.
    $settings->add(new admin_setting_heading(
        'local_casospracticos/notificationsheader',
        get_string('settings:notifications', 'local_casospracticos'),
        ''
    ));

    // Notify on publish.
    $settings->add(new admin_setting_configcheckbox(
        'local_casospracticos/notifyonpublish',
        get_string('settings:notifyonpublish', 'local_casospracticos'),
        get_string('settings:notifyonpublish_desc', 'local_casospracticos'),
        0
    ));

    // Add link to manage cases.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_casospracticos_manage',
        get_string('managecases', 'local_casospracticos'),
        new moodle_url('/local/casospracticos/index.php')
    ));
}
