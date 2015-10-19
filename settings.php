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
 * socialwall course format.  Display the whole course as "socialwall" made of modules.
 *
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configcheckbox(
            'format_socialwall/enablenotification',
            new lang_string('enablenotification', 'format_socialwall'),
            new lang_string('enablenotificationdesc', 'format_socialwall'), 0)
    );

    $settings->add(new admin_setting_configcheckbox(
            'format_socialwall/enablelikes',
            new lang_string('enablelikes', 'format_socialwall'),
            new lang_string('enablelikesdesc', 'format_socialwall'), 1)
    );

    $settings->add(new admin_setting_configcheckbox(
            'format_socialwall/enablestudentupload',
            new lang_string('enablestudentupload', 'format_socialwall'),
            new lang_string('enablestudentuploaddesc', 'format_socialwall'), 1)
    );

    $nums = array(
        '1' => 1,
        '2' => 2,
        '3' => 3,
        '4' => 4,
        '5' => 5,
        '10' => 10,
        '20' => 20
    );

    $settings->add(new admin_setting_configselect(
            'format_socialwall/tlnumposts',
            new lang_string('tlnumposts', 'format_socialwall'),
            new lang_string('tlnumpostsdesc', 'format_socialwall'), 10, $nums)
    );

    $settings->add(new admin_setting_configselect(
            'format_socialwall/tlnumcomments',
            new lang_string('tlnumcomments', 'format_socialwall'),
            new lang_string('tlnumcommentsdesc', 'format_socialwall'), 5, $nums)
    );

    $settings->add(new admin_setting_configselect(
            'format_socialwall/tlnumreplies',
            new lang_string('tlnumreplies', 'format_socialwall'),
            new lang_string('tlnumrepliesdesc', 'format_socialwall'), 5, $nums)
    );

    $settings->add(new admin_setting_configcheckbox(
            'format_socialwall/deleteafterunenrol',
            new lang_string('deleteafterunenrol', 'format_socialwall'),
            new lang_string('deleteafterunenroldesc', 'format_socialwall'), 0)
    );
}