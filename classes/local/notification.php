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

define('SOCIALWALL_NOTIFICATION_NO', 0);
define('SOCIALWALL_NOTIFICATION_INSTANT', 1);
define('SOCIALWALL_NOTIFICATION_DIGEST', 2);

define('NOTIFICATION_PENDING', 0);
define('NOTIFICATION_DONE', 1);

define('DEBUG_MESSAGE', 0);

/**
 * 1- Notification uses intentionally an internal queue, so notification will be
 * independent from logging setting and would work even if log data wouldn't be stored.
 *
 * 2- In a first approach Notification uses a instant (direct function call) event
 * handling. This can be easily changed to hook into events api, if you are
 * interested in logging this events.
 *
 * 3- events list:
 * postcreated
 * postlocked/postunlocked
 * commentcreated
 * likedeleted
 * likecreated
 */
class notification {

    protected $courses = array();
    protected $notificationsplaintext = array();
    protected $users = array();
    public static $NOTIFICATION_TYPE = array(
        SOCIALWALL_NOTIFICATION_NO => 'notificationoff',
        SOCIALWALL_NOTIFICATION_INSTANT => 'notificationperpost',
        SOCIALWALL_NOTIFICATION_DIGEST => 'notificationdigest');

    /** create instance as a singleton */
    public static function instance() {
        static $notification;

        if (isset($notification)) {
            return $notification;
        }

        $notification = new notification();
        return $notification;
    }

    /** write a record into database for later messaging via message provider "timelineposts"
     *  recipientid = 0 means, this message is sent to all participiants of the course,
     *  which have sufficient capabilities to receive it.
     *
     * @global object $DB
     * @global object $USER
     * @param record $post
     * @return boolean
     */
    public static function enqueue_post_created($post) {
        global $DB, $USER;

        $course = course_get_format($post->courseid)->get_course();
        if (empty($course->enablenotification)) {
            return false;
        }

        $eventdata = new \stdClass();
        $eventdata->creatorid = $USER->id;
        $eventdata->courseid = $post->courseid;
        $eventdata->recipientid = 0; // Post to all.
        $eventdata->postid = $post->id;
        $eventdata->module = 'post';
        $eventdata->action = 'created';
        $eventdata->details = $post->id;
        $eventdata->time = time();
        $DB->insert_record('format_socialwall_nfqueue', $eventdata);
        return true;
    }

    /** write a record into database for later messaging via message provider "timelineposts"
     *  recipientid = 0 means, this message is sent to all participiants of the course,
     *  which have sufficient capabilities to receive it.
     *
     * @global object $DB
     * @global object $USER
     * @param record $post
     * @return boolean
     */
    public static function enqueue_post_locked($post) {
        global $DB, $USER;

        $course = course_get_format($post->courseid)->get_course();
        if (empty($course->enablenotification)) {
            return false;
        }

        $eventdata = new \stdClass();
        $eventdata->creatorid = $USER->id;
        $eventdata->courseid = $post->courseid;
        $eventdata->recipientid = 0; // Post to all.
        $eventdata->postid = $post->id;
        $eventdata->module = 'post';
        if ($post->locked) {
            $eventdata->action = 'locked';
        } else {
            $eventdata->action = 'unlocked';
        }
        $eventdata->details = $post->id;
        $eventdata->time = time();
        $DB->insert_record('format_socialwall_nfqueue', $eventdata);
        return true;
    }

    /** write a record into database for later messaging via message provider "timelineposts"
     *  recipientid = 0 means, this message is sent to all participiants of the course,
     *  which have sufficient capabilities to receive it.
     *
     * @global object $DB
     * @global object $USER
     * @param record $post
     * @return boolean
     */
    public static function enqueue_comment_created($comment) {
        global $DB, $USER;

        $course = course_get_format($comment->courseid)->get_course();
        if (empty($course->enablenotification)) {
            return false;
        }

        $eventdata = new \stdClass();
        $eventdata->creatorid = $USER->id;
        $eventdata->courseid = $comment->courseid;
        $eventdata->recipientid = 0; // Post to all.
        $eventdata->postid = $comment->postid;
        $eventdata->module = 'comment';
        $eventdata->action = 'created';
        $eventdata->details = $comment->id;
        $eventdata->time = time();
        $DB->insert_record('format_socialwall_nfqueue', $eventdata);

        return true;
    }

