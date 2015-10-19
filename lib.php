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
 * This file contains main class for the course format Socialwall
 *
 * @since     Moodle 2.7
 * @package   format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/topics/lib.php');
require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');
require_once($CFG->dirroot . '/course/format/socialwall/pages/post_form.php');

/**
 * Main class for the socialwall course format
 *
 * @package    format_socialwall
 * @copyright  2014 Andreas Wagner, Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_socialwall extends format_topics {

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        return null;
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * Please note that course view page /course/view.php?id=COURSEID is hardcoded in many
     * places in core and contributed modules. If course format wants to change the location
     * of the view script, it is not enough to change just this function. Do not forget
     * to add proper redirection.
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if null the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        return $url;
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * socialwall format uses the following options:
     * - coursedisplay
     * - numsections
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('format_socialwall');

            $courseformatoptions = array(
                'enablenotification' => array(
                    'default' => $courseconfig->enablenotification,
                    'type' => PARAM_BOOL,
                ),
                'enablelikes' => array(
                    'default' => $courseconfig->enablelikes,
                    'type' => PARAM_BOOL,
                ),
                'enablestudentupload' => array(
                    'default' => $courseconfig->enablestudentupload,
                    'type' => PARAM_BOOL,
                ),
                'tlnumposts' => array(
                    'default' => $courseconfig->tlnumposts,
                    'type' => PARAM_INT,
                ),
                'tlnumcomments' => array(
                    'default' => $courseconfig->tlnumcomments,
                    'type' => PARAM_INT,
                ),
                'tlnumreplies' => array(
                    'default' => $courseconfig->tlnumreplies,
                    'type' => PARAM_INT,
                ),
                'numsections' => array(
                    'default' => 3,
                    'type' => PARAM_INT,
                ),
                'deleteafterunenrol' => array(
                    'default' => $courseconfig->deleteafterunenrol,
                    'type' => PARAM_BOOL
                ),
                'deletemodspermanently' => array(
                    'default' => $courseconfig->deleteafterunenrol,
                    'type' => PARAM_BOOL
                )
            );
        }
        if ($foreditform && !isset($courseformatoptions['enablenotification']['label'])) {

            $nums = array(
                '1' => 1,
                '2' => 2,
                '3' => 3,
                '4' => 4,
                '5' => 5,
                '10' => 10,
                '20' => 20
            );

            $courseformatoptionsedit = array(
                'enablenotification' => array(
                    'label' => new lang_string('enablenotification', 'format_socialwall'),
                    'element_type' => 'selectyesno',
                    'help' => 'enablenotification',
                    'help_component' => 'format_socialwall'
                ),
                'enablelikes' => array(
                    'label' => new lang_string('enablelikes', 'format_socialwall'),
                    'element_type' => 'selectyesno',
                    'help' => 'enablelikes',
                    'help_component' => 'format_socialwall'
                ),
                'enablestudentupload' => array(
                    'label' => new lang_string('enablestudentupload', 'format_socialwall'),
                    'element_type' => 'selectyesno',
                    'help' => 'enablestudentupload',
                    'help_component' => 'format_socialwall',
                ),
                'tlnumposts' => array(
                    'label' => new lang_string('tlnumposts', 'format_socialwall'),
                    'element_type' => 'select',
                    'element_attributes' => array($nums),
                ),
                'tlnumcomments' => array(
                    'label' => new lang_string('tlnumcomments', 'format_socialwall'),
                    'element_type' => 'select',
                    'element_attributes' => array($nums),
                ),
                'tlnumreplies' => array(
                    'label' => new lang_string('tlnumreplies', 'format_socialwall'),
                    'element_type' => 'select',
                    'element_attributes' => array($nums),
                ),
                'deleteafterunenrol' => array(
                    'label' => new lang_string('deleteafterunenrol', 'format_socialwall'),
                    'element_type' => 'selectyesno',
                    'help' => 'deleteafterunenrol',
                    'help_component' => 'format_socialwall'
                ),
                'deletemodspermanently' => array(
                    'label' => new lang_string('deletemodspermanently', 'format_socialwall'),
                    'element_type' => 'selectyesno',
                    'help' => 'deletemodspermanently',
                    'help_component' => 'format_socialwall'
                ),
                'numsections' => array(
                    'label' => '',
                    'element_type' => 'hidden'
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

}
