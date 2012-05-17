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

define('AJAX_SCRIPT', TRUE);

include __DIR__.'/../../config.php';
include_once __DIR__.'/scan_assignment.php';
include_once __DIR__.'/detection_tools.php';
global $DB;

// this global is used to store the assignment currently processed
// to record status if an unexpected error occurs.
// It is an array containing stage (which is extract,moss,jplag) and cmid
global $PROCESSING_INFO;

$cmid = required_param('cmid', PARAM_INT);
$task = required_param('task', PARAM_TEXT);

// user initiating the scanning must have grade right
$context = get_context_instance(CONTEXT_MODULE, $cmid);
require_capability('mod/assignment:grade', $context);

// unblock the session to allow parallel running (if use default PHP session)
session_write_close();

$assignment = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
if (!$assignment) {
    echo 'Invalid assignment!';
}
// possible values are scan, check and download
if ($task=='scan') {
    ignore_user_abort();
    set_time_limit(0); // uploading may last very long
    register_shutdown_function('handle_shutdown');
    $time = required_param('time', PARAM_INT);
    $PROCESSING_INFO = array('stage'=>'extract','cmid'=>$cmid);
    start_scan_assignment($assignment,$time);
} elseif ($task=='check') {
    $starttime = required_param('time',PARAM_INT);
    check_status($assignment,$starttime);
} elseif ($task=='download') {
    ignore_user_abort();
    set_time_limit(0);
    download_assignment($assignment);
}

function start_scan_assignment($assignment,$time) {
    global $DB,$detection_tools;

    // update the status of all tools to pending if it is finished or error
    foreach ($detection_tools as $toolname=>$tool) {
        if (isset($assignment->$toolname)) {
            $tool_record = $DB->get_record('programming_'.$toolname, array('settingid'=>$assignment->id));
            if ($tool_record && ($tool_record->status=='finished' || $tool_record->status=='error')) {
                $tool_record->status = 'pending';
                $DB->update_record('programming_'.$toolname,$tool_record);
            }
        }
    }

    // register the last time scan
    $assignment->starttime = $time;
    $DB->update_record('programming_plagiarism',$assignment);

    create_temporary_dir();
    scan_assignment($assignment);
}

function check_status($assignment,$time) {
    global $DB, $detection_tools;
    
    $status = array();
    if ($time!=$assignment->starttime) {
        // this means that the scanning hasn't been started by the request simultaneously initiated with this yet
        // (this request come faster than the other)
        foreach ($detection_tools as $tool_name=>$tool_info) {
            if ($assignment->$tool_name)
            $status[$tool_name] = array('stage'=>'initiating','progress'=>0);
        }
        echo json_encode($status);
        return;
    }
    
    // the scanning has been initiated
    foreach ($detection_tools as $tool_name=>$tool_info) {
        if (!$assignment->$tool_name)
            continue;
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=NULL);

        $tool_class_name = $tool_info['class_name'];
        $tool_class = new $tool_class_name();

        $status[$tool_name] = array('stage'=>$scan_info->status,'progress'=>$scan_info->progress);
        if ($scan_info->status=='finished') { // send back the link
            $status[$tool_name]['link'] = $tool_class->display_link($assignment);
        } elseif ($scan_info->status=='error') {
            $status[$tool_name]['message'] = $scan_info->message;
        }
    }
    echo json_encode($status);
}

function download_assignment($assignment) {
    global $DB, $detection_tools;
    $status = array();
    foreach ($detection_tools as $tool_name=>$tool_info) {
        if (!$assignment->$tool_name)
            continue;
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=NULL);

        if ($scan_info->status=='done') {
            $tool_class_name = $tool_info['class_name'];
            $tool_class = new $tool_class_name();
            download_result($assignment, $tool_class, $scan_info);
        }
    }
}
