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
 * This script is used to fork processes in order to scan assignments after extraction.
 * It cannot be called directly through the website, but just through curl libray in
 * scan_assignment function in start_scanning.php file
 * Authentication: a random token is generated and stored in the programming_jplag or programming_moss table
 * in DB before calling the script. This token is passed to this script, which in turn verify it with the one stored in DB.
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/scan_assignment.php');
require_once(__DIR__.'/detection_tools.php');
global $DB, $CFG;

// this global is used to store the assignment currently processed
// to record status if an unexpected error occurs.
// It is an array containing stage (which is extract,moss,jplag) and cmid
global $PROCESSING_INFO;
ignore_user_abort();
$tool = required_param('tool', PARAM_TEXT);
$cmid = required_param('cmid', PARAM_INT);
$token = required_param('token', PARAM_TEXT);
$wait_to_finish = optional_param('wait', 1, PARAM_INT);

// verify the token
$assignment = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
$scan_info = $DB->get_record('programming_'.$tool, array('settingid'=>$assignment->id));
if ($scan_info->token!=$token) {
    die ('Forbidden');
}

// unblock the session to allow parallel running (if use default PHP session)
session_write_close();

// this is for error handling
$PROCESSING_INFO = array('stage'=>$tool, 'cmid'=>$cmid);
ob_start();
set_error_handler('tool_scanning_error_handler');
register_shutdown_function('handle_shutdown');
scan_after_extract_assignment($assignment, $tool, $wait_to_finish);
function tool_scanning_error_handler($error_no, $error_message) {
    if ($error_no==E_USER_ERROR) {
        global $DB, $PROCESSING_INFO;
        $tool = $PROCESSING_INFO['stage'];
        $cmid = $PROCESSING_INFO['cmid'];

        $assignment = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
        $scan_info = $DB->get_record('programming_'.$tool, array('settingid'=>$assignment->id));
        $scan_info->status = 'error';
        $scan_info->message = get_string('general_user_error', 'plagiarism_programming');
        $scan_info->error_detail = $error_message;

        $DB->update_record('programming_'.$tool, $scan_info);
    }
    return false;
}