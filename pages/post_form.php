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
 * socialwall course format.  Display the whole course as "socialwall" made of modules.
 *
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class post_form extends moodleform {

    // Define the form.
    protected function definition() {
        global $OUTPUT, $PAGE, $COURSE, $CFG, $USER;

        $mform = & $this->_form;
        $courseid = $this->_customdata['courseid'];
        $postid = (!empty($this->_customdata['options']->postid)) ? $this->_customdata['options']->postid : 0;
        // Update of save a post.
        $action = ($postid > 0) ? 'updatepost' : 'savepost';

        $context = context_course::instance($courseid);

        // ...get formparameters from cache.
        $cache = cache::make('format_socialwall', 'postformparams');
        $formparams = $cache->get($courseid . '_' . $postid);

        $loadposteditor = optional_param('loadposteditor', -1, PARAM_INT);

        if ($loadposteditor != -1) {

            $formparams['loadposteditor'] = $loadposteditor;

            // ...remember this setting, if page is reloaded.
            $cache->set($courseid . '_' . $postid, $formparams);
        }
        // ...get errors from cache and set them to elements.
        $errorcache = cache::make('format_socialwall', 'postformerrors');
        if ($errors = $errorcache->get($courseid)) {
            foreach ($errors as $element => $error) {
                $mform->setElementError($element, $error['message']);
            }
        }
        $errorcache->delete($courseid);

        // ... value of this element is set by javascript (postform.js) before submit.
        $mform->addElement('hidden', 'cmsequence', '', array('id' => 'cmsequence'));
        $mform->setType('cmsequence', PARAM_TEXT);
        $mform->setDefault('cmsequence', '');

        // ... posttext.
        $buttongroup = array();
        $buttongroup[] = $mform->createElement('submit', 'submitbutton', get_string($action, 'format_socialwall'));

        if ($action == 'updatepost') {
            $buttongroup[] = $mform->createElement('cancel');
        }
        $group = $mform->addGroup($buttongroup);
        $group->setAttributes(array('class' => 'fgroup_id_group_1'));

        // ... htmleditor/texarea to post text.
        $canposthtml = has_capability('format/socialwall:posthtml', $context);
        $showeditor = (!empty($formparams['loadposteditor']) and $canposthtml);

        $params = array('class' => 'sw-texarea', 'id' => 'posttext');

        if ($showeditor) {

            $mform->addElement('editor', 'posttext', get_string('poststatusordnote', 'format_socialwall'), $params);
            $mform->setType('posttext', PARAM_RAW);

            if (isset($formparams['posttext'])) {
                $element = $mform->getElement('posttext');
                $element->setValue(array('text' => $formparams['posttext']));
            }
        } else {

            $mform->addElement('textarea', 'posttext', get_string('poststatusordnote', 'format_socialwall'), $params);
            $mform->setType('posttext', PARAM_TEXT);
            if (isset($formparams['posttext'])) {
                $mform->setDefault('posttext', $formparams['posttext']);
            }
        }

        $postoptions = array();

        // ... Select group.
        $groupmode = groups_get_course_groupmode($COURSE);
        if (($groupmode == SEPARATEGROUPS) and ! has_capability('moodle/course:managegroups', $context)) {
            $allgroups = groups_get_all_groups($courseid, $USER->id);
        } else {
            $allgroups = groups_get_all_groups($courseid);
        }

        $groupsmenu = array();
        $groupsmenu[0] = get_string('allparticipants');
        foreach ($allgroups as $gid => $unused) {
            $groupsmenu[$gid] = format_string($allgroups[$gid]->name);
        }

        if (count($groupsmenu) > 0) {
            $postoptions[] = $mform->createElement('select', 'togroupid', '', $groupsmenu);
            if (isset($formparams['togroupid'])) {
                $mform->setDefault('togroupid', $formparams['togroupid']);
            }
        }

        // ... options group.
        $poststatusmenu = array(0 => get_string('poststatus', 'format_socialwall'));
        if (has_capability('format/socialwall:makesticky', $context)) {
            $poststatusmenu[1] = get_string('makesticky', 'format_socialwall');
        }
        if (has_capability('format/socialwall:postprivate', $context)) {
            $poststatusmenu[2] = get_string('privatepost', 'format_socialwall');
        }
        if ($PAGE->user_allowed_editing()) {
            $poststatusmenu[4] = get_string('makealert', 'format_socialwall');
        }
        if (count($poststatusmenu) > 1) {
            $postoptions[] = $mform->createElement('select', 'poststatus', '', $poststatusmenu);
            if (isset($formparams['poststatus'])) {
                $mform->setDefault('poststatus', $formparams['poststatus']);
            }
        }

        // ...switch htmleditor on/off.
        if ($canposthtml) {

            $key = (!empty($formparams['loadposteditor'])) ? 'turneditoroff' : 'turneditoron';
            $postoptions[] = $mform->createElement('submit', $key, get_string($key, 'format_socialwall'));
        }

        if (count($postoptions) > 0) {
            $group = $mform->addGroup($postoptions);
            $group->setAttributes(array('class' => 'fgroup_id_group_2'));

        }

        // ... display the activites prepared for the next post only by a teacher.
        if ($PAGE->user_allowed_editing()) {

            if (!isset($USER->editing) or ( !$USER->editing)) {

                $addstr = get_string('addactivityresource', 'format_socialwall');
                $mform->addElement('submit', 'turneditingon', $addstr, array('id' => 'sw-addactivitylink'));
            }
        } else {

            $o = html_writer::tag('div', '', array('class' => 'clearfix'));
            $mform->addElement('html', $o);

            // ...upload options for all users, which cannot edit page.
            $attachgroup = array();
            $course = course_get_format($COURSE)->get_course();

            $canpostfile = (has_capability('format/socialwall:postfile', $context) && (!empty($course->enablestudentupload)));

            if ($canpostfile) {

                $uploadfileicon = $OUTPUT->pix_icon('icon', get_string('uploadafile', 'format_socialwall'), 'resource');
                $linktext = $uploadfileicon . get_string('uploadafile', 'format_socialwall');

                $url = new moodle_url('/course/view.php', array('id' => $courseid, 'loadfilemanager' => 1));

                $link = html_writer::link($url, $linktext, array('id' => 'uploadfile'));
                $attachgroup[] = $mform->createElement('static', 'uploadfile', '', $link);
            }

            $canposturl = (has_capability('format/socialwall:posturl', $context) && (!empty($course->enablestudentupload)));

            if ($canposturl) {
                $addlinkicon = $OUTPUT->pix_icon('icon', get_string('addalink', 'format_socialwall'), 'url');
                $at = html_writer::link('#', $addlinkicon . get_string('addalink', 'format_socialwall'), array('id' => 'addalink'));
                $attachgroup[] = $mform->createElement('static', 'addalink', '', $at);
            }

            if (!empty($attachgroup)) {
                $group = $mform->addGroup($attachgroup);
                $group->setAttributes(array('class' => 'fgroup_id_group_3'));
            }

            $loadfilemanager = optional_param('loadfilemanager', 0, PARAM_INT);
            if ($canpostfile and ( $loadfilemanager == 1)) {

                $mform->addElement('html', html_writer::start_div('', array('id' => 'fileswrapper')));

                // ... filemanager.
                $filemanageroptions = array();
                $filemanageroptions['accepted_types'] = '*';
                $filemanageroptions['maxbytes'] = 0;
                $filemanageroptions['maxfiles'] = 1;
                $filemanageroptions['mainfile'] = true;

                $mform->addElement('filemanager', 'files', get_string('selectfiles'), array(), $filemanageroptions);

                $mform->addElement('html', html_writer::end_div());

                $mform->addElement('hidden', 'loadfilemanager', '1', array('id' => 'loadfilemanager'));
                $mform->setType('loadfilemanager', PARAM_INT);
            }

            // ...external url.
            $style = (isset($errors['externalurl'])) ? 'display:auto' : 'display:none';

            $mform->addElement('html', html_writer::start_div('', array('id' => 'externalurlwrapper', 'style' => $style)));

            $mform->addElement('url', 'externalurl', get_string('externalurl', 'url'),
                array('size' => '60'), array('usefilepicker' => true));

            $mform->setType('externalurl', PARAM_URL);
            if (isset($errors['externalurl'])) {
                $mform->setDefault('externalurl', $errors['externalurl']['value']);
            }

            // ... get urlresource filter a try.
            $filters = filter_get_active_in_context($context);
            if (isset($filters['urlresource'])) {
                require_once($CFG->dirroot . '/filter/urlresource/lib.php');
                filter_url_resource_helper::add_postformfields($mform, $courseid);
            }
            $mform->addElement('html', html_writer::end_div());
        }

        // Id of post to remember the update option for further pageloads.
        $mform->addElement('hidden', 'id', 0, array('id' => 'id'));
        $mform->setType('id', PARAM_INT);
        $mform->setDefault('id', $postid);

        // Id of course we are in.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $courseid);

        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_TEXT);
        $mform->disable_form_change_checker();
    }

    public function validation($data, $files) {
        global $CFG;

        $errors = array();

        // Submit is redirected if error occurs, so we store errordata in session.
        $sessionerrordata = array();
        $cache = cache::make('format_socialwall', 'postformerrors');
        $cache->delete($data['id']);

        // ... do validation of externalurl.
        if (!empty($data['externalurl'])) {

            include_once($CFG->libdir . '/validateurlsyntax.php');

            if (!validateUrlSyntax($data['externalurl'])) {

                $errors['externalurl'] = get_string('invalidurl', 'url');
                $sessionerrordata['externalurl'] = array('message' => $errors['externalurl'], 'value' => $data['externalurl']);
            }
        }

        // ... check if post is all empty.
        if (isset($data['submitbutton'])) {
            $empty = (empty($data['posttext']) && empty($data['cmsequence']) && empty($data['externalurl']) && empty($files));
            if ($empty) {
                $errors['posttext'] = get_string('attachmentorpostrequired', 'format_socialwall');
                $sessionerrordata['posttext'] = array('message' => $errors['posttext'], 'value' => $data['posttext']);
            }
        }

        // ... store or clean.
        if (!empty($sessionerrordata)) {
            $cache->set($data['id'], $sessionerrordata);
        }

        return $errors;
    }

    public function set_data($post) {
        global $DB;

        if ($post->id == 0) {
            return;
        }

        // Get attached activities for existing post.
        if ($cmids = $DB->get_records('format_socialwall_attaches', array('postid' => $post->id), '', 'coursemoduleid')) {
            $cache = cache::make('format_socialwall', 'attachedrecentactivities');
            $cache->set($post->courseid . '_' . $post->id, array_keys($cmids));
            $post->cmsequence = implode(',', array_keys($cmids));
        }

        // Post was already loaded the first time so take values from cache, which are already loaded.
        $cache = cache::make('format_socialwall', 'postformparams');
        if ($formparams = $cache->get($post->courseid . '_' . $post->id)) {
            return;
        }

        $post->poststatus = 0;

        if (!empty($post->sticky)) {
            $post->poststatus = 1;
        }

        if (!empty($post->private)) {
            $post->poststatus = 2;
        }

        if (!empty($post->alert)) {
            $post->poststatus = 4;
        }

        parent::set_data($post);

        // Set the cache for this post to keep values during the next page changes.
        $formparams = $cache->get($post->courseid . '_' . $post->id);
        $formparams['posttext'] = $post->posttext;
        $formparams['togroupid'] = $post->togroupid;
        $formparams['poststatus'] = $post->poststatus;
        $cache->set($post->courseid . '_' . $post->id, $formparams);

        $mform = & $this->_form;
        $element = $mform->getElement('posttext');
        if ($element->getType() == 'editor') {
            $element->setValue(array('text' => $post->posttext));
        }
    }

    protected function get_form_identifier() {
        global $USER;

        $postid = (!empty($this->_customdata['options']->postid)) ? $this->_customdata['options']->postid : 0;
        return get_class($this) . '_' . $postid . '_' . $USER->id;
    }

}
