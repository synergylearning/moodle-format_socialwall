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
 *
 * @since     Moodle 2.7
 * @package   format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('FORMAT_SOCIALWALL_TIMELINESECTION', 1);
define('FORMAT_SOCIALWALL_POSTFORMSECTION', 2);

/** Eventhandling for the mod_created event, activate the 
 *  acl control by setting moduls availabilty field, 
 *  even if user has edit (i. e. deleted), when acl is on.
 */
function format_socialwall_course_module_deleted($event) {

    $eventdata = $event->get_data();
    $cmid = $eventdata['objectid'];
    \format_socialwall\local\attaches::cleanup_coursemoduledeleted($cmid);
}

function format_socialwall_course_deleted($event) {
    $eventdata = $event->get_data();
    $courseid = $eventdata['objectid'];
    \format_socialwall\local\posts::cleanup_coursedeleted($courseid);
}

/** clean up userrelated data, when user unenrolls a course.
 * $eventdata->lastenrol = true; // means user not enrolled any more
 */
function format_socialwall_user_enrolment_deleted($event) {

    $eventdata = $event->get_data();

    $ue = $eventdata['other']['userenrolment'];

    if ($ue['lastenrol']) {
        \format_socialwall\local\posts::cleanup_userunrenolled($eventdata['relateduserid'], $eventdata['courseid']);
    }
}

function format_socialwall_user_deleted($event) {

    $eventdata = $event->get_data();
    $userid = $eventdata['objectid'];

    \format_socialwall\local\posts::cleanup_userdeleted($userid);
}