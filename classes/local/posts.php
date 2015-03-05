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

namespace format_socialwall\local;

/** This class manages posts, which can created by users in a course with
 *  socialwall format.
 */
class posts {

    protected $postform;
    protected $courseid;
    protected $filteroptions;

    protected function __construct($courseid) {
        $this->courseid = $courseid;
    }

    /** create instance as a singleton 
     * 
     * @param int $courseid
     * @return \format_socialwall\local\posts
     */
    public static function instance($courseid) {
        static $posts;

        if (isset($posts)) {
            return $posts;
        }

        $posts = new posts($courseid);
        return $posts;
    }

    /** cleanup data, when a user is deleted
     * 
     * @global object $DB
     * @param int $userid
     * @return boolean true if succeeded
     */
    public static function cleanup_userdeleted($userid) {
        global $DB;

        // ... delete posts of the user.
        self::delete_users_posts($userid);

        // ... delete comments of user.
        $DB->delete_records('format_socialwall_comments', array('fromuserid' => $userid));

        // ... delete likes.
        $DB->delete_records('format_socialwall_likes', array('fromuserid' => $userid));

        // ... delete all the enqueued notifications.
        $DB->delete_records('format_socialwall_nfqueue', array('recipientid' => $userid));
        $DB->delete_records('format_socialwall_nfqueue', array('creatorid' => $userid));

        // ... delete all the notification settings.
        $DB->delete_records('format_socialwall_nfsettings', array('userid' => $userid));

        return true;
    }

    /** delete users post and comments, in user is unenrolled
     * 
     * @global object $DB
     * @param int $userid
     * @param int $courseid 
     * @return boolean true when succeeded
     */
    public static function cleanup_userunrenolled($userid, $courseid) {
        global $DB;

        // ...check coursesettings.
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            return false;
        }

        $course = course_get_format($course)->get_course();

        if (empty($course->deleteafterunenrol)) {
            return false;
        }

        // ... delete posts of the user.
        self::delete_users_posts($userid);

        // ... delete comments of user.
        $DB->delete_records_select('format_socialwall_comments', 'fromuserid = ? AND courseid = ?', array($userid, $courseid));

