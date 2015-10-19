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

namespace format_socialwall\local;

class action_handler {

    public static function post_comment($course) {
        global $CFG;

        $postid = required_param('postid', PARAM_INT);
        $replycommentid = required_param('replycommentid', PARAM_INT);

        require_once($CFG->dirroot . '/course/format/socialwall/pages/comment_form.php');

        $url = new \moodle_url('/course/format/socialwall/action.php');
        $commentform = new \comment_form($url, array('postid' => $postid,
            'id' => $course->id, 'replycommentid' => $replycommentid));

        if ($data = $commentform->get_data()) {

            // Suitable capability checks are made in save_post!
            $comments = comments::instance();
            return $comments->save_comment_from_submit($data, $course);
        }
        return false;
    }

    public static function delete_comment() {

        $cid = required_param('cid', PARAM_INT);

        $formatsociallwallcomments = comments::instance();
        return $formatsociallwallcomments->delete_comment($cid);
    }

    public static function like_post($course) {

        $likes = likes::instance($course);
        // Suitable capability checks are made in save_likes_from_submit!
        return $likes->save_likes_from_submit();
    }

    public static function lock_post($course) {

        $posts = posts::instance($course->id);
        // Suitable capability checks are made in save_posts_locked_from_submit!
        return $posts->save_posts_locked_from_submit();
    }

}
