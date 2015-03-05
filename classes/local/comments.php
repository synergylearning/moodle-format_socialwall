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

/** class for timeline comments */
class comments {

    /** create instance as a singleton */
    public static function instance() {
        static $comments;

        if (isset($comments)) {
            return $comments;
        }

        $comments = new comments();
        return $comments;
    }

    /** ... add all comments and add more authors to postsdata record
     * 
     * @global recored $DB
     * @param record $postsdata
     * @return boolean, true, if succeded
     */
    public static function add_comments_to_posts(&$postsdata, $limitcomments = 0) {
        global $DB;

        if (empty($postsdata->posts)) {
            return false;
        }

        $posts = $postsdata->posts;

        if (!$comments = $DB->get_records_list('format_socialwall_comments', 'postid', array_keys($posts), 'timecreated DESC')) {
            return false;
        }

        foreach ($comments as $comment) {

            if (!isset($postsdata->posts[$comment->postid]->comments)) {

                $postsdata->posts[$comment->postid]->comments = array();
            }

            if (!empty($limitcomments) and (count($postsdata->posts[$comment->postid]->comments) == $limitcomments)) {
                continue;
            }
            $postsdata->posts[$comment->postid]->comments[$comment->id] = $comment;
            $postsdata->authors[$comment->fromuserid] = $comment->fromuserid;
        }

        return true;
    }

    /** get all the data for displaying comments of a post
     * 
     * @global object $DB
     * @param int $postid
     * @return \stdClass object containing autors and comment of a post.
     */
    public function get_comments_data($postid) {
        global $DB;

        $postsdata = new \stdClass();

        if (!$postsdata->posts = $DB->get_records('format_socialwall_posts', array('id' => $postid))) {
            return $postsdata->posts = array();
        }

        $postsdata->authors = array();

        // ...fetch comments and add them to $postdata.
        $this->add_comments_to_posts($postsdata);

        // ... finally gather all the required userdata for authors.
        list($inuserids, $params) = $DB->get_in_or_equal(array_keys($postsdata->authors));
        $sql = "SELECT * FROM {user} WHERE id {$inuserids}";

        if (!$users = $DB->get_records_sql($sql, $params)) {
            debugging('error while retrieving post authors');
        }

        $postsdata->authors = $users;

        return $postsdata;
    }

    /** save a new comment from submit
     * 
     * @global record $USER
     * @global object $DB
     * @param object $comment submitted data from form.
     * @return array result array to use for ajax and non ajax request.
     */
    public function save_comment_from_submit($comment, $course) {
        global $USER, $DB;

        // Ensure that post exists and get the right courseid.
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $comment->postid))) {
            print_error('invalidpostid', 'format_socialwall');
        }

        // ... if post is locked or user has no cap to save, don't save the comment.
        $coursecontext = \context_course::instance($post->courseid);
        if (!has_capability('format/socialwall:writecomment', $coursecontext)) {
            print_error('missingcapwritecomment', 'format_socialwall');
        }

        $comment->courseid = $post->courseid;
        $comment->fromuserid = $USER->id;
        $comment->timecreated = time();
        $comment->timemodified = $comment->timecreated;

        // ... check if post is locked.
        if ($post->locked != 0) {
            print_error('postislocked', 'format_socialwall');
        }

        // ...insert comment.
        if (!$comment->id = $DB->insert_record('format_socialwall_comments', $comment)) {
            print_error('commentsaveerror', 'format_socialwall');
        }

        $result = array(
            'error' => '0', 'message' => 'commentsaved',
            'commentid' => $comment->id, 'postid' => $comment->postid,
            'countlikes' => 0, 'countcomments' => 0
        );

        $posts = posts::instance($comment->courseid);

        if ($post = $posts->refresh_comments_count($comment->postid)) {
            $result['countlikes'] = $post->countlikes;
            $result['countcomments'] = $post->countcomments;
        }

        // We use a instant enqueueing, if needed you might use events here.
        notification::enqueue_comment_created($comment);

        return $result;
    }

    /** delete comment and refresh the number of comments in post table
     * 
     * @global object $DB
     * @param tint $cid, id of comment.
     * @return array result
     */
    public function delete_comment($cid) {
        global $DB, $USER;

        // ... get post for refreshing counts after delete.
        if (!$comment = $DB->get_record('format_socialwall_comments', array('id' => $cid))) {
            print_error('commentidinvalid', 'format_socialwall');
        }

        // ...check capability.
        $coursecontext = \context_course::instance($comment->courseid);

        $candeletecomment = (($comment->fromuserid == $USER->id) and
                (has_capability('format/socialwall:deleteowncomment', $coursecontext)));

        $candeletecomment = ($candeletecomment or has_capability('format/socialwall:deleteanycomment', $coursecontext));

        if (!$candeletecomment) {
            print_error('missingcapdeletecomment', 'format_socialwall');
        }

        // ... delete comment.
        $DB->delete_records('format_socialwall_comments', array('id' => $cid));

        // ... delete all the enqueued notifications.
        $DB->delete_records_select('format_socialwall_nfqueue', "module = 'comment' and details = ?", array($cid));

        $result = array(
            'error' => '0', 'message' => 'commentdeleted',
            'commentid' => $cid, 'postid' => $comment->postid,
            'countlikes' => 0, 'countcomments' => 0
        );

        $posts = posts::instance($comment->courseid);

        if ($post = $posts->refresh_comments_count($comment->postid)) {
            $result['countlikes'] = $post->countlikes;
            $result['countcomments'] = $post->countcomments;
        }

        return $result;
    }

}