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
 * Upgrade script
 *
 * @package    core
 * @copyright  1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_qtype_myqtype_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    /// Add a new column newcol to the mdl_myqtype_options
    if ($oldversion < 2012050103) {

        // Define field token to be added to programming_jplag
        $table = new xmldb_table('programming_jplag');
        $field = new xmldb_field('token', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'progress');

        // Conditionally launch add field token
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field token to be added to programming_moss
        $table = new xmldb_table('programming_moss');
        $field = new xmldb_field('token', XMLDB_TYPE_CHAR, '32', null, null, null, null, 'progress');

        // Conditionally launch add field token
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // programming savepoint reached
        upgrade_plugin_savepoint(true, 2012050103, 'plagiarism', 'programming');

    }

    return TRUE;
}
