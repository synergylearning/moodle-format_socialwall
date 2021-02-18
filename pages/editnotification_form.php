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
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');

class editnotification_form extends moodleform {

    // Define the form.
    protected function definition() {

        $mform = & $this->_form;
        $courseid = $this->_customdata['courseid'];
        $userid = $this->_customdata['userid'];
        $notificationtype = $this->_customdata['notificationtype'];

        $choices = array();
        foreach (\format_socialwall\local\notification::$notificationtype as $key => $type) {
            $choices[$key] = get_string($type, 'format_socialwall');
        }

        $mform->addElement('select', 'notificationtype', get_string('notificationtype', 'format_socialwall'), $choices);
        $mform->setDefault('notificationtype', $notificationtype);

        // Id of course, we are in.
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        $mform->setDefault('userid', $userid);

        // Id of course, we are in.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $courseid);

        $this->add_action_buttons(true, get_string('savesetting', 'format_socialwall'));
    }

}