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
 * From to post a comment.
 *
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class addactivity_form extends moodleform {

    // Define the form.
    protected function definition() {
        global $PAGE;

        $mform = & $this->_form;
        $courseid = $this->_customdata['courseid'];
        $postid = $this->_customdata['postid'];

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_cms();

        // Add activities area.
        $modulenames = get_module_types_names();

        $existingnames = array();
        foreach ($cms as $mod) {

            $existingnames[$mod->modname] = $modulenames[$mod->modname];
        }

        core_collator::asort($existingnames);

        $typeelements = array();
        foreach ($existingnames as $type => $modname) {
            $typeelements[$type] = $mform->createElement('checkbox', 'type_' . $type, '', $modname, array('id' => 'type_' . $type));
            $mform->setDefault('filterbytype[type_' . $type . ']', 1);
        }

        if (!empty($typeelements)) {

            // Module type filter.
            $mform->addElement('header', 'filtersheader', get_string('filtersheader', 'format_socialwall'));
            $mform->addGroup($typeelements, 'filterbytype');

            $params = array('size' => '30', 'placeholder' => get_string('searchbyname', 'format_socialwall'));
            $mform->addElement('text', 'searchbyname', '', $params);
            $mform->setType('searchbyname', PARAM_TEXT);

        } else {
            $mform->addElement('html', get_string('norecentactivities', 'format_socialwall'));
        }

        // Recent activities area.
        $mform->addElement('header', 'recentactivitiesheader', get_string('recentactivities', 'format_socialwall'));
        $mform->setExpanded('recentactivitiesheader');
        
        $courserenderer = $PAGE->get_renderer('course');

        $modids = array();
        $cache = cache::make('format_socialwall', 'attachedrecentactivities');

        if ($attachedrecentactivities = $cache->get($courseid . '_' . $postid)) {
            $modids = array_flip($attachedrecentactivities);
        }

        // Order cms by name.
        if (!empty($cms)) {
            uasort($cms, array($this, 'compare_modules'));
        }

        foreach ($cms as $mod) {

            $name = $courserenderer->course_section_cm_name($mod);
            $type = $mod->modname;

            // In case of empty name, try to get content.
            if (empty($name)) {

                $contentpart = $courserenderer->course_section_cm_text($mod);
                $url = $mod->url;

                if (empty($url)) {
                    $name = shorten_text($contentpart, 70);
                }
            }

            $mform->addElement('checkbox', 'module_' . $type . '_' . $mod->id, '', $name, array('id' => 'module_' . $mod->id));

            if (isset($modids[$mod->id])) {
                $mform->setDefault('module_' . $type . '_' . $mod->id, 1);
            }
        }
        
        $args = array(
            'courseid' => $courseid,
            'attachedrecentactivities' => $attachedrecentactivities
        );

        $PAGE->requires->strings_for_js(
                array('addrecentactivity', 'attach', 'cancel'), 'format_socialwall');

        $PAGE->requires->yui_module(
                'moodle-format_socialwall-addactivity', 'M.format_socialwall.addactivityinit', array($args), null, true);
    }

    /**
     * Compare the modules by name
     * 
     * @param object $mod1
     * @param object $mod2
     * @return int
     */
    protected function compare_modules($mod1, $mod2) {

        if (empty($mod1->name)) {
            return -1;
        }

        if (empty($mod2->name)) {
            return -1;
        }

        if (strtoupper($mod1->name) > strtoupper($mod2->name)) {
            return 1;
        }

        if (strtoupper($mod1->name) == strtoupper($mod2->name)) {
            return 0;
        }
        return -1;
    }

}
