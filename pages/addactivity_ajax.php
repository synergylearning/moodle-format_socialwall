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
 * add attached actvities to cache.
 *
 * @package   format_socialwall
 * @copyright 2015 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');

$action = required_param('action', PARAM_ALPHA);
$courseid = required_param('courseid', PARAM_INT);
$postid = optional_param('postid', 0, PARAM_INT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$context = context_course::instance($course->id, MUST_EXIST);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/course/format/socialwall/pages/addactivity_ajax.php'));
$PAGE->set_context($context);

if ($action == 'addactivities') {

    $attachedrecentactivityids = optional_param('attachrecentactivitiyids', '', PARAM_TEXT);
    $attachedrecentactivityids = json_decode($attachedrecentactivityids);

    $cache = cache::make('format_socialwall', 'attachedrecentactivities');
    $cache->set($course->id.'_'.$postid, $attachedrecentactivityids);
}

// No return necessary.