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

/**
 * Provides the information to backup grid course format
 */
class backup_format_socialwall_plugin extends backup_local_plugin {

    /**
     * Returns the format information to attach to course element
     */
    protected function define_course_plugin_structure() {

        $plugin = $this->get_plugin_element();

        $userinfo = $this->get_setting_value('users');

        if ($userinfo) {

            $formatsocialwall = new backup_nested_element($this->get_recommended_name());
            $plugin->add_child($formatsocialwall);

            // ...backup posts.
            $posts = new backup_nested_element('posts');
            $formatsocialwall->add_child($posts);

            $post = new backup_nested_element('post', array(), array('id', 'fromuserid', 'togroupid', 'posttext', 'sticky',
                'private', 'alert', 'locked', 'countcomments', 'countlikes', 'timecreated', 'timemodified'));

            $posts->add_child($post);
            $post->set_source_table('format_socialwall_posts', array('courseid' => backup::VAR_PARENTID));

            // ...backup likes.
            $likes = new backup_nested_element('likes');
            $post->add_child($likes);

            $like = new backup_nested_element('like', array(), array('id', 'postid', 'fromuserid', 'timecreated'));
            $likes->add_child($like);
            $like->set_source_table('format_socialwall_likes', array('postid' => backup::VAR_PARENTID));

            // ...backup comments.
            $comments = new backup_nested_element('comments');
            $post->add_child($comments);

            $comment = new backup_nested_element('comment', array(), array('id', 'postid',
                'replycommentid', 'countreplies', 'fromuserid', 'text', 'timecreated', 'timemodified'));

            $comments->add_child($comment);
            $comment->set_source_table('format_socialwall_comments', array('postid' => backup::VAR_PARENTID));

            // ... backup notification settings.
            $nfsettings = new backup_nested_element('nfsettings');
            $formatsocialwall->add_child($nfsettings);

            $nfsetting = new backup_nested_element('nfsetting', array(), array('userid', 'notificationtype'));
            $nfsettings->add_child($nfsetting);
            $nfsetting->set_source_table('format_socialwall_nfsettings', array('courseid' => backup::VAR_PARENTID));
        }

        return $plugin;
    }

    /**
     * Returns the format information to attach to module element
     */
    protected function define_module_plugin_structure() {

        $plugin = $this->get_plugin_element();
        $userinfo = $this->get_setting_value('users');

        if ($userinfo) {

            $formatsocialwall = new backup_nested_element($this->get_recommended_name());
            $plugin->add_child($formatsocialwall);

            // ...backup attachment information.
            $attachments = new backup_nested_element('attachments');
            $formatsocialwall->add_child($attachments);

            $attachment = new backup_nested_element('attachment', array(), array('postid', 'sortorder'));
            $attachments->add_child($attachment);
            $attachment->set_source_table('format_socialwall_attaches', array('coursemoduleid' => backup::VAR_PARENTID));
        }
        return $plugin;
    }

}
