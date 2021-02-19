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
 * event observer for format socialwall
 *
 * @since     Moodle 2.0
 * @package   format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\course_module_deleted',
        'callback' => 'format_socialwall_course_module_deleted',
        'includefile' => '/course/format/socialwall/locallib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\course_deleted',
        'callback' => 'format_socialwall_course_deleted',
        'includefile' => '/course/format/socialwall/locallib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback' => 'format_socialwall_user_enrolment_deleted',
        'includefile' => '/course/format/socialwall/locallib.php',
        'internal' => true
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => 'format_socialwall_user_deleted',
        'includefile' => '/course/format/socialwall/locallib.php',
        'internal' => true
    )
);