    /** write a record into database for later messaging via message provider "timelineposts"
     *  recipientid = 0 means, this message is sent to all participiants of the course,
     *  which have sufficient capabilities to receive it.
     *
     * @global object $DB
     * @global object $USER
     * @param record $post
     * @return boolean
     */
    public static function enqueue_like_created($post) {
        global $DB, $USER;

        $course = course_get_format($post->courseid)->get_course();
        if (empty($course->enablenotification) or empty($course->enablelikes)) {
            return false;
        }

        $eventdata = new \stdClass();
        $eventdata->creatorid = $USER->id;
        $eventdata->courseid = $post->courseid;
        $eventdata->recipientid = 0; // Post to all.
        $eventdata->postid = $post->id;
        $eventdata->module = 'like';
        $eventdata->action = 'created';
        $eventdata->details = $post->id;
        $eventdata->time = time();
        $DB->insert_record('format_socialwall_nfqueue', $eventdata);
    }

    /** write a record into database for later messaging via message provider "timelineposts"
     *  recipientid = 0 means, this message is sent to all participiants of the course,
     *  which have sufficient capabilities to receive it.
     *
     * @global object $DB
     * @global object $USER
     * @param record $post
     * @return boolean
     */
    public static function enqueue_like_deleted($post) {
        global $DB, $USER;

        $course = course_get_format($post->courseid)->get_course();
        if (empty($course->enablenotification) or empty($course->enablelikes)) {
            return false;
        }

        $eventdata = new \stdClass();
        $eventdata->creatorid = $USER->id;
        $eventdata->courseid = $post->courseid;
        $eventdata->recipientid = 0; // Post to all.
        $eventdata->postid = $post->id;
        $eventdata->module = 'like';
        $eventdata->action = 'deleted';
        $eventdata->details = $post->id;
        $eventdata->time = time();
        $DB->insert_record('format_socialwall_nfqueue', $eventdata);
        return true;
    }

    /** get enqueued notifications for all users
     *
     * @global object $DB
     * @param int $starttime, begin of period to retrieve notifications
     * @param int $endtime,  end of period to retrieve notifications
     * @return array of notification with data from relating post.
     */
    protected static function get_enqueued_notifications($starttime, $endtime) {
        global $DB;

        $sql = "SELECT q.*, p.posttext, p.private, p.togroupid
                FROM {format_socialwall_nfqueue} q
                JOIN {format_socialwall_posts} p ON p.id = q.postid
                WHERE time >= ? AND time < ? AND recipientid = '0'";

        if (!$notifications = $DB->get_records_sql($sql, array($starttime, $endtime))) {
            return array();
        }

        return $notifications;
    }

    /** print out all users for debuggin purposes
     *
     * @param type $users
     */
    protected function print_user($users) {
        foreach ($users as $user) {
            echo fullname($user) . "<br >";
        }
    }

