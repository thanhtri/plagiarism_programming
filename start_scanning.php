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
 * This script serves the ajax requests of assignment scanning when users click the scan button
 * on the assignment page
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/scan_assignment.php');
require_once(__DIR__.'/detection_tools.php');
global $DB;

// this global is used to store the assignment currently processed
// to record status if an unexpected error occurs.
// It is an array containing stage (which is extract,moss,jplag) and cmid
global $PROCESSING_INFO;

$cmid = required_param('cmid', PARAM_INT);
$task = required_param('task', PARAM_TEXT);

// user initiating the scanning must have grade right
$context = context_module::instance($cmid);
require_capability('mod/assignment:grade', $context);

// unblock the session to allow parallel running (if use default PHP session)
session_write_close();

$assignment = $DB->get_record('plagiarism_programming', array('cmid'=>$cmid));
if (!$assignment) {
    echo 'Invalid assignment!';
}
// possible values are scan, check and download
if ($task=='scan') {
    ignore_user_abort(true);
    set_time_limit(1*DAYSECS); // uploading may last very long, we cannot allow the script to wait for 1 day
    register_shutdown_function('plagiarism_programming_handle_shutdown');
    $time = optional_param('time', 0, PARAM_INT);
    $PROCESSING_INFO = array('stage'=>'extract', 'cmid'=>$cmid);
    ob_implicit_flush(true);
    start_scan_assignment($assignment, $time);
} else if ($task=='check') {
    $starttime = optional_param('time', 0, PARAM_INT);
    plagiarism_programming_check_status($assignment, $starttime);
} else if ($task=='download') {
    ignore_user_abort(true);
    set_time_limit(0);
    plagiarism_programming_download_assignment($assignment);
}

/**
 * Scan an assignment with all the selected tools. This function intend only to serve the ajax
 * request for scanning an assignment
 * @param $assignment: the record object of settings for the assignment.
 * @param $time: the timestamp this request is issued at the client side, to synchronise it with the check status requests
 */
function start_scan_assignment($assignment, $time) {
    global $DB, $detection_tools;

    echo get_string('scanning_in_progress', 'plagiarism_programming')."\n";
    // reset the status of all tools to pending and clear the error message if it is finished or error
    foreach ($detection_tools as $toolname => $tool) {
        if (isset($assignment->$toolname)) {
            $tool_record = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment->id));
            if ($tool_record && ($tool_record->status=='finished' || $tool_record->status=='error')) {
                $tool_record->status = 'pending';
                $tool_record->message = '';
                $tool_record->error_detail = '';
                $DB->update_record('plagiarism_programming_'.$toolname, $tool_record);
            }
        }
    }

    // register the timestamp. This helps the check status requests know that the status obtained is the current status of the
    // system, in case it comes sooner
    $assignment->starttime = $time;
    $DB->update_record('plagiarism_programming', $assignment);

    plagiarism_programming_create_temp_dir();
    plagiarism_programming_scan_assignment($assignment);
}

/**
 * Check the scanning status of the selected tools for an assignment.
 * The function will output a json object {toolname=>{stage:status,progress:percentage}}
 * @param $assignment: the record object of settings for the assignment
 * @param $time: a timestamp to make sure the checked status is the status of the scanning triggered
 */
function plagiarism_programming_check_status($assignment, $time=0) {
    global $DB, $detection_tools;

    $status = array();

    /* Check whether the timestamp is updated or not. If not, the status in DB is the old status */
    if ($time>0 && $time!=$assignment->starttime) {
        // this means that the scanning hasn't been started by the request simultaneously initiated with this yet
        // (this request come faster than the other)
        foreach ($detection_tools as $tool_name => $tool_info) {
            if ($assignment->$tool_name) {
                $status[$tool_name] = array('stage'=>'initiating', 'progress'=>0);
            }
        }
        echo json_encode($status);
        return;
    }

    // the scanning has been initiated
    foreach ($detection_tools as $tool_name => $tool_info) {
        if (!$assignment->$tool_name) {
            continue;
        }
        $scan_info = $DB->get_record('plagiarism_programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=null);

        $tool_class_name = $tool_info['class_name'];
        $tool_class = new $tool_class_name();
        plagiarism_programming_check_scanning_status($assignment, $tool_class, $scan_info);

        $status[$tool_name] = array('stage'=>$scan_info->status, 'progress'=>$scan_info->progress);
        if ($scan_info->status=='finished') { // send back the link
            $status[$tool_name]['link'] = $tool_class->display_link($assignment);
        } else if ($scan_info->status=='error') {
            $status[$tool_name]['message'] = $scan_info->message;
        }
    }
    echo json_encode($status);
}

/**
 * Download the similarity report of the selected tools. This function only delegates to download_result in scan_assignment.php
 * @param $assignment: the record object of setting for the assignment
 */
function plagiarism_programming_download_assignment($assignment) {
    global $DB, $detection_tools;
    foreach ($detection_tools as $tool_name => $tool_info) {
        if (!$assignment->$tool_name) {
            continue;
        }
        $scan_info = $DB->get_record('plagiarism_programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=null);

        if ($scan_info->status=='done') {
            $tool_class_name = $tool_info['class_name'];
            $tool_class = new $tool_class_name();
            plagiarism_programming_download_result($assignment, $tool_class, $scan_info);
        }
    }
}
