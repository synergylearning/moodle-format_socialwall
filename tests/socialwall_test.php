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
 * @package   local_impact
 * @copyright 2016 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class format_socialwall_testcase extends advanced_testcase {

    /**
     * Unenrol user from course
     *
     * @global type $DB
     * @param type $user
     * @param type $course1
     */
    private function unenrol_user($userid, $course1id) {
        global $DB;

        // Get all the instances for that course.
        $sql = "SELECT e.*
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid
                WHERE ue.userid = :userid";

        if (!$instances = $DB->get_records_sql($sql, array('userid' => $userid, 'courseid' => $course1id))) {
            return false;
        }

        foreach ($instances as $instance) {
            $plugin = enrol_get_plugin($instance->enrol);
            $plugin->unenrol_user($instance, $userid);
        }
        return true;
    }

    private function create_post_with_attachment($course1id, $userid, $attachmentid) {
        global $DB;

        $post = new stdClass();
        $post->courseid = $course1id;
        $post->fromuserid = $userid;
        $post->togroupid = 0;
        $post->cmsequence = $attachmentid;
        $post->id = 0;
        $post->posttext = "Test Post";
        $post->timecreated = time();
        $post->timemodified = $post->timecreated;
        $post->id = $DB->insert_record('format_socialwall_posts', $post);
        \format_socialwall\local\attaches::save_attaches($post->id, $post->cmsequence);

        return $post->id;
    }

    public function test_plugin_installed() {
        $config = get_config('format_socialwall');
        $this->assertTrue(isset($config->enablenotification));
    }

    /**
     * Test whether the params deletemodspermanently and deleteafterunenrol of
     * course format works correctly, when unenrolling a user.
     */
    public function test_unenroluser() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/course/format/socialwall/lib.php');

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();

        // Generate users.
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        // Setup a course with a attendance module inside.
        $record = array('format' => 'socialwall');
        $course1 = $generator->create_course($record);
        $course2 = $generator->create_course($record);

        // Enrol user into course.
        $generator->enrol_user($user1->id, $course1->id, 5);

        // Ensure setting delete "Delete Items after unenrol use" is No.
        $course1 = course_get_format($course1)->get_course();
        $this->assertEmpty($course1->deleteafterunenrol);

        // Create a Post in the course 1 from user1.
        $label1 = $generator->create_module('label', array('course' => $course1));
        $post1id = $this->create_post_with_attachment($course1->id, $user1->id, $label1->id);

        // Create a Post in the course 2 from user1.
        $label2 = $generator->create_module('label', array('course' => $course2));
        $post2id = $this->create_post_with_attachment($course2->id, $user1->id, $label2->id);

        // Delete enrolment.
        $this->unenrol_user($user1->id, $course1->id);

        // Check post.
        $count = $DB->count_records('format_socialwall_posts', array('fromuserid' => $user1->id));
        $this->assertEquals(2, $count);
        // Check Label.
        $count = $DB->count_records('label');
        $this->assertEquals(2, $count);

        // Try with different setting.
        $generator->enrol_user($user1->id, $course1->id, 5);

        // Set deleteafterunenrol.
        $DB->set_field('course_format_options', 'value', 1, array('format' => 'socialwall', 'name' => 'deleteafterunenrol'));

        format_socialwall::reset_course_cache();
        $course1 = course_get_format($course1)->get_course();
        $this->assertEquals(1, $course1->deleteafterunenrol);

        // Set deletemodspermanently.
        $DB->set_field('course_format_options', 'value', 1, array('format' => 'socialwall', 'name' => 'deletemodspermanently'));

        format_socialwall::reset_course_cache();
        $course1 = course_get_format($course1)->get_course();
        $this->assertEquals(1, $course1->deletemodspermanently);

        // Delete enrolment.
        $this->unenrol_user($user1->id, $course1->id);

        // Check post.
        $count = $DB->count_records('format_socialwall_posts', array('fromuserid' => $user1->id, 'courseid' => $course1->id));
        $this->assertEquals(0, $count);

        $count = $DB->count_records('label', array('id' => $label1->id));
        $this->assertEquals(1, $count);

        $count = $DB->count_records('format_socialwall_posts', array('fromuserid' => $user1->id, 'courseid' => $course2->id));
        $this->assertEquals(1, $count);

        $count = $DB->count_records('label', array('id' => $label2->id));
        $this->assertEquals(1, $count);
    }

}
