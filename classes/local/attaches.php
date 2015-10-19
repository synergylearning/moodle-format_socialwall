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

require_once($CFG->dirroot . '/lib/gradelib.php');

/** This class manages the relationship between posts and attached modules. */
class attaches {

    protected $grades;

    /** 
     * Create instance as a singleton
     */
    public static function instance() {
        static $attaches;

        if (isset($attaches)) {
            return $attaches;
        }

        $attaches = new attaches();
        return $attaches;
    }

    /** 
     * Add info about this users attaches to the postsdata object
     * 
     * @param object $postsdata
     * @return boolean, true if succeded
     */
    public static function add_attaches_to_posts($courseid, &$postsdata) {
        global $DB, $USER;

        if (empty($postsdata->posts)) {
            return false;
        }

        $posts = $postsdata->posts;

        $params = array($USER->id);
        list($inpoststr, $inparams) = $DB->get_in_or_equal(array_keys($posts));
        $params = array_merge($params, $inparams);
        $params[] = $courseid;

        // ... fetch attaches an grade_items.
        $sql = "SELECT at.id as atid, at.postid, at.coursemoduleid, gi.*
                FROM {format_socialwall_attaches} at
                JOIN {course_modules} cm ON cm.id = at.coursemoduleid
                JOIN {modules} m ON m.id = cm.module
                LEFT JOIN
                    (SELECT gg.id, gri.iteminstance, gri.itemmodule, gri.itemtype
                     FROM {grade_items} gri
                     JOIN {grade_grades} gg ON (gg.itemid = gri.id AND userid = ?)) as
                     gi ON (gi.itemmodule = m.name AND gi.iteminstance = cm.instance)
                WHERE postid {$inpoststr} AND cm.course = ?";

        if (!$attaches = $DB->get_records_sql($sql, $params)) {
            return false;
        }

        foreach ($attaches as $attachment) {

            if (!isset($postsdata->posts[$attachment->postid]->attaches)) {

                $postsdata->posts[$attachment->postid]->attaches = array();
                $postsdata->posts[$attachment->postid]->grades = array();
            }

            $postsdata->posts[$attachment->postid]->attaches[$attachment->atid] = $attachment;

            if (!empty($attachment->iteminstance)) {
                $gradedata = grade_get_grades(
                        $courseid, $attachment->itemtype, $attachment->itemmodule, $attachment->iteminstance, $USER->id
                );

                // Add author of grades, to retrieve complete user record later.
                if (!empty($gradedata->outcomes[0]->grades[$USER->id])) {

                    $usermodified = $gradedata->outcomes[0]->grades[$USER->id]->usermodified;

                    $postsdata->authors[$usermodified] = $usermodified;
                    $postsdata->posts[$attachment->postid]->grades[$attachment->coursemoduleid] = $gradedata->outcomes[0];
                }
                if (!empty($gradedata->items[0]->grades[$USER->id])) {

                    $usermodified = $gradedata->items[0]->grades[$USER->id]->usermodified;

                    $postsdata->authors[$usermodified] = $usermodified;
                    $postsdata->posts[$attachment->postid]->grades[$attachment->coursemoduleid] = $gradedata->items[0];
                }
            }
        }

        return true;
    }

    /** 
     * Save coursemoduleids attached to a post
     * 
     * @return array, result array.
     */
    public static function save_attaches($postid, $cmsequence) {
        global $DB;

        // Delete all existing attachments.
        $DB->delete_records('format_socialwall_attaches', array('postid' => $postid));

        if (empty($cmsequence)) {
            return array('error' => '0', 'message' => 'attachessaved');
        }

        $cmids = explode(',', $cmsequence);

        if (empty($cmids)) {
            return array('error' => '0', 'message' => 'attachessaved');
        }

        foreach ($cmids as $cmid) {
            $attachment = new \stdClass();
            $attachment->postid = $postid;
            $attachment->coursemoduleid = $cmid;
            $attachment->sortorder = 0;
            $DB->insert_record('format_socialwall_attaches', $attachment);
        }
        return array('error' => '0', 'message' => 'attachessaved');
    }

    /** 
     * Delete all the information about the attached modules for a coursemodule
     * 
     * @param int $cmid the id of the course module.
     */
    public static function cleanup_coursemoduledeleted($cmid) {
        global $DB;

        $DB->delete_records('format_socialwall_attaches', array('coursemoduleid' => $cmid));
    }

}