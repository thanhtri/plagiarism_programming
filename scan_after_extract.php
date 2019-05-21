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
 *
 * It cannot be called directly through the website, but just through curl libray in
 * scan_assignment function in start_scanning.php file
 * Authentication: a random token is generated and stored in the plagiarism_programming_jplag or plagiarism_programming_moss table
 * in DB before calling the script. This token is passed to this script, which in turn verify it with the one stored in DB.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/scan_assignment.php');
require_once(__DIR__.'/detection_tools.php');
global $DB, $CFG;

/*
 * This global is used to store the assignment currently processed
 * to record status if an unexpected error occurs.
 * It is an array containing stage (which is extract,moss,jplag) and cmid.
 * It is also used in "scan_assignment.php".
 */
global $processinginfo;
ignore_user_abort(true);
set_time_limit(DAYSECS);
ob_start();
$tool = required_param('tool', PARAM_TEXT);
$cmid = required_param('cmid', PARAM_INT);
$token = required_param('token', PARAM_TEXT);
$waittofinish = optional_param('wait', 1, PARAM_INT);
$notificationmail = optional_param('mail', 0, PARAM_INT);

// Verify the token.
$assignment = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
$scaninfo = $DB->get_record('plagiarism_programming_'.$tool, array('settingid' => $assignment->id));
if ($scaninfo->token != $token) {
    die ('Forbidden');
}

// Unlock the session to allow parallel running.
session_write_close();

// This is for error handling.
$processinginfo = array('stage' => $tool, 'cmid' => $cmid);
set_error_handler('plagiarism_programming_error_handler');
register_shutdown_function('plagiarism_programming_handle_shutdown');
scan_after_extract_assignment($assignment, $tool, $waittofinish, $notificationmail);

/**
 * Handles errors.
 * @param Number $errornumber
 * @param String $errormessage
 * @return boolean
 */
function plagiarism_programming_error_handler($errornumber, $errormessage) {

    if ($errornumber == E_ERROR) {
        global $DB, $processinginfo;
        $tool = $processinginfo['stage'];
        $cmid = $processinginfo['cmid'];

        $assignment = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
        $scaninfo = $DB->get_record('plagiarism_programming_'.$tool, array('settingid' => $assignment->id));
        $scaninfo->status = 'error';
        $scaninfo->message = get_string('general_user_error', 'plagiarism_programming');
        $scaninfo->error_detail = $errormessage;

        $DB->update_record('plagiarism_programming_'.$tool, $scaninfo);
    }
    return false;
}