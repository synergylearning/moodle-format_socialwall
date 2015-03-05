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
require_once(dirname(__FILE__) . '../../../../../config.php');
require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');
require_once($CFG->dirroot . '/course/format/socialwall/pages/editnotification_form.php');

$courseid = required_param('courseid', PARAM_INT); // Course id.
$userid = $USER->id;

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_pagelayout('course');

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/socialwall/pages/editnotification.php', array('id' => $courseid)));
$PAGE->set_title(get_string('editnotification', 'format_socialwall'));
$PAGE->set_heading(get_string('editnotification', 'format_socialwall'));

$notifications = \format_socialwall\local\notification::instance($courseid);

// ...check, whether module is commentable.
$notificationtype = $notifications->get_notification_user($course, $userid);

$noticiationeditform = new editnotification_form(null,
                array('courseid' => $course->id,
                    'userid' => $userid,
                    'notificationtype' => $notificationtype
        ));

if ($noticiationeditform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', array("id" => $course->id)));
}

if ($data = $noticiationeditform->get_data()) {

    $result = $notifications->save_from_submit($data);

    if ($result['error'] == 0) {

        $redirect = new moodle_url('/course/view.php?id=' . $course->id);
        redirect($redirect, $result['message']);
    } else {
        $msg = $result['message'];
    }
}

echo $OUTPUT->header();

if (!empty($msg)) {
    echo $OUTPUT->notification(get_string($msg, 'format_socialwall'));
}
echo $OUTPUT->heading(get_string('editnotification', 'format_socialwall'), 2);
$noticiationeditform->display();

echo $OUTPUT->footer();