    /** get all the users, which are able to receive the given notification (includes
     * checking some capabilities).
     *
     * @param type $notification
     * @param type $course
     * @param type $context
     * @return array, list of recipients of notification
     */
    protected function get_users_to_notify($notification, $course, $context) {

        $groupid = $notification->togroupid;

        // If post is visible for a specail group, check groupmode.
        if ($groupid != 0) {

            $groupmode = groups_get_course_groupmode($course);

            if ($groupmode != SEPARATEGROUPS) {

                $groupid = 0;
            }
        }

        $capability = array();
        if ($notification->private == 1) {
            $capability[] = 'format/socialwall:viewprivate';
        }

        if ($notification->module == 'like') {
            $capability[] = 'format/socialwall:viewlikes';
        }

        // ... regarding perfomance we do different retrieve of users.
        // ... no cap optional group.
        if (empty($capability)) {

            if (!$users = get_enrolled_users($context, '', $groupid)) {
                $users = array();
            }

            // Add users with group access, if groups are separated and a group is
            // specified.
            if ($groupid != 0) {
                if ($canseegroups = get_enrolled_users($context, 'moodle/course:managegroups')) {
                    $users = array_merge($users, $canseegroups);
                }
            }

            if (DEBUG_MESSAGE == 1) {
                echo ("<p><b>--- no cap, group: {$groupid}</b></p>");
                // ...print_object($notification).
                echo ('<br>Users:<br>');
                $this->print_user($users);
            }
            return $users;
            // ... one cap, no groups.
        } else if ((count($capability) == 1) and ($groupid == 0)) {

            if (!$users = get_enrolled_users($context, $capability[0])) {
                $users = array();
            }

            if (DEBUG_MESSAGE == 1) {
                echo ("<p><b>--- one cap {$capability[0]}, group: {$groupid}</b></p>");
                // ...print_object($notification).
                echo ('<br>Users:<br>');
                $this->print_user($users);
            }
            return $users;
            // ...more the one cap and group.
        } else {
            $groups = ($groupid == 0) ? '' : $groupid;

            if (!$users = get_users_by_capability($context, $capability, 'u.*', '', '', '', $groups, '', null, null, true)) {
                return array();
            }

            if (DEBUG_MESSAGE == 1) {
                echo ("<p><b>--- cap " . implode(", ", $capability) . ", group: {$groupid}</b></p>");
                // ...print_object($notification).
                echo ('<br>Users:<br>');
                $this->print_user($users);
            }
            return $users;
        }

        return array();
    }

    /** get and cache the course
     *
     * @global object $DB
     * @param int $courseid, the courseid
     * @return record
     */
    protected function get_course($courseid) {
        global $DB;

        if (isset($this->courses[$courseid])) {
            return $this->courses[$courseid];
        }

        $this->courses[$courseid] = $DB->get_record('course', array('id' => $courseid));
        return $this->courses[$courseid];
    }

    protected function get_user($userid) {
        global $DB;

        if (isset($this->users[$userid])) {
            return $this->users[$userid];
        }

        $this->users[$userid] = $DB->get_record('user', array('id' => $userid));

        return $this->users[$userid];
    }

    /** add notification settings to the given user records
     *
     * @param array $users, array of user records.
     * @param int $courseid, courseid
     * @return int
     */
    protected function add_users_notificationsetting(&$users, $courseid) {

        $settings = $this->get_notification_settings($courseid);

        $counts = array();
        $counts[SOCIALWALL_NOTIFICATION_INSTANT] = 0;
        $counts[SOCIALWALL_NOTIFICATION_DIGEST] = 0;

        foreach ($users as &$user) {

            if (!empty($settings[$user->id])) {

                $user->enablenotification = $settings[$user->id];
                $counts[$settings[$user->id]]++;
            } else {
                $user->enablenotification = 0;
            }
        }

        return $counts[SOCIALWALL_NOTIFICATION_INSTANT];
    }

