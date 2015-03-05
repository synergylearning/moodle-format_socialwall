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
define('AJAX_SCRIPT', true);
require_once(dirname(__FILE__) . '../../../../config.php');

$courseid = required_param('id', PARAM_INT); // ... coursemodule id.
$action = required_param('action', PARAM_ALPHA);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_sesskey(); // Gotta have the sesskey.
require_course_login($course);

// ...start setting up the page.
$context = context_course::instance($course->id, MUST_EXIST);

// ... add all the theme settings.
$course = course_get_format($course)->get_course();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/course/format/socialwall/ajax.php', array('id' => $courseid)));

require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');

require_once($CFG->dirroot . '/course/format/socialwall/classes/local/action_handler.php');

switch ($action) {

    case 'postcomment' :

        $result = \format_socialwall\local\action_handler::post_comment($course);

        if ($result['error'] == 0) {

            $renderer = $PAGE->get_renderer('format_socialwall');
            $comment = $DB->get_record('format_socialwall_comments', array('id' => $result['commentid']));

            $result['commenthtml'] = $renderer->render_ajax_loaded_comment($context, $comment, $USER);
        }

        echo json_encode($result);
        die;

    case 'deletecomment':

        $result = \format_socialwall\local\action_handler::delete_comment();
        echo json_encode($result);
        die;


    case 'likepost' :

        $result = \format_socialwall\local\action_handler::like_post($course);
        echo json_encode($result);
        die;

    case 'lockpost':

        $result = \format_socialwall\local\action_handler::lock_post($course);
        echo json_encode($result);
        die;

    case 'showallcomments':

        $postid = required_param('postid', PARAM_INT);

        // Ensure that post exists and get the correct courseid.
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {
            print_error('invalidpostid', 'format_socialwall');
        }

        if ($post->courseid <> $course->id) {
            print_error('invalidcourseid', 'format_socialwall');
        }

        $comments = \format_socialwall\local\comments::instance();
        $commentsdata = $comments->get_comments_data($postid);

        $renderer = $PAGE->get_renderer('format_socialwall');
        $commentshtml = $renderer->render_ajax_loaded_comments($postid, $context, $commentsdata);

        echo json_encode(array('error' => '0', 'postid' => $postid, 'commentshtml' => $commentshtml));
        die;

    case 'loadmoreposts' :

        $posts = \format_socialwall\local\posts::instance($course->id);

        $limitfrom = optional_param('limitfrom', 0, PARAM_INT);

        // Posts are limited to the max value of $course->tlnumposts in course settings.
        $postsdata = $posts->get_timeline_posts($course, $limitfrom);
        $renderer = $PAGE->get_renderer('format_socialwall');
        $postshtml = $renderer->render_ajax_loaded_posts($course, $postsdata);

        $result = array(
            'error' => '0', 'poststotal' => $postsdata->poststotal,
            'postsloaded' => $postsdata->postsloaded, 'postshtml' => $postshtml);

        echo json_encode($result);
        die;

    case 'storeformparams' :

        // Here we store the values of postformelement for the case the user turns editing after
        // he has done some input.
        $cache = cache::make('format_socialwall', 'postformparams');
        $formparams = $cache->get($course->id);
        $formparams['posttext'] = optional_param('posttext', '', PARAM_RAW);
        $formparams['togroupid'] = optional_param('togroupid', 0, PARAM_INT);
        $formparams['poststatus'] = optional_param('poststatus', 0, PARAM_INT);

        $cache->set($course->id, $formparams);
        die;

    default :
        print_error('unknown action: ' . $action);
}