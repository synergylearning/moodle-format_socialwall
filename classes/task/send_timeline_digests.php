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
 * socialwall course format, Tasks
 *
 * @package format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_socialwall\task;

class send_timeline_digests extends \core\task\scheduled_task {

    public function get_name() {
        // Shown in admin screens.
        return get_string('sendtimelinedigests', 'format_socialwall');
    }

    public function execute() {
        global $CFG;

        require_once($CFG->dirroot . '/course/format/socialwall/locallib.php');

        // Send out all digests.
        $notification = \format_socialwall\local\notification::instance();
        $notification->digest_cron();
    }

}