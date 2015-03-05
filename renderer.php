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
 * Renderer for outputting the socialwall course format.
 *
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/topics/renderer.php');
require_once($CFG->dirroot . '/course/format/socialwall/pages/comment_form.php');

/**
 * Basic renderer for socialwall format.
 *
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_socialwall_renderer extends format_topics_renderer {

    protected $commentsformshow = 0;

    /** get data for user and render author div to display author 
     * 
     * @global object $OUTPUT
     * @param int $userid
     * @param array $authors list of existing autors
     * @return array userdata of author and  HTML String for displaying users picture and name.
     */
    private function get_timeline_author($userid, $authors) {
        global $OUTPUT;

        // ... setup user for display.
        if (isset($authors[$userid])) {

            // ...existing user.
            $postauthor = $authors[$userid];

            $userpicture = new \user_picture($postauthor);
            $o = $OUTPUT->render($userpicture);
        } else {

            // ... user doesn't exist, i. e. is unknown.
            $postauthor = (object) array('firstname' => '', 'lastname' => get_string('unknownuser', 'format_socialwall'),
                        'firstnamephonetic' => '', 'lastnamephonetic' => '', 'middlename' => '', 'alternatename' => '');

            $attributes = array('src' => $OUTPUT->pix_url('u/user35'));
            $o = html_writer::empty_tag('img', $attributes);
        }

        $o = html_writer::tag('div', $o, array('class' => 'tl-author'));
        return array($postauthor, $o);
    }

    /** renders the string for displaying how long ago is a comment posted
     * 
     * @param int $time timestamp for posted time
     * @return string
     */
    protected function render_timeline_comment_ago($time) {

        $starttime = $time;
        $endtime = time();

        $seconds = $endtime - $starttime;
        if ($seconds < 60) {
            return get_string('timeagosec', 'format_socialwall', $seconds);
        }

        $minutes = floor($seconds / 60);

        if ($minutes < 60) {
            return get_string('timeagomin', 'format_socialwall', $minutes);
        }

        $hours = floor($minutes / 60);
        $minutes = $minutes % 60;

        if ($hours < 24) {
            return get_string('timeagohours', 'format_socialwall', array('minutes' => $minutes, 'hours' => $hours));
        }

        $days = floor($hours / 24);
        $hours = $hours % 24;

        if ($days < 30) {
            return get_string('timeagodays', 'format_socialwall', array('days' => $days, 'hours' => $hours));
        }

        // ... must be a little different calculated.
        $start = new DateTime();
        $start->setTimestamp($starttime);

        $end = new DateTime();
        $end->setTimestamp($endtime);

        $interval = $start->diff($end);

        if ($days < 300) {
            return $interval->format(get_string('timeagomonthdays', 'format_socialwall'));
        }

        return $interval->format(get_string('timeagoyearsdays', 'format_socialwall'));
    }

    /** renders a timeline comment
     * 
     * @global object $OUTPUT
     * @global record $USER
     * @param record $comment
     * @param array $authors the already retrieved authors for posts and comments
     * @param object $coursecontext
     * @return string HTML for the comment
     */
    protected function render_timeline_comment($comment, $authors,
                                               $coursecontext) {
        global $OUTPUT, $USER;

        list($commentauthor, $o) = $this->get_timeline_author($comment->fromuserid, $authors);

        $dl = '';

        $candeletecomment = ($comment->fromuserid == $USER->id);
        $candeletecomment = ($candeletecomment and has_capability('format/socialwall:deleteowncomment', $coursecontext));
        $candeletecomment = ($candeletecomment or has_capability('format/socialwall:deleteanycomment', $coursecontext));

        if ($candeletecomment) {

            $urlparams = array(
                'id' => $comment->courseid, 'action' => 'deletecomment',
                'cid' => $comment->id, 'sesskey' => sesskey()
            );

            $url = new moodle_url('/course/format/socialwall/action.php', $urlparams);
            $deleteicon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
            $deletelink = html_writer::link($url, $deleteicon, array('id' => 'tldeletecomment_' . $comment->id));
            $dl = html_writer::tag('span', $deletelink, array('class' => 'tl-action-icons'));
        }

        $c = html_writer::tag('div', fullname($commentauthor) . $dl, array('class' => 'tl-authorname'));
        $c .= html_writer::tag('div', $comment->text);
        $c .= html_writer::tag('span', $this->render_timeline_comment_ago($comment->timecreated), array('class' => 'tl-timeago'));

        $o .= html_writer::tag('div', $c, array('class' => 'tl-text'));
        return html_writer::tag('li', $o, array('class' => 'tl-comment', 'id' => 'tlcomment_' . $comment->id));
    }

    /** renders all the comments for a post
     * 
     * @param record $post
     * @param array $authors the already retrieved authors for posts and comments
     * @param course_context $context
     * @return string HTML al all comment for one post
     */
    protected function render_timeline_comments($post, $authors, $context) {

        $o = '';
        if (!empty($post->comments)) {

            foreach ($post->comments as $comment) {

                $o .= $this->render_timeline_comment($comment, $authors, $context);
            }
        }

        return $o;
    }

    /** render a form in the timeline
     * 
     * @param int $courseid
     * @param string $fields HTML for the formelements.
     * @param array $params params for the form tag.
     * @return string
     */
    protected function render_timeline_form($courseid, $fields,
                                            $params = array()) {

        $params['method'] = 'post';

        $f = html_writer::start_tag('form', $params);
        $f .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $courseid));
        $f .= $fields;
        $f .= html_writer::end_tag('form');

        return $f;
    }

    /** render grades, if timeline post contains gradable modules
     * 
     * @global record $USER
     * @param array $authors the already retrieved authors for posts and comments
     * @param record $gradedata
     * @return string HTML for grading information
     */
    protected function render_timeline_grades($authors, $gradedata) {
        global $USER;

        if (!$usergrade = $gradedata->grades[$USER->id]) {
            return '';
        }

        list($grader, $o) = $this->get_timeline_author($usergrade->usermodified, $authors);
        $a = new stdClass();
        $a->name = $gradedata->name;
        $a->result = $usergrade->str_long_grade;
        $c = html_writer::tag('div', fullname($grader));
        $c .= html_writer::tag('div', get_string('gradednote', 'format_socialwall', $a));
        $c .= html_writer::tag('div', $usergrade->feedback);
        $o .= html_writer::tag('div', $c);

        return html_writer::tag('li', $o, array('class' => 'tl_gradednote'));
    }

    /** renders a timeline post.
     * 
     * @global object $OUTPUT
     * @global record $USER
     * @param record $course
     * @param record $post
     * @param object $completion
     * @param array $authors the already retrieved authors for posts and comments
     * @return string HTML for post
     */
    protected function render_timeline_post($course, $post, $completion,
                                            $authors) {
        global $OUTPUT, $USER;

        $coursecontext = context_course::instance($post->courseid);

        list($postauthor, $l) = $this->get_timeline_author($post->fromuserid, $authors);

        $o = html_writer::tag('div', $l, array('class' => 'tl-leftcol'));

        // ... determine group for headline.
        if (empty($post->togroupid)) {

            $to = get_string('allparticipants');
        } else {

            $groups = groups_get_all_groups($post->courseid);
            $to = (isset($groups[$post->togroupid])) ?
                    get_string('group') . ' ' . $groups[$post->togroupid]->name :
                    get_string('nonexistinggroup', 'format_socialwall');
        }

        $date = userdate($post->timecreated);
        $authorspan = html_writer::tag('span', fullname($postauthor), array('class' => 'tl-authorname'));
        $headline = get_string('postedonto', 'format_socialwall', array('author' => $authorspan, 'date' => $date, 'to' => $to));

        // ... add delete icon, if delete is possible.
        $candeletepost = (($post->fromuserid == $USER->id) and (has_capability('format/socialwall:deleteownpost', $coursecontext)));
        $candeletepost = ($candeletepost or has_capability('format/socialwall:deleteanypost', $coursecontext));

        if ($candeletepost) {

            $url = new moodle_url('/course/format/socialwall/action.php',
                            array('id' => $post->courseid, 'action' => 'deletepost', 'pid' => $post->id, 'sesskey' => sesskey()));
            $deletelink = html_writer::link($url, $OUTPUT->pix_icon('t/delete', get_string('delete')));

            $headline .= html_writer::tag('span', $deletelink, array('class' => 'tl-action-icons'));
        }

        // ...
        if (!empty($post->posttext)) {
            // ...user with cap format/socialwall:posthtml should be able to html.
            $headline .= html_writer::tag('div', format_text($post->posttext), array('class' => 'tl-posttext'));
        }

        if (!empty($post->attaches)) {

            $modinfo = get_fast_modinfo($post->courseid);

            $modulehtml = '';
            foreach ($post->attaches as $attachment) {

                $cm = $modinfo->get_cm($attachment->coursemoduleid);
                $modulehtml .= $this->courserenderer->course_section_cm_list_item($course, $completion, $cm, 0);

                if (isset($post->grades[$attachment->coursemoduleid])) {
                    $modulehtml .= $this->render_timeline_grades($authors, $post->grades[$attachment->coursemoduleid]);
                }
            }

            if (!empty($modulehtml)) {
                $headline .= html_writer::tag('ul', $modulehtml, array('class' => 'section tl-postattachment'));
            }
        }

        $p = '';
        if (has_capability('format/socialwall:lockcomment', $coursecontext)) {

            $class = (!empty($post->locked)) ? 'locked' : 'unlocked';

            $urlparams = array(
                'id' => $post->courseid, 'postid' => $post->id, 'action' => 'lockpost',
                'locked' => empty($post->locked), 'sesskey' => sesskey()
            );

            $url = new moodle_url('/course/format/socialwall/action.php', $urlparams);

            if (!empty($post->locked)) {

                $pixicon = $OUTPUT->pix_icon('lockedpost', get_string('unlockpost', 'format_socialwall'), 'format_socialwall');
            } else {

                $pixicon = $OUTPUT->pix_icon('unlockedpost', get_string('lockpost', 'format_socialwall'), 'format_socialwall');
            }

            $link = html_writer::link($url, $pixicon, array('id' => 'lockpost_' . $post->id, 'class' => $class));
            $p .= html_writer::tag('div', $link, array('class' => 'tl-locked'));
        }

        if (has_capability('format/socialwall:makesticky', $coursecontext)) {

            $urlparams = array(
                'id' => $post->courseid, 'postid' => $post->id, 'action' => 'makesticky',
                'sticky' => empty($post->sticky), 'sesskey' => sesskey()
            );

            $url = new moodle_url('/course/format/socialwall/action.php', $urlparams);

            if (!empty($post->sticky)) {

                $pixicon = $OUTPUT->pix_icon('stickypost', get_string('makeunsticky', 'format_socialwall'), 'format_socialwall');
            } else {

                $pixicon = $OUTPUT->pix_icon('unstickypost', get_string('makesticky', 'format_socialwall'), 'format_socialwall');
            }

            $link = html_writer::link($url, $pixicon);
            $p .= html_writer::tag('div', $link, array('class' => 'tl-sticky'));
        } else {

            // ...cannot edit stickyness of post.
            if (!empty($post->sticky)) {

                $pixicon = $OUTPUT->pix_icon('stickypost', get_string('sticky', 'format_socialwall'), 'format_socialwall');
                $p .= html_writer::tag('div', $pixicon, array('class' => 'tl-sticky'));
            }
        }

        $p .= html_writer::tag('div', $headline);

        $countoutput = '';
        if (!empty($course->enablelikes) and has_capability('format/socialwall:viewlikes', $coursecontext)) {

            $countlikessstr = get_string('countlikes', 'format_socialwall', $post->countlikes);
            $countoutput .= html_writer::tag('span', $countlikessstr, array('id' => 'tlcountlikes_' . $post->id));
        }

        $countcommentsstr = get_string('countcomments', 'format_socialwall', $post->countcomments);
        $countoutput .= html_writer::tag('span', $countcommentsstr, array('id' => 'tlcountcomments_' . $post->id));

        $actionarea = html_writer::tag('div', $countoutput, array('class' => 'tl-counts'));

        $actionlink = '';
        if (!empty($course->enablelikes) and has_capability('format/socialwall:like', $coursecontext)) {

            $class = (!empty($post->userlike)) ? 'likenomore' : 'like';

            $urlparams = array(
                'id' => $post->courseid, 'postid' => $post->id, 'action' => 'likepost',
                'userlike' => empty($post->userlike), 'sesskey' => sesskey()
            );

            $url = new moodle_url('/course/format/socialwall/action.php', $urlparams);
            $urlparams = array('class' => $class, 'id' => "userlike_{$post->id}");
            $actionlink .= html_writer::link($url, get_string($class, 'format_socialwall'), $urlparams);
        }

        $capcomment = (has_capability('format/socialwall:writecomment', $coursecontext));
        if ($capcomment) {

            $style = (empty($post->locked)) ? '' : 'display:none';

            $urlparams = array('id' => $post->courseid, 'postid' => $post->id, 'commentsformshow' => $post->id);
            $url = new moodle_url('/course/view.php', $urlparams);
            $urltext = get_string('writecomment', 'format_socialwall');
            $actionlink .= html_writer::link($url, $urltext, array('id' => "showcommentform_{$post->id}", 'style' => $style));
        }

        $actionarea .= html_writer::tag('div', $actionlink, array('class' => 'tl-actionlink'));

        if ($capcomment) {

            $url = new moodle_url('/course/format/socialwall/action.php');
            $formparams = array('postid' => $post->id, 'id' => $post->courseid);
            $commentform = new comment_form($url, $formparams, 'post', '', array('id' => 'tlcommentform_' . $post->id));

            if (!$commentform->has_errors() and  $this->commentsformshow != $post->id) {
                 $style = 'display:none';
            }

            $params = array('class' => 'tl-commentform', 'id' => "tlcommentformwrap_{$post->id}", 'style' => $style);

            $actionarea .= html_writer::tag('div', $commentform->render(), $params);
        }

        $p .= html_writer::tag('div', $actionarea, array('class' => 'tl-post-actionarea'));

        // ... print out all comments.
        $c = $this->render_timeline_comments($post, $authors, $coursecontext);
        $p .= html_writer::tag('ul', $c, array('class' => 'tl-comments', 'id' => 'tlcomments_' . $post->id));

        $morecommentscount = $post->countcomments - $course->tlnumcomments;
        if ($morecommentscount > 0) {

            $url = new moodle_url('/course/format/socialwall/action.php');
            $strmore = get_string('showall', 'format_socialwall', $morecommentscount);
            $l = html_writer::link('#', $strmore, array('id' => 'tlshowall_' . $post->id));
            $p .= html_writer::tag('div', $l, array('class' => 'tl-showall'));
        }

        $o .= html_writer::tag('div', $p, array('class' => 'tl-text'));

        $text = html_writer::tag('li', $o, array('class' => 'tl-post'));
        return filter_manager::instance()->filter_text($text, $coursecontext);
    }

    /** renders a form for filtering and ording the timeline posts
     * 
     * @global record $USER
     * @global object $OUTPUT
     * @param record $course
     * @param record $filteroptions
     * @return string HTML for the form.
     */
    protected function render_timeline_filterform($course, $filteroptions) {
        global $USER, $OUTPUT;

        if (!empty($filteroptions->showalert)) {

            $content = html_writer::tag('h4', get_string('showalert', 'format_socialwall'));
            $url = new moodle_url('/course/view.php', array('id' => $course->id));

            $content .= $OUTPUT->single_button($url, get_string('showallposts', 'format_socialwall'));

            return $content;
        }

        // Filter by group.
        $coursecontext = context_course::instance($course->id);

        if ((groups_get_course_groupmode($course) == SEPARATEGROUPS)
                && (!has_capability('moodle/course:managegroups', $coursecontext))) {

            $allgroups = groups_get_all_groups($course->id, $USER->id);
            $alllabel = get_string('allmygroups', 'format_socialwall');
        } else {

            $allgroups = groups_get_all_groups($course->id);
            $alllabel = get_string('allparticipants');
        }

        $f = '';
        if (!empty($allgroups)) {
            $groupsmenu = array();
            foreach ($allgroups as $gid => $unused) {
                $groupsmenu[$gid] = format_string($allgroups[$gid]->name);
            }
            $f = html_writer::select($groupsmenu, 'tl_filtergroup', $filteroptions->filtergroups, array('' => $alllabel));
        }

        // ... create select for module type.
        $modinfo = get_fast_modinfo($course);
        $modulenames = $modinfo->get_used_module_names();

        $nothing = array('' => get_string('allmodultypes', 'format_socialwall'));
        $f .= html_writer::select($modulenames, 'tl_filtermodules', $filteroptions->filtermodules, $nothing);

        // ... order by date.
        $options = array();
        $options['timecreated asc'] = get_string('timecreateasc', 'format_socialwall');

        $nothing = array('timecreated desc' => get_string('timecreatedesc', 'format_socialwall'));
        $f .= html_writer::select($options, 'tl_orderby', $filteroptions->orderby, $nothing);

        $inputparams = array(
            'type' => 'submit', 'name' => 'filter',
            'value' => get_string('filtertimeline', 'format_socialwall')
        );
        $f .= html_writer::empty_tag('input', $inputparams);

        return $this->render_timeline_form($course->id, $f);
    }

    /** print out the first section (i. e. section number 0) for the course
     * 
     * @param record $course
     * @param section_info $sectioninfo
     */
    protected function print_first_section($course, $sectioninfo) {

        if (!isset($sectioninfo[0])) {
            return false;
        }

        $thissection = $sectioninfo[0];

        echo $this->section_header($thissection, $course, false, 0);
        echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
        echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
        echo $this->section_footer();
    }

    /** print the section which contains the post form (i. e. section number 2)
     * 
     * @global record $USER
     * @global record $PAGE
     * @global object $OUTPUT
     * @param record $course
     * @param object $sectioninfo
     * @param moodle_form $postform
     */
    protected function print_postform_section($course, $sectioninfo, $postform) {
        global $USER, $PAGE, $OUTPUT;

        $o = '';

        // ... usually this is section 2, because activities on time line are required to be in section 1.
        if (isset($sectioninfo[FORMAT_SOCIALWALL_POSTFORMSECTION])) {

            $thissection = $sectioninfo[FORMAT_SOCIALWALL_POSTFORMSECTION];

            $o .= html_writer::start_tag('li', array('id' => 'section-' . $thissection->section,
                        'class' => 'section main clearfix', 'role' => 'region',
                        'aria-label' => get_section_name($course, $thissection)));

            $o .= html_writer::start_tag('div', array('class' => 'content'));

            $url = new moodle_url('/course/format/socialwall/pages/editnotification.php', array('courseid' => $course->id));

            $linktext = $OUTPUT->pix_icon('i/settings', get_string('editnotification', 'format_socialwall')) . " " .
                    get_string('editnotification', 'format_socialwall');

            $linkparams = array('class' => 'pf-notificationsetting', 'id' => 'pfnotificationsetting_' . $course->id);
            $o .= html_writer::link($url, $linktext, $linkparams);

            $o .= html_writer::tag('div', '', array('class' => 'clearfix'));

            $o .= $postform->render();

            if ($PAGE->user_allowed_editing()) {

                $o .= html_writer::tag('div', '', array('class' => 'clearfix'));

                $thissection = $sectioninfo[FORMAT_SOCIALWALL_POSTFORMSECTION];

                if (empty($thissection->sequence)) {

                    if ($USER->editing) {

                        $o .= html_writer::start_div('attachactivies');
                        $o .= get_string('attachactivities', 'format_socialwall');
                    } else {

                        $o .= html_writer::start_div('');
                    }
                } else {

                    if ($USER->editing) {

                        $o .= html_writer::start_div('attachactivies');
                        $o .= get_string('attachactivities', 'format_socialwall');
                        $o .= html_writer::empty_tag('br') . get_string('attachedactivities', 'format_socialwall');
                    } else {

                        $o .= html_writer::start_div('');
                        $o .= get_string('attachedactivities', 'format_socialwall');
                    }
                }

                $o .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                $o .= $this->courserenderer->course_section_add_cm_control($course, FORMAT_SOCIALWALL_POSTFORMSECTION, 0);
                $o .= html_writer::end_div();
            }

            $o .= $this->section_footer();
        }

        echo $o;
    }

    /** print out the timeline section (i. e. section number 1)
     * 
     * @param record $course
     * @param record $postsdata all the gathered data to print posts
     * @param object $completion completion info of course
     */
    protected function print_timeline_section($course, $postsdata, $completion) {

        echo $this->render_timeline_filterform($course, $postsdata->filteroptions);

        $countdata = array('total' => $postsdata->poststotal, 'count' => count($postsdata->posts));
        $countstr = get_string('counttotalpost', 'format_socialwall', $countdata);

        echo html_writer::tag('div', $countstr, array('id' => 'counttotalpost'));

        echo html_writer::start_tag('ul', array('class' => 'tl-posts', 'id' => 'tl-posts'));

        $postshtml = '';
        foreach ($postsdata->posts as $post) {
            $postshtml .= $this->render_timeline_post($course, $post, $completion, $postsdata->authors);
        }

        echo $postshtml;

        // ...do not remove this tag, it is needed for ajax page loading.
        echo html_writer::tag('div', '', array('id' => 'tl-endofposts'));

        echo html_writer::end_tag('ul');
    }

    /** print out the course page
     * 
     * @global record $PAGE
     * @param record $course
     * @param completion_info $completioninfo
     */
    public function print_page($course, $completioninfo) {
        global $PAGE;

        $this->commentsformshow = optional_param('commentsformshow', 0, PARAM_INT);

        $posts = \format_socialwall\local\posts::instance($course->id);

        $postdata = $posts->get_timeline_posts($course);
        $postform = $posts->get_post_form();

        $course = course_get_format($course)->get_course();

        // Title with completion help icon.
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        echo $this->start_section_list();

        $modinfo = get_fast_modinfo($course);
        $sectioninfo = $modinfo->get_section_info_all();

        // ...Section 0.
        $this->print_first_section($course, $sectioninfo);

        // ...Section 2.
        $this->print_postform_section($course, $sectioninfo, $postform);

        // ...Section 1.
        $this->print_timeline_section($course, $postdata, $completioninfo);

        echo $this->end_section_list();

        $args = array(
            'courseid' => $course->id,
            'poststotal' => $postdata->poststotal,
            'postsloaded' => $postdata->postsloaded,
            'userallowedediting' => $PAGE->user_allowed_editing()
        );

        $PAGE->requires->strings_for_js(
                array('counttotalpost', 'like', 'likenomore', 'countlikes', 'countcomments', 'textrequired', 'confirmdeletecomment'), 'format_socialwall');

        $PAGE->requires->yui_module(
                'moodle-format_socialwall-postform', 'M.format_socialwall.postforminit', array($args), null, true);
    }

    /** render the posts loaded by an AJAX call
     * 
     * @param record $course
     * @param record $postsdata data of post for rendering
     * @return string HTML for post
     */
    public function render_ajax_loaded_posts($course, $postsdata) {

        $completion = new completion_info($course);
        $postshtml = '';
        foreach ($postsdata->posts as $post) {
            $postshtml .= $this->render_timeline_post($course, $post, $completion, $postsdata->authors);
        }
        return $postshtml;
    }

    /** render the comment loaded by an AJAX call
     * 
     * @param context_course $context
     * @param record $comment
     * @param record $author
     * @return string HTML for comment
     */
    public function render_ajax_loaded_comment($context, $comment, $author) {

        $authors = array($author->id => $author);
        return $this->render_timeline_comment($comment, $authors, $context);
    }

    /** render the comments loaded by an AJAX call 
     * 
     * @param int $postid id of post
     * @param context_course $context
     * @param record $commentsdata data render comments
     * @return string HTML of comments
     */
    public function render_ajax_loaded_comments($postid, $context, $commentsdata) {

        if (empty($commentsdata->posts[$postid])) {
            return "";
        }

        $post = $commentsdata->posts[$postid];

        return $this->render_timeline_comments($post, $commentsdata->authors, $context);
    }

}