    protected function get_notifications_plaintext($notification) {
        global $DB;

        if (isset($this->notificationsplaintext[$notification->id][$notification->details])) {
            return $this->notificationsplaintext[$notification->id][$notification->details];
        }

        // ... get user, hopefully from cache.
        $creator = $this->get_user($notification->creatorid);

        $a = new \stdClass();

        $a->creator = fullname($creator);
        $a->posttext = $notification->posttext;

        // If comments then get comments text.
        if ($notification->module == 'comment') {
            $comment = $DB->get_record('format_socialwall_comments', array('id' => $notification->details));
            $a->commenttext = $comment->text;
        }

        $langkeymessage = 'nf_' . $notification->module . '_' . $notification->action;

        if (!isset($this->notificationsplaintext[$notification->id])) {
            $this->notificationsplaintext[$notification->id] = array();
        }

        $this->notificationsplaintext[$notification->id][$notification->details] =
                get_string($langkeymessage, 'format_socialwall', $a);

        return $this->notificationsplaintext[$notification->id][$notification->details];
    }

    /** create a message using notification record
     *
     * @global object $DB
     * @param record $notification
     * @param record $course
     * @return object, eventdata for the message.
     */
    protected function create_message($notification, $course) {
        global $DB;

        $eventdata = new \object();
        // The component sending the message.
        // Along with name this must exist in the table message_providers.
        $eventdata->component = 'format_socialwall';
        // Type of message from that module (as module defines it).
        // Along with component this must exist in the table message_providers.
        $eventdata->name = 'timelineposts';
        // ...user object.
        $eventdata->userfrom = get_admin();
        // ...very short one-line subject.
        $eventdata->subject = get_string('notificationfromcourse', 'format_socialwall', $course->shortname);

        $url = new \moodle_url('/course/view.php', array('id' => $notification->courseid));

        $messagecontent = $this->get_notifications_plaintext($notification);
        $eventdata->fullmessage = $messagecontent . ', ' . $url->out();

        $eventdata->fullmessageformat = FORMAT_PLAIN;   // Text format.
        $courselink = \html_writer::link($url, get_string('nf_gotocourse', 'format_socialwall'));
        $eventdata->fullmessagehtml = $messagecontent . ', ' . $courselink;

        // ...useful for plugins like sms or twitter.
        $eventdata->smallmessage = '';
        return $eventdata;
    }

    /** postpone the notification for digest message
     *
     * @global object $DB
     * @param record $notification
     * @param record $touser
     */
    protected function postpone_notification($notification, $touser) {
        global $DB;

        // Check if it is already there?

        $notification->recipientid = $touser->id;
        $DB->insert_record('format_socialwall_nfqueue', $notification);
    }

    /** cron processing of instant notifications, it is recommended to call this method a cron every 60s.
     *
     *  The Method will do:
     *
     *  1- get all the notifications with recipientid == 0 (which means message is dedicated for all users in course)
     *  2- get all users which can receive the message (regrading capabilities and gorups)
     *  3- send out an instant message, postpone for digest or don't send message depending on users notification settings.
     *
     * Note that checking cap and group is done for postponed notification (notifications with recipientid != 0),
     * so in digest_cron(), we didn't have to check it on more time. :)
     *
     * @global type $CFG
     * @global object $DB
     */
    public function instant_cron() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/accesslib.php');
        $config = get_config('format_socialwall');

        // Posts older than 2 days will not be mailed. This is to avoid the problem where
        // cron has not been running for a long time, and then suddenly people are flooded
        // with mail from the past few weeks or months.
        $timenow = time();
        $starttime = (isset($config->lastinstantcron)) ? $config->lastinstantcron : 0;
        $starttime = max($starttime, $timenow - 48 * 3600);   // Two days earlier.
        // Get all the notifications with dat from post since starttime until start of cron.
        $notifications = $this->get_enqueued_notifications($starttime, $timenow);