        return true;
    }

    /** cleanup all the data in the format socialwall tables 
     * 
     * @param int $courseid, the course id.
     */
    public static function cleanup_coursedeleted($courseid) {
        global $DB;

        // ... delete post.
        $DB->delete_records('format_socialwall_posts', array('courseid' => $courseid));

        // ... delete comments.
        $DB->delete_records('format_socialwall_comments', array('courseid' => $courseid));

        // ... delete likes.
        $DB->delete_records('format_socialwall_likes', array('courseid' => $courseid));

        // ... delete all the enqueued notifications.
        $DB->delete_records('format_socialwall_nfqueue', array('courseid' => $courseid));

        // ... delete all the notification settings.
        $DB->delete_records('format_socialwall_nfsettings', array('courseid' => $courseid));
    }

    /**
     * Post form is neede in two separate scripts (view.php and action.php), so we create
     * it here in centralized place.
     * 
     * @return object the post form
     */
    public function get_post_form() {

        // Count the activities in section == FORMAT_SOCIALWALL_POSTFORMSECTION.
        $modinfo = get_fast_modinfo($this->courseid);
        $cmids = (isset($modinfo->sections[FORMAT_SOCIALWALL_POSTFORMSECTION])) ? $modinfo->sections[FORMAT_SOCIALWALL_POSTFORMSECTION] : array();

        $options = $this->get_filter_options($this->courseid);
        $formparams = array('courseid' => $this->courseid, 'cmids' => $cmids, 'options' => $options);

        $actionurl = new \moodle_url('/course/format/socialwall/action.php');

        $this->postform = new \post_form($actionurl, $formparams, 'post', '', array('id' => 'postform'));

        return $this->postform;
    }

    /** get and store filter setting for posts list in Session
     *  for each course.
     * 
     * @param int $courseid
     * @return record filteroptions
     */
    protected function get_filter_options($courseid) {

        $showalert = optional_param('showalert', '0', PARAM_INT);
        if (!empty($showalert)) {
            return (object) array('showalert' => $showalert);
        }

        $cache = \cache::make('format_socialwall', 'timelinefilter');

        if (!$timelinefilter = $cache->get($courseid)) {

            $timelinefilter = new \stdClass();
            $timelinefilter->filtergroups = 0;
            $timelinefilter->filtermodules = '';
            $timelinefilter->orderby = 'timecreated desc';
        }

        $changed = false;

        $filtergroups = optional_param('tl_filtergroup', -1, PARAM_INT);
        if ($filtergroups > -1) {
            $timelinefilter->filtergroups = $filtergroups;
            $changed = true;
        }

        $filtermodules = optional_param('tl_filtermodules', 'nomodule', PARAM_TEXT);
        if ($filtermodules != 'nomodule') {
            $timelinefilter->filtermodules = $filtermodules;
            $changed = true;
        }

        $orderby = optional_param('tl_orderby', 'oldsort', PARAM_TEXT);
        if ($orderby != 'oldsort') {
            $timelinefilter->orderby = $orderby;
            $changed = true;
        }

        if ($changed) {
            $cache->set($courseid, $timelinefilter);
        }

        return $timelinefilter;
    }

    /** get all Posts (with authors) from the database by courseid
     * 
     * @global object $DB
     * @param int $course, with theme settings loaded.
     * @return \stdClass, postsdata (infodata for all posts).
     */
    protected function get_all_posts($course, $options = null, $limitfrom = 0,
                                     $limitcount = 0, $orderby = array()) {
        global $DB, $COURSE, $USER;

        $courseid = $course->id;
        $context = \context_course::instance($courseid);

        // ... prepare posts infodata.
        $postsdata = new \stdClass();
        $postsdata->posts = array();
        $postsdata->poststotal = 0;
        $postsdata->postsloaded = 0;
        $postsdata->filteroptions = $options;
        $postsdata->authors = array();

        if ($limitcount == 0) {
            $limitcount = (!empty($course->tlnumposts)) ? $course->tlnumposts : 0;
        }

        $cond = array("WHERE sp.courseid = ?");
        $params = array($courseid);
        $join = "";

        // ... no private posts?
        if (!has_capability('format/socialwall:viewprivate', $context)) {
            $cond[] = " sp.private = '0'";
        }

        // ... only users groups?
        if (!empty($options->filtergroups)) {

            $cond[] = " sp.togroupid = ?";
            $params[] = $options->filtergroups;
        } else {

            // ... if seperate groups are set and user is not allowed to see other groups set filter.
            $groupmode = groups_get_course_groupmode($COURSE);

            if (($groupmode == SEPARATEGROUPS) and !has_capability('moodle/course:managegroups', $context)) {

                $keys = array(0); // To all participants.

                if ($usersgroups = groups_get_all_groups($courseid, $USER->id)) {

                    $keys = array_merge($keys, array_keys($usersgroups));
                }

                list($ingroups, $inparams) = $DB->get_in_or_equal($keys);
                $cond[] = " sp.togroupid {$ingroups}";
                $params = array_merge($params, $inparams);
            }
        }

        if (!empty($options->filtermodules)) {
            $join = "JOIN {format_socialwall_attaches} at ON at.postid = sp.id ";
            $join .= "JOIN {course_modules} cm ON cm.id = at.coursemoduleid ";
            $join .= "JOIN {modules} m ON m.id = cm.module ";
            $cond[] = " m.name = ?";
            $params[] = $options->filtermodules;
        }

        if (!empty($options->filteralerts)) {
            $cond[] = " sp.alert = '1'";
        }

        // ...show only one post on page, when option showalert is set.
        if (!empty($options->showalert)) {
            $cond[] = " sp.id = ?";
            $params[] = $options->showalert;
        }

        if (!empty($options->orderby)) {
            $orderby[] = 'sp.' . $options->orderby;
        }

        $ordering = '';
        if (!empty($orderby)) {
            $ordering = 'ORDER BY ' . implode(', ', $orderby);
        }

        $where = implode(' AND ', $cond);

        // ... get all posts.
        $sqlfrom = "FROM {format_socialwall_posts} sp {$join} {$where} ";

        $sql = "SELECT DISTINCT sp.* " . $sqlfrom . " {$ordering} ";

        $countsql = "SELECT count(DISTINCT sp.id) as total " . $sqlfrom;

        if (!$postsdata->poststotal = $DB->count_records_sql($countsql, $params)) {
            return $postsdata;
        }

        if (!$postsdata->posts = $DB->get_records_sql($sql, $params, $limitfrom, $limitcount)) {
            return $postsdata;
        }

        $postsdata->postsloaded = $limitfrom + count($postsdata->posts);
        return $postsdata;
    }

    /** add all post data, which is necessary for rendering the posts
     * 
     * @global object $DB
     * @param record $course
     * @param  object $postsdata containing retrieved posts in $postdata->post
     * @return object the postdata object with all of the data added
     */
    protected function add_posts_data($course, &$postsdata) {
        global $DB;

        if (empty($postsdata->posts)) {
            return $postsdata;
        }

        $courseid = $course->id;

        // ... gather all the authors ids from posts.
        foreach ($postsdata->posts as &$post) {

            $postsdata->authors[$post->fromuserid] = $post->fromuserid;
        }

        // ... add all comments and add more authors.
        comments::add_comments_to_posts($postsdata, $course->tlnumcomments);

        // ... add all likes from this user.
        $likes = likes::instance($course);
        $likes->add_likes_to_posts($postsdata);

        // ... add all attachments.
        attaches::add_attaches_to_posts($courseid, $postsdata);

        // ... finally gather all the required userdata for authors.

        $params = array();
        list($inuserids, $params) = $DB->get_in_or_equal(array_keys($postsdata->authors));

        $sql = "SELECT * FROM {user} WHERE id {$inuserids}";

        if (!$users = $DB->get_records_sql($sql, $params)) {
            debugging('error while retrieving post authors');
        }

        $postsdata->authors = $users;

        return $postsdata;
    }

    /** get postdata object for all alerting posts
     * 
     * @param record $course
     * @param int $limitfrom
     * @param int $limitcount
     * @param array $orderby
     * @return object postdata object with all data for rendering.
     */
    public function get_alert_posts($course, $limitfrom = 0, $limitcount = 0,
                                    $orderby = array()) {

        $options = new \stdClass();
        $options->filteralerts = true;
        return $this->get_all_posts($course, $options, $limitfrom, $limitcount, $orderby);
    }

    /** get postdata object for timeline posts
     * 
     * @param record $course
     * @param int $limitfrom
     * @param int $limitcount
     * @param array $orderby
     * @return object postdata object with all data for rendering.
     */
    public function get_timeline_posts($course, $limitfrom = 0, $limitcount = 0) {

        $options = $this->get_filter_options($course->id);
        $postsdata = $this->get_all_posts($course, $options, $limitfrom, $limitcount, array('sticky desc'));
        return $this->add_posts_data($course, $postsdata);
    }

    /** if modules are attached to a post they must be moved to timeline section 
     *  (normally section number 1).
     * 
     * @param type $courseid
     * @param type $cmsequence
     * @return type
     */
    private function check_and_move_module($courseid, $cmsequence) {

        $modinfo = get_fast_modinfo($courseid);
        $section = $modinfo->get_section_info(FORMAT_SOCIALWALL_TIMELINESECTION);

        $cmids = explode(',', $cmsequence);

        foreach ($cmids as $cmid) {

            $mod = $modinfo->get_cm($cmid);
            moveto_module($mod, $section);
        }

        return $cmsequence;
    }

    /** If user has attached a file to a post we must create a modul with type file
     *  and attach it to post.
     * 
     * @global record $USER
     * @global record $CFG
     * @global record $COURSE
     * @global object $DB
     * @param object $form  submitted data of form.
     * @return record the created module of type file.
     */
    protected function create_mod_files($form) {
        global $USER, $CFG, $COURSE, $DB;

        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $form->files, 'sortorder, id', false)) {
            // ...if no module can be created return an empty string, so not module info will be stored as attachment.
            return '';
        }

        $file = reset($files);

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        $moduleid = $DB->get_field('modules', 'id', array('name' => 'resource'));

        $data = new \stdClass();
        $data->modulename = 'resource';
        $data->module = $moduleid;
        $data->name = $file->get_filename();
        $data->visible = 1;
        $data->section = 1;
        $data->files = $form->files;
        $data->display = RESOURCELIB_DISPLAY_AUTO;

        // Create instance of mod_files.
        $modinfo = add_moduleinfo($data, $COURSE);

        return $modinfo->coursemodule;
    }

    /** if user has attached a url to a post, we must create a module with type url
     * 
     * @global record $CFG
     * @global record $COURSE
     * @global object $DB
     * @param string $url the url string
     * @return record the created module of type url.
     */
    protected function create_mod_url($url) {
        global $CFG, $COURSE, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        $moduleid = $DB->get_field('modules', 'id', array('name' => 'url'));

        $data = new \stdClass();
        $data->modulename = 'url';
        $data->module = $moduleid;
        $data->name = $url;
        $data->visible = 1;
        $data->section = 1;
        $data->externalurl = $url;
        $data->display = RESOURCELIB_DISPLAY_POPUP;
        $data->popupwidth = 400;
        $data->popupheight = 400;

        // Create instance of url.
        $modinfo = add_moduleinfo($data, $COURSE);

        return $modinfo->coursemodule;
    }

    /** save a post using the submitted data
     * 
     * @global type $USER
     * @global obejct $DB
     * @param type $data
     * @return type
     */
    public function save_post($data, $course) {
        global $USER, $DB, $CFG;

        require_once($CFG->dirroot . '/mod/url/locallib.php');
        $context = \context_course::instance($data->id);

        // ...save when even a posttext or a externalurl or a file or a actvitiy is given.
        $cmsequence = $data->cmsequence;

        // ... added activity?
        if (!empty($cmsequence)) {

            $cmsequence = $this->check_and_move_module($data->id, $cmsequence);
        } else {

            // ... add a resource.
            if (!empty($data->files)) {

                $canpostfile = (has_capability('format/socialwall:postfile', $context) && (!empty($course->enablestudentupload)));

                if (!$canpostfile) {
                    print_error('missingcappostfile', 'format_socialwall');
                }
                $cmsequence = $this->create_mod_files($data);
            } else {

                // ... check externalurl and create a activity in section 1, if necessary.
                if (!empty($data->externalurl)) {

                    $canposturl = (has_capability('format/socialwall:posturl', $context) && (!empty($course->enablestudentupload)));
                    if (!$canposturl) {
                        print_error('missingcapposturl', 'format_socialwall');
                    }

                    $cmsequence = $this->create_mod_url($data->externalurl);

                    // ... set filter Plugin here.
                    $filters = filter_get_active_in_context($context);
                    if (isset($filters['urlresource'])) {
                        require_once($CFG->dirroot . '/filter/urlresource/lib.php');
                        \filter_url_resource_helper::save_externalurl($data, $cmsequence);
                    }
                }
            }
        }

        if ((empty($data->posttext)) and (empty($cmsequence))) {
            print_error('attachmentorpostrequired', 'format_socialwall');
        }

        // ... create post.
        $post = new \stdClass();
        $post->courseid = $data->id;
        $post->fromuserid = $USER->id;
        $post->togroupid = $data->togroupid;
        
        if (is_array($data->posttext)) {
            $posttext = $data->posttext['text'];
        } else {
            $posttext = $data->posttext;
        }

        if (has_capability('format/socialwall:posthtml', $context)) {
            $post->posttext = clean_text($posttext);
        } else {
            $post->posttext = clean_param($posttext, PARAM_NOTAGS);
        }

        if (isset($data->poststatus)) {
            $post->sticky = ($data->poststatus == 1);
            $post->private = ($data->poststatus == 2);
            $post->alert = ($data->poststatus == 4);
        } else {
            $post->sticky = 0;
            $post->private = 0;
            $post->alert = 0;
        }

        $post->timecreated = time();
        $post->timemodified = $post->timecreated;

        $post->id = $DB->insert_record('format_socialwall_posts', $post);

        if (!empty($cmsequence)) {
            attaches::save_attaches($post->id, $cmsequence);
        }

        // We use a instant enqueueing, if needed you might use events here.
        notification::enqueue_post_created($post);

        // ...clear the inputed values.
        $cache = \cache::make('format_socialwall', 'postformparams');
        $cache->purge();

        return array('error' => '0', 'message' => 'postsaved');
    }

    /** delete comment and refresh the number of comments in post table
     * 
     * @global object $DB
     * @param tint $cid, id of comment.
     * @return array result
     */
    public function delete_post($pid) {
        global $DB, $USER;

        // ... get post for refreshing counts after delete.
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $pid))) {
            print_error('postidinvalid', 'format_socialwall');
        }

        // ...check capability.
        $coursecontext = \context_course::instance($post->courseid);
        $candeletepost = (($post->fromuserid == $USER->id) and (has_capability('format/socialwall:deleteownpost', $coursecontext)));
        $candeletepost = ($candeletepost or has_capability('format/socialwall:deleteanypost', $coursecontext));

        if (!$candeletepost) {
            print_error('missingcapdeletepost', 'format_socialwall');
        }

        self::execute_delete($pid);
        return array('error' => '0', 'message' => 'postdeleted');
    }

    /** delete all posts of the user including the data related to users posts
     * 
     * @global object $DB
     * @param int $userid
     */
    protected static function delete_users_posts($userid) {
        global $DB;

        // ... delete comments.
        $sql = "DELETE c FROM {format_socialwall_comments} c
                JOIN mdl_format_socialwall_posts p ON p.id = c.postid
                WHERE p.fromuserid = ?";

        $DB->execute($sql, array($userid));

        // ... delete likes.
        $sql = "DELETE l FROM {format_socialwall_likes} l
                JOIN mdl_format_socialwall_posts p ON p.id = l.postid
                WHERE p.fromuserid = ?";

        $DB->execute($sql, array($userid));

        // ... delete attaches.
        $sql = "DELETE a FROM {format_socialwall_attaches} a
                JOIN mdl_format_socialwall_posts p ON p.id = a.postid
                WHERE p.fromuserid = ?";

        $DB->execute($sql, array($userid));

        // ... delete all the enqueued notifications about this post.
        $sql = "DELETE q FROM {format_socialwall_nfqueue} q
                JOIN mdl_format_socialwall_posts p ON p.id = q.postid
                WHERE p.fromuserid = ?";

        $DB->execute($sql, array($userid));

        // ... finally delete post.
        $DB->delete_records('format_socialwall_posts', array('fromuserid' => $userid));
    }

    /** delete all data related to a post
     * 
     * @global obejct $DB
     * @param int $pid the id of the post
     */
    protected static function execute_delete($pid) {
        global $DB;

        // ... delete comments.
        $DB->delete_records('format_socialwall_comments', array('postid' => $pid));

        // ... delete likes.
        $DB->delete_records('format_socialwall_likes', array('postid' => $pid));

        // ... delete attaches.
        $DB->delete_records('format_socialwall_attaches', array('postid' => $pid));

        // ... delete all the enqueued notifications.
        $DB->delete_records('format_socialwall_nfqueue', array('postid' => $pid));

        // ... delete post.
        $DB->delete_records('format_socialwall_posts', array('id' => $pid));
    }

    /** refresh the value of the countcomments column in format_socialwall table
     * 
     * @global object $DB
     * @param int $postid
     */
    public function refresh_comments_count($postid) {
        global $DB;

        if ($post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {

            $post->countcomments = $DB->count_records('format_socialwall_comments', array('postid' => $postid));
            $post->timemodified = time();

            $DB->update_record('format_socialwall_posts', $post);
            return $post;
        }
        return false;
    }

    /** refresh the value of the countlikes column in format_socialwall table
     * 
     * @global object $DB
     * @param int $postid
     */
    public function refresh_likes_count($postid) {
        global $DB;

        if ($post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {

            $post->countlikes = $DB->count_records('format_socialwall_likes', array('postid' => $postid));
            $post->timemodified = time();

            $DB->update_record('format_socialwall_posts', $post);
            return $post->countlikes;
        }
        return 0;
    }

    /** save the locked state for a post
     * 
     * @global object $DB
     * @return result array.
     */
    public function save_posts_locked_from_submit() {
        global $DB;

        // Ensure that post exists and get the right courseid.
        $postid = required_param('postid', PARAM_INT);
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {
            print_error('invalidpostid', 'format_socialwall');
        }

        // ... check capability.
        $coursecontext = \context_course::instance($post->courseid);
        if (!has_capability('format/socialwall:lockcomment', $coursecontext)) {
            print_error('missingcaplockcomment', 'format_socialwall');
        }

        $locked = optional_param('locked', '0', PARAM_INT);

        if ($post->locked != $locked) {

            $post->locked = $locked;
            $post->timemodified = time();
            $DB->update_record('format_socialwall_posts', $post);

            // We use a instant enqueueing, if needed you might use events here.
            notification::enqueue_post_locked($post);
        }
        return array('error' => '0', 'message' => 'postsaved', 'postid' => $post->id, 'locked' => "{$post->locked}");
    }

    /** save the sticky state for a post
     * 
     * @global object $DB
     * @return result array.
     */
    public function makesticky() {
        global $DB;

        // Ensure that post exists and get the right courseid.
        $postid = required_param('postid', PARAM_INT);
        if (!$post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {
            print_error('invalidpostid', 'format_socialwall');
        }

        // ... check capability.
        $coursecontext = \context_course::instance($post->courseid);
        if (!has_capability('format/socialwall:makesticky', $coursecontext)) {
            print_error('missingcapmakesticky', 'format_socialwall');
        }

        $sticky = optional_param('sticky', '0', PARAM_INT);

        if ($post->sticky != $sticky) {

            $post->sticky = $sticky;
            $post->timemodified = time();
            $DB->update_record('format_socialwall_posts', $post);
        }
        return array('error' => '0', 'message' => 'postsaved', 'postid' => $post->id, 'sticky' => "{$post->sticky}");
    }

}
