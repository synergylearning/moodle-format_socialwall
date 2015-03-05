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
 * @since     Moodle 2.7
 * @package   format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_socialwall\local;

class likes {

    // ... course record with theme settings.
    protected $course;

    protected function __construct($course) {
        $this->course = $course;
    }

    /** create instance as a singleton */
    public static function instance($course) {
        static $likes;

        if (isset($likes)) {
            return $likes;
        }

        $likes = new likes($course);
        return $likes;
    }

    /** add info about this users likes to the postsdata object
     * 
     * @global object $DB
     * @global record $USER
     * @param record $postsdata
     * @return boolean, true if succeded
     */
    public function add_likes_to_posts(&$postsdata) {
        global $DB, $USER;

        if (empty($this->course->enablelikes)) {
            return false;
        }

        if (empty($postsdata->posts)) {
            return false;
        }

        $posts = $postsdata->posts;

        list($inpoststr, $params) = $DB->get_in_or_equal(array_keys($posts));
        $params[] = $USER->id;

        $sql = "SELECT postid FROM {format_socialwall_likes}
                WHERE postid {$inpoststr} AND fromuserid = ?";

        if (!$likes = $DB->get_records_sql($sql, $params)) {
            return false;
        }

        foreach ($postsdata->posts as &$post) {

            if (isset($likes[$post->id])) {
                $post->userlike = 1;
            } else {
                $post->userlike = 0;
            }
        }

        return true;
    }

    /** save a new comment from submit
     * 
     * @global record $USER
     * @global object $DB
     * @return array, result array.
     */
    public function save_likes_from_submit() {
        global $USER, $DB;

        if (empty($this->course->enablelikes)) {
            print_error('likesaredisabled' , 'format_socialwall');
        }

        // Ensure that post exists and get the right courseid.
        $postid = required_param('postid', PARAM_INT);
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {
            print_error('invalidpostid', 'format_socialwall');
        }

        $userlike = optional_param('userlike', '0', PARAM_INT);

        // ... check capability.
        $coursecontext = \context_course::instance($post->courseid);
        if (!has_capability('format/socialwall:like', $coursecontext)) {
            print_error('missingcaplikepost', 'format_socialwall');
        }

        $refresh = false;
        if (empty($userlike)) {

            if ($like = $DB->get_records('format_socialwall_likes', array('postid' => $postid, 'fromuserid' => $USER->id))) {

                $DB->delete_records_select('format_socialwall_likes', 'fromuserid = ? AND postid = ?', array($USER->id, $postid));
                $refresh = true;

                // We use a instant enqueueing, if needed you might use events here.
                notification::enqueue_like_deleted($post);
            }
        } else {

            if (!$like = $DB->get_records('format_socialwall_likes', array('postid' => $postid, 'fromuserid' => $USER->id))) {

                $newlike = new \stdClass();
                $newlike->courseid = $this->course->id;
                $newlike->postid = $postid;
                $newlike->fromuserid = $USER->id;
                $newlike->timecreated = time();

                $DB->insert_record('format_socialwall_likes', $newlike);
                $refresh = true;

                notification::enqueue_like_created($post);
            }
        }

        $result = array(
            'error' => '0', 'message' => 'likesaved',
            'postid' => $postid, 'userlike' => $userlike,
            'countcomments' => $post->countcomments, 'countlikes' => $post->countlikes);

        if ($refresh) {
            $posts = posts::instance($this->course->id);
            $result['countlikes'] = $posts->refresh_likes_count($postid);
        }

        return $result;
    }

}