        foreach ($notifications as $notification) {

            // It's unlikely but possible there
            // might be an error later so that a post notification is NOT actually send out,
            // but since mail isn't crucial, we can accept this risk.  Doing it now
            // prevents the risk of duplicated notification, which is a worse problem.
            $DB->delete_records_select('format_socialwall_nfqueue', 'id = ?', array($notification->id));
            // Course theme settings must not be checked, because notification
            // would not be queued, when enablenotification is off in theme settings.
            if (!$course = $this->get_course($notification->courseid)) {
                mtrace('Invalid course with id: ' . $notification->courseid);
                continue;
            }

            // ...check existing context.
            if (!$context = \context_course::instance($notification->courseid)) {
                mtrace('Invalid Context for course with id: ' . $notification->courseid);
                continue;
            };

            // ...get all the users (including notification settings) which
            // should be notificated regarding groups, notification type and needed capability.
            $userstonotificate = $this->get_users_to_notify($notification, $course, $context);
            if (empty($userstonotificate)) {
                mtrace('No users found in course with id: ' . $notification->courseid);
                continue;
            };

            // ... get num of instant notes.
            $countinstantnotes = $this->add_users_notificationsetting($userstonotificate, $notification->courseid);

            $eventdata = new \stdClass();

            // ...prepare message, if at lest one message is send.
            if ($countinstantnotes > 0) {
                $eventdata = $this->create_message($notification, $course);
            }

            $sentcount = 0;
            $failedsent = 0;
            $postponed = 0;

            // With each user to notificate do notification or postpone to digest.
            foreach ($userstonotificate as $user) {

                // ...terminate if processing of any account takes longer than 2 minutes.
                \core_php_time_limit::raise(120);

                // ... if user want's digest, create a digest queue entry.
                if ($user->enablenotification == SOCIALWALL_NOTIFICATION_INSTANT) {

                    $eventdata->userto = $user;
                    if (!message_send($eventdata)) {

                        $failedsent++;
                    } else {

                        $sentcount++;
                    }
                } else {
                    if ($user->enablenotification == SOCIALWALL_NOTIFICATION_DIGEST) {
                        $postponed++;
                        $this->postpone_notification($notification, $user);
                    }
                }
            }
            $statstr = "(sent: $sentcount, failed: $failedsent, postponed: $postponed)";
            mtrace('Notification processed: ' . $notification->module . ' ' . $notification->action . $statstr);
            unset($userstonotificate);
        }

        // Release some memory.
        unset($notifications);
        // ...set successfully cron time.

