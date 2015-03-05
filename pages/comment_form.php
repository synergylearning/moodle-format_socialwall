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

class comment_form extends moodleform {

    // Define the form.
    protected function definition() {

        $mform = & $this->_form;
        $postid = $this->_customdata['postid'];
        $courseid = $this->_customdata['id'];

        // ...get errors from cache and set them to elements.
        $errorcache = cache::make('format_socialwall', 'commentformerrors');
        if ($errors = $errorcache->get($postid)) {
            foreach ($errors as $element => $error) {
                $mform->setElementError($element, $error['message']);
            }
        }
        $errorcache->delete($postid);

        $mform->addElement('textarea', 'text', '', array('class' => 'tl-commenttext', 'id' => 'commenttext_' . $postid));
        $mform->setType('text', PARAM_TEXT);
        $mform->addRule('text', null, 'required', null, 'client');

        $mform->addElement('hidden', 'postid', $postid);
        $mform->setType('postid', PARAM_INT);

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action', 'postcomment');
        $mform->setType('action', PARAM_TEXT);

        $params = array('class' => 'tl-postcomment', 'id' => 'postcomment_' . $postid);
        $mform->addElement('submit', 'submitcomment', get_string('postcomment', 'format_socialwall'), $params);
    }

    public function has_errors() {
        $mform = & $this->_form;
        $error = $mform->getElementError('text');
        return !empty($error);
    }

    public function validation($data, $files) {

        $errors = array();

        // Submit is redirected if error occurs, so we store errordata in session.
        $sessionerrordata = array();
        $cache = cache::make('format_socialwall', 'commentformerrors');
        $cache->delete($data['postid']);

        // ... check if comment is all empty.
        if (isset($data['submitcomment'])) {
            if (empty($data['text'])) {
                $errors['text'] = get_string('textrequired', 'format_socialwall');
                $sessionerrordata['text'] = array('message' => $errors['text'], 'value' => $data['text']);
            }
        }

        // ... store or clean.
        if (!empty($sessionerrordata)) {
            $cache->set($data['postid'], $sessionerrordata);
        }

        return $errors;
    }

}