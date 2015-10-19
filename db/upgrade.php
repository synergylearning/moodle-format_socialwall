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
 * @since     Moodle 2.7
 * @package   format_socialwall
 * @copyright 2014 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_format_socialwall_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015072900) {

        // Define field replycommentid to be added to format_socialwall_comments.
        $table = new xmldb_table('format_socialwall_comments');
        $field = new xmldb_field('replycommentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'postid');

        // Conditionally launch add field replycommentid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Socialwall savepoint reached.
        upgrade_plugin_savepoint(true, 2015072900, 'format', 'socialwall');
    }

    if ($oldversion < 2015081801) {

        // Define field countreplies to be added to format_socialwall_comments.
        $table = new xmldb_table('format_socialwall_comments');
        $field = new xmldb_field('countreplies', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'replycommentid');

        // Conditionally launch add field countreplies.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Socialwall savepoint reached.
        upgrade_plugin_savepoint(true, 2015081801, 'format', 'socialwall');
    }

    return true;
}