        set_config('lastinstantcron', $timenow, 'format_socialwall');
        mtrace('format_socialwall instant_cron succesfully finished.');
    }

    protected function get_enqueued_digest_notes($starttime, $endtime) {
        global $DB;

        $sql = "SELECT q.*, p.posttext, p.private, p.togroupid
                FROM {format_socialwall_nfqueue} q
                JOIN {format_socialwall_posts} p ON p.id = q.postid
                WHERE time >= ? AND time < ? AND recipientid <> '0'";

        if (!$notifications = $DB->get_records_sql($sql, array($starttime, $endtime))) {
            return array();
        }

        // ... group by recipientid, courseid.
        $groupednotifications = array();
        foreach ($notifications as $notification) {

            if (!isset($groupednotifications[$notification->recipientid])) {
                $groupednotifications[$notification->recipientid] = array();
            }

            if (!isset($groupednotifications[$notification->recipientid][$notification->courseid])) {
                $groupednotifications[$notification->recipientid][$notification->courseid] = array();
            }
            $groupednotifications[$notification->recipientid][$notification->courseid][$notification->id] = $notification;
        }

        return $groupednotifications;
    }

    /** process the digest notifications, this is normally done one time per day.
     *  if you have many notifications, it would be possible to:
     *
     *  1- schedule in night time.
     *  2- to reduce max processed notifications and do cron more times per day.
     *
     */
    public function digest_cron() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/accesslib.php');
        $config = get_config('format_socialwall');

        // Posts older than 2 days will not be mailed. This is to avoid the problem where
        // cron has not been running for a long time, and then suddenly people are flooded
        // with mail from the past few weeks or months.
        $timenow = time();
        $starttime = (isset($config->lastdigestcron)) ? $config->lastdigestcron : 0;
        $starttime = max($starttime, $timenow - 48 * 3600);   // Two days earlier as limit
        // get all the notifications with dat from post since starttime until start of cron.
        $starttime = 0;
        $groupednotifications = $this->get_enqueued_digest_notes($starttime, $timenow);

        $countdigests = 0;
        $counterrors = 0;

        // ... building up notifications text.
        foreach ($groupednotifications as $userid => $usersnotifications) {

            // Terminate if processing of any account takes longer than 2 minutes.
            \core_php_time_limit::raise(120);

            $digesttexthtml = '';
            foreach ($usersnotifications as $courseid => $coursenotifications) {

                $digesttexthtml = '<p> ----------------------------------- </p>';

                foreach ($coursenotifications as $notification) {

                    $plainmessage = $this->get_notifications_plaintext($notification);

                    // ... building up html text.
                    $digesttexthtml .= '<p>' . $plainmessage . '</p>';
                }

                $url = new \moodle_url('/course/view.php', array('id' => $notification->courseid));
                $digesttexthtml .= '<br>' . \html_writer::link($url, get_string('nf_gotocourse', 'format_socialwall'));
            }

            // Create message, delete related notifications from queue and send out the digest
            // to this user.
            $eventdata = new \object();
            // The component sending the message. Along with name this must exist in the table message_providers.
            $eventdata->component = 'format_socialwall';
            // Type of message from that module (as module defines it).
            // Along with component this must exist in the table message_providers.
            $eventdata->name = 'timelineposts';

            $eventdata->userfrom = get_admin();      // User object.
            $eventdata->subject = get_string('timelinedigests', 'format_socialwall');   // Very short one-line subject.

            $eventdata->userto = $this->get_user($userid);

            $eventdata->fullmessage = html_to_text($digesttexthtml);
            $eventdata->fullmessageformat = FORMAT_PLAIN;   // Text format.

            $eventdata->fullmessagehtml = $digesttexthtml;

            // Useful for plugins like sms or twitter.
            $eventdata->smallmessage = '';

            if (message_send($eventdata)) {

                $DB->delete_records_select('format_socialwall_nfqueue', 'recipientid = ?', array($userid));
                $countdigests++;
            } else {
                $counterrors++;
            }
        }

        unset($groupednotifications);
        unset($this->courses);
        unset($this->notificationsplaintext);
        unset($this->users);

        set_config('lastdigestcron', $timenow, 'format_socialwall');
        mtrace('format_socialwall digest_cron successfully finished (' . $countdigests . ' sent, ' . $counterrors . ' Errors).');
    }

    /** save the notification settings for a user
     *
     * @global object $DB
     * @param record $data, the submitted data.
     */
    public function save_from_submit($data) {
        global $DB;

        $params = array('courseid' => $data->courseid, 'userid' => $data->userid);

        if ($exists = $DB->get_record('format_socialwall_nfsettings', $params)) {

            $exists->notificationtype = $data->notificationtype;
            $DB->update_record('format_socialwall_nfsettings', $exists);
        } else {

            $DB->insert_record('format_socialwall_nfsettings', $data);
        }
    }

    /** get the notification settings for a user in a course, return 0 when no
     * record in database exists.
     *
     * @param record $course the course
     * @param int $userid the id of the user.
     * @return int
     */
    public function get_notification_user($course, $userid) {

        $nfsettings = $this->get_notification_settings($course->id);

        if (!isset($nfsettings[$userid])) {
            return 0;
        }

        return $nfsettings[$userid];
    }

    /** get all existing (in database) setting for the course
     *
     * @global object $DB
     * @param int $courseid, courseid.
     * @return array (userid => notification setting
     */
    public function get_notification_settings($courseid) {
        global $DB;

        $sql = "SELECT userid, notificationtype FROM {format_socialwall_nfsettings} WHERE courseid = ?";

        if (!$nfsettings = $DB->get_records_sql_menu($sql, array($courseid))) {
            return array();
        }

        return $nfsettings;
    }

}