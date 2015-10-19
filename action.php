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
require_once(dirname(__FILE__) . '../../../../config.php');

$courseid = required_param('courseid', PARAM_INT); // ... course id.
$action = required_param('action', PARAM_ALPHA);

// User has clicked the cancel-button in form.
if (isset($_REQUEST['cancel'])) {
    $action = 'cancel';
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_sesskey(); // Gotta have the sesskey.
require_course_login($course);

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);

// ... add all the theme settings.
$course = course_get_format($course)->get_course();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/socialwall/action.php', array('id' => $courseid)));

require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');

$redirecturl = new moodle_url('/course/view.php', array('id' => $course->id));

switch ($action) {

    case 'savepost' :
    case 'updatepost':

        $posts = \format_socialwall\local\posts::instance($course->id);
        $postform = $posts->get_post_form();

        if ($data = $postform->get_data()) {

            // User have turned editing on or off.
            if (isset($data->turneditingon)) {

                if ($PAGE->user_allowed_editing()) {
                    $USER->editing = 1;
                }
            }

            if (isset($data->turneditoron)) {
                $redirecturl = new moodle_url('/course/view.php', array('id' => $course->id, 'loadposteditor' => 1));
            }

            if (isset($data->turneditoroff)) {
                $redirecturl = new moodle_url('/course/view.php', array('id' => $course->id, 'loadposteditor' => 0));
            }

            // User have submitted a post.
            if (isset($data->submitbutton)) {
                // Suitable capability checks are made in save_post!
                $posts->save_post($data, $course);
            }
        }
        redirect($redirecturl);

        break;

    case 'deletepost' :

        $pid = required_param('pid', PARAM_INT);

        $formatsociallwallposts = \format_socialwall\local\posts::instance($course->id);

        // Suitable capability checks are made in delete_post!
        $formatsociallwallposts->delete_post($pid);

        redirect($redirecturl);
        break;

    case 'likepost' :

        \format_socialwall\local\action_handler::like_post($course);
        redirect($redirecturl);
        break;

    case 'makesticky' :

        $posts = \format_socialwall\local\posts::instance($course->id);
        // Suitable capability checks are made in delete_post!
        $posts->makesticky();
        redirect($redirecturl);
        break;

    case 'lockpost' :

        \format_socialwall\local\action_handler::lock_post($course);
        redirect($redirecturl);
        break;

    case 'postcomment' :

        \format_socialwall\local\action_handler::post_comment($course);
        redirect($redirecturl);
        break;

    case 'deletecomment' :

        \format_socialwall\local\action_handler::delete_comment();
        redirect($redirecturl);
        break;

    case 'cancel' :
    case 'resetfilter' :
        $cache = \cache::make('format_socialwall', 'timelinefilter');
        $cache->purge();

        $cache = \cache::make('format_socialwall', 'postformparams');
        $cache->purge();

        $cache = cache::make('format_socialwall', 'attachedrecentactivities');
        $cache->purge();

        redirect($redirecturl);
        break;

    default :
        print_error('unknown action: ' . $action);
}
