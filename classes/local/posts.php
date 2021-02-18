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

defined('MOODLE_INTERNAL') || die();

/**
 * This class manages posts, which can created by users in a course with
 * socialwall format.
 */
class posts {

    protected $postform;
    protected $courseid;
    protected $filteroptions;

    protected function __construct($courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Create instance as a singleton
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

    /**
     * Cleanup data, when a user is deleted
     *
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

    /**
     * Delete users post and comments, in user is unenrolled
     *
     * @param int $userid
     * @param int $courseid
     * @return boolean true when succeeded
     */
    public static function cleanup_userunrenolled($userid, $courseid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/format/lib.php');

        // ...check coursesettings.
        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            return false;
        }

        $course = course_get_format($course)->get_course();

        if (empty($course->deleteafterunenrol)) {
            return false;
        }

        // ... delete posts of the user.
        self::delete_users_posts($userid, $courseid);

        // ... delete comments of user.
        $DB->delete_records_select('format_socialwall_comments', 'fromuserid = ? AND courseid = ?', array($userid, $courseid));

        return true;
    }

    /**
     * Cleanup all the data in the format socialwall tables
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
    public function get_post_form($postsdata = null) {
        global $USER;

        // Count the activities in section == FORMAT_SOCIALWALL_POSTFORMSECTION.
        $modinfo = get_fast_modinfo($this->courseid);
        $cmids = (isset($modinfo->sections[FORMAT_SOCIALWALL_POSTFORMSECTION])) ?
                $modinfo->sections[FORMAT_SOCIALWALL_POSTFORMSECTION] : array();

        $options = $this->get_filter_options($this->courseid);
        $formparams = array('courseid' => $this->courseid, 'cmids' => $cmids, 'options' => $options);

        $actionurl = new \moodle_url('/course/format/socialwall/action.php');

        $this->postform = new \post_form($actionurl, $formparams, 'post', '', array('id' => 'postform'));

        // If the timeline is filtered by postid and this user has the capability to edit the post, load the posts data into form.
        if (!empty($options->postid)) {

            if (isset($postsdata->posts[$options->postid])) {

                $post = $postsdata->posts[$options->postid];
                $coursecontext = \context_course::instance($post->courseid);

                // ... add edit icon, if edting is allowed.
                $caneditpost = ($post->fromuserid == $USER->id
                               && has_capability('format/socialwall:updateownpost', $coursecontext));
                $caneditpost = ($caneditpost or has_capability('format/socialwall:updateanypost', $coursecontext));

                if ($caneditpost) {
                    $this->postform->set_data($post);
                }
            }
        }

        return $this->postform;
    }

    /**
     * Get and store filter setting for posts list in Session
     * for each course.
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
            $timelinefilter->postid = 0;
            $timelinefilter->filtergroups = 0;
            $timelinefilter->filtermodules = '';
            $timelinefilter->orderby = 'timecreated desc';
        }

        $postid = optional_param('postid', '0', PARAM_INT);
        if (!empty($postid)) {

            $timelinefilter->postid = $postid;
            $cache->set($courseid, $timelinefilter);

            return $timelinefilter;
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

    /**
     * Get all Posts (with authors) from the database by courseid
     *
     * @param int $course, with theme settings loaded.
     * @return \stdClass, postsdata (infodata for all posts).
     */
    protected function get_all_posts($course, $options = null, $limitfrom = 0, $limitcount = 0, $orderby = array()) {
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

            if (($groupmode == SEPARATEGROUPS) and ! has_capability('moodle/course:managegroups', $context)) {

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

        if (!empty($options->postid)) {
            $cond[] = " sp.id = ? ";
            $params[] = $options->postid;
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

    /**
     * Add all post data, which is necessary for rendering the posts
     *
     * @param object $course
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

        // ... add all comments, replies to comments and add more authors.
        comments::add_comments_to_posts($postsdata, $course->tlnumcomments, $course->tlnumreplies);

        // ... add all likes from this user.
        $likes = likes::instance($course);
        $likes->add_likes_to_posts($postsdata);

        // ... add all attachments.
        attaches::add_attaches_to_posts($courseid, $postsdata);

        // ... finally gather all the required userdata for authors.
        if (!$users = $DB->get_records_list('user', 'id', array_keys($postsdata->authors))) {
            debugging('error while retrieving post authors');
        }

        $postsdata->authors = $users;

        return $postsdata;
    }

    /**
     * Get postdata object for all alerting posts
     *
     * @param object $course
     * @param int $limitfrom
     * @param int $limitcount
     * @param array $orderby
     * @return object postdata object with all data for rendering.
     */
    public function get_alert_posts($course, $limitfrom = 0, $limitcount = 0, $orderby = array()) {

        $options = new \stdClass();
        $options->filteralerts = true;
        return $this->get_all_posts($course, $options, $limitfrom, $limitcount, $orderby);
    }

    /**
     * Get postdata object for timeline posts
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

    /**
     * If modules are attached to a post they must be moved to timeline section
     * (normally section number 1).
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

    /**
     * If user has attached a file to a post we must create a modul with type file
     * and attach it to post.
     *
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

    /**
     * If user has attached a url to a post, we must create a module with type url
     *
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

    /**
     * Save a post using the submitted data
     *
     * @param type $data
     * @return type
     */
    public function save_post($data, $course) {
        global $USER, $DB, $CFG, $PAGE;

        require_once($CFG->dirroot . '/mod/url/locallib.php');
        $context = \context_course::instance($data->courseid);

        // ... create post.
        $post = new \stdClass();
        $update = false;

        // If id is not empty, ensure that post is existing and test whether user is updating the post.
        if (!empty($data->id)) {

            if ($exists = $DB->get_record('format_socialwall_posts', array('id' => $data->id))) {
                $post = $exists;

                // Check, whether user is allowed to update the post.
                $caneditpost = ($post->fromuserid == $USER->id
                                && has_capability('format/socialwall:updateownpost', $context));
                $caneditpost = ($caneditpost or has_capability('format/socialwall:updateanypost', $context));

                if (!$caneditpost) {
                    print_error('missingcapupdatepost', 'format_socialwall');
                }
                $update = true;
            } else {
                print_error('noposttoupdate', 'format_socialwall');
            }
        }

        // ...save when even a posttext or a externalurl or a file or a actvitiy is given.
        $cmsequence = $data->cmsequence;

        // ... are there added activities?
        if (!empty($cmsequence)) {

            $cmsequence = $this->check_and_move_module($data->courseid, $cmsequence);
        }

        // If user may not add any activity but may add a file or a link, replace existing files with new file.
        if (!$PAGE->user_allowed_editing()) {

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

        if ((empty($data->posttext)) and ( empty($cmsequence))) {
            print_error('attachmentorpostrequired', 'format_socialwall');
        }

        $post->courseid = $data->courseid;
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

        if ($update) {

            $post->timemodified = time();
            $DB->update_record('format_socialwall_posts', $post);

            // ...reset postid if post was updated.
            $cache = \cache::make('format_socialwall', 'timelinefilter');
            $cache->purge_current_user();
        } else {

            $post->timecreated = time();
            $post->timemodified = $post->timecreated;

            $post->id = $DB->insert_record('format_socialwall_posts', $post);
        }

        attaches::save_attaches($post->id, $cmsequence);

        // We use a instant enqueueing, if needed you might use events here.
        notification::enqueue_post_created($post);

        // ...clear the inputed values.
        $cache = \cache::make('format_socialwall', 'postformparams');
        $cache->purge();

        // ...clear the attached actvities.
        $cache = \cache::make('format_socialwall', 'attachedrecentactivities');
        $cache->purge();

        return array('error' => '0', 'message' => 'postsaved');
    }

    /**
     * Delete comment and refresh the number of comments in post table
     *
     * @param int $cid, id of comment.
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
        $candeletepost = ($post->fromuserid == $USER->id
                          && has_capability('format/socialwall:deleteownpost', $coursecontext));
        $candeletepost = ($candeletepost or has_capability('format/socialwall:deleteanypost', $coursecontext));

        if (!$candeletepost) {
            print_error('missingcapdeletepost', 'format_socialwall');
        }

        self::execute_delete($pid);
        return array('error' => '0', 'message' => 'postdeleted');
    }

    /**
     * Delete activities of all the posts of a user, when course format setting
     * "deletemodspermanently" is set to yes and the activity is not attached to another post.
     *
     * @param int $userid
     * @return boolean, true if at least one activitity is deleted.
     */
    protected static function delete_users_posts_activities($userid, $courseid = 0) {
        global $DB;

        $where = " WHERE o.format = 'socialwall' AND o.name = 'deletemodspermanently' AND o.value = '1' ";
        $params = array('userid' => $userid);

        // Checking courseid.
        if (!empty($courseid)) {
            $where .= " AND p.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Get all modids having count = 1.
        $sql = "SELECT a.coursemoduleid, count(*) as countmod
                 FROM {format_socialwall_attaches} a
                 JOIN {format_socialwall_posts} p ON (p.id = a.postid AND p.fromuserid = :userid)
                 JOIN {format_socialwall_attaches} a2 ON (a2.coursemoduleid = a.coursemoduleid AND a2.postid = p.id)
                 JOIN {course_format_options} o ON o.courseid = p.courseid
                 {$where}
                 GROUP BY coursemoduleid
                 HAVING countmod = '1'";

        if (!$modcounts = $DB->get_records_sql($sql, $params)) {
            return false;
        }

        $cmids = array_keys($modcounts);

        foreach ($cmids as $cmid) {
            course_delete_module($cmid);
        }

        return true;
    }

    /**
     * Delete all posts of the user including the data related to users posts
     *
     * @param int $userid
     */
    protected static function delete_users_posts($userid, $courseid = 0) {
        global $DB;

        $cond = array();
        $cond[] = " p.fromuserid = :userid ";

        $params = array();
        $params['userid'] = $userid;

        // Restrict deletion to course.
        if (!empty($courseid)) {
            $cond[] = " p.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        $where = "WHERE " . implode(" AND ", $cond);

        // ... delete comments.
        $sql = "DELETE c FROM {format_socialwall_comments} c
                JOIN mdl_format_socialwall_posts p ON p.id = c.postid {$where}";

        $DB->execute($sql, $params);

        // ... delete likes.
        $sql = "DELETE l FROM {format_socialwall_likes} l
                JOIN {format_socialwall_posts} p ON p.id = l.postid {$where}";

        $DB->execute($sql, $params);

        // Delete all the activities attached to users posts only?
        self::delete_users_posts_activities($userid, $courseid);

        // ... delete attaches.
        $sql = "DELETE a FROM {format_socialwall_attaches} a
                JOIN {format_socialwall_posts} p ON p.id = a.postid {$where}";

        $DB->execute($sql, $params);

        // ... delete all the enqueued notifications about this post.
        $sql = "DELETE q FROM {format_socialwall_nfqueue} q
                JOIN {format_socialwall_posts} p ON p.id = q.postid {$where}";

        $DB->execute($sql, $params);

        // ... finally delete post.
        $params = array('fromuserid' => $userid);
        if (!empty($courseid)) {
            $params['courseid'] = $courseid;
        }

        $DB->delete_records('format_socialwall_posts', $params);
    }

    /**
     * Delete activities, when course format setting "deletemodspermanently" is set
     * to yes and the activitie is not attached to another post.
     *
     * @param int $pid, the id of the post.
     * @return boolean, true when at least one acitivity is deleted.
     */
    protected static function delete_posts_activities($pid) {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/format/lib.php');

        // Get the course and check, whether the format is "socialwall".
        $sql = "SELECT c.* FROM {course} c
                JOIN {format_socialwall_posts} p ON p.courseid = c.id
                WHERE p.id = :pid AND c.format = 'socialwall'";

        if (!$course = $DB->get_record_sql($sql, array('pid' => $pid))) {
            return false;
        }

        $course = course_get_format($course)->get_course();

        if (empty($course->deletemodspermanently)) {
            return false;
        }

        $sql = "SELECT a.coursemoduleid, count(*) as countmod
                 FROM {format_socialwall_attaches} a
                 JOIN {format_socialwall_posts} p ON (p.id = a.postid AND p.courseid = :courseid)
                 JOIN {format_socialwall_attaches} a2 ON (a2.coursemoduleid = a.coursemoduleid AND a2.postid = :pid)
                 GROUP BY coursemoduleid
                 HAVING countmod = '1'";

        if (!$modcounts = $DB->get_records_sql($sql, array('courseid' => $course->id, 'pid' => $pid))) {
            return false;
        }

        $cmids = array_keys($modcounts);

        foreach ($cmids as $cmid) {
            course_delete_module($cmid);
        }

        return true;
    }

    /**
     * Delete all data related to a post
     *
     * @param int $pid the id of the post
     */
    protected static function execute_delete($pid) {
        global $DB;

        // ... delete comments.
        $DB->delete_records('format_socialwall_comments', array('postid' => $pid));

        // ... delete likes.
        $DB->delete_records('format_socialwall_likes', array('postid' => $pid));

        // ... delete activities attached to this post only?
        self::delete_posts_activities($pid);

        // ... delete attaches.
        $DB->delete_records('format_socialwall_attaches', array('postid' => $pid));

        // ... delete all the enqueued notifications.
        $DB->delete_records('format_socialwall_nfqueue', array('postid' => $pid));

        // ... delete post.
        $DB->delete_records('format_socialwall_posts', array('id' => $pid));
    }

    /**
     * Refresh the value of the countcomments column in format_socialwall table
     *
     * @param int $postid
     */
    public function refresh_comments_count($postid) {
        global $DB;

        if ($post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {

            $post->countcomments = $DB->count_records('format_socialwall_comments',
                    array('postid' => $postid, 'replycommentid' => '0'));

            $DB->update_record('format_socialwall_posts', $post);
            return $post;
        }
        return false;
    }

    /**
     * Refresh the value of the countlikes column in format_socialwall table
     *
     * @param int $postid
     */
    public function refresh_likes_count($postid) {
        global $DB;

        if ($post = $DB->get_record('format_socialwall_posts', array('id' => $postid))) {

            $post->countlikes = $DB->count_records('format_socialwall_likes', array('postid' => $postid));

            $DB->update_record('format_socialwall_posts', $post);
            return $post->countlikes;
        }
        return 0;
    }

    /**
     * Save the locked state for a post
     *
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

    /**
     * Save the sticky state for a post
     *
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
