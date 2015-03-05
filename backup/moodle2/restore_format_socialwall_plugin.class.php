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
 * Version details
 *
 * @package    format
 * @subpackage socialwall
 * @copyright  2014 Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class restore_format_socialwall_plugin extends restore_local_plugin {

    protected function define_course_plugin_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('users');

        if ($userinfo) {

            $elename = 'post';
            $elepath = $this->get_pathfor('/posts/post');
            $paths[] = new restore_path_element($elename, $elepath);

            $elename = 'like';
            $elepath = $this->get_pathfor('/posts/post/likes/like');
            $paths[] = new restore_path_element($elename, $elepath);

            $elename = 'comment';
            $elepath = $this->get_pathfor('/posts/post/comments/comment');
            $paths[] = new restore_path_element($elename, $elepath);

            $elename = 'nfsetting';
            $elepath = $this->get_pathfor('/nfsettings/nfsetting');
            $paths[] = new restore_path_element($elename, $elepath);
        }

        return $paths;
    }

    /**
     * process restore of posts.
     */
    public function process_post($data) {
        global $DB;

        $data = (object) $data;

        // ... remember oldid for mapping.
        $oldid = $data->id;

        $data->courseid = $this->task->get_courseid();
        $data->fromuserid = $this->get_mappingid('user', $data->fromuserid);
        $data->togroupid = $this->get_mappingid('group', $data->togroupid);
        $data->timemodified = time();
        $newid = $DB->insert_record('format_socialwall_posts', $data);

        $this->set_mapping('socialwallpost', $oldid, $newid);
    }

    /**
     * process restore of likes.
     */
    public function process_like($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->postid = $this->get_mappingid('socialwallpost', $data->postid);
        $data->fromuserid = $this->get_mappingid('user', $data->fromuserid);

        $DB->insert_record('format_socialwall_likes', $data);
    }

    /**
     * process restore of comments.
     */
    public function process_comment($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->postid = $this->get_mappingid('socialwallpost', $data->postid);
        $data->fromuserid = $this->get_mappingid('user', $data->fromuserid);

        $DB->insert_record('format_socialwall_comments', $data);
    }

    /**
     * process restore of notification settings.
     */
    public function process_nfsetting($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid);

        $DB->insert_record('format_socialwall_nfsettings', $data);
    }

    protected function define_module_plugin_structure() {

        $paths = array();

        $userinfo = $this->get_setting_value('users');

        if ($userinfo) {

            $elename = 'attachment';
            $elepath = $this->get_pathfor('/attachments/attachment');
            $paths[] = new restore_path_element($elename, $elepath);
        }

        return $paths; // And we return the interesting paths.
    }

    /**
     * process restore of attachment information.
     */
    public function process_attachment($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->postid = $this->get_mappingid('socialwallpost', $data->postid);
        $data->coursemoduleid = $this->task->get_moduleid();

        $DB->insert_record('format_socialwall_attaches', $data);
    }

}