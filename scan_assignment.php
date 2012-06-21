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
 * Initiate the plagiarism scanning for all assignments of which the
 * scanning date already passed
 * Called by the cron script
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

global $CFG;

require_once(__DIR__.'/utils.php');
require_once(__DIR__.'/plagiarism_tool.php');
require_once(__DIR__.'/jplag_tool.php');
require_once(__DIR__.'/moss_tool.php');
require_once(__DIR__.'/utils.php');

define('PLAGIARISM_TEMP_DIR', $CFG->dataroot.'/temp/plagiarism_programming/');

/**
 * Create a temporary directory for this plugin in $CFG->dataroot/temp/ directory
 */
function create_temporary_dir() {
    if (!is_dir(PLAGIARISM_TEMP_DIR)) {
        mkdir(PLAGIARISM_TEMP_DIR);
    }
}

/** 
 * Create the temporary directory for the assignment.
 * Students' code will be extracted here
 * @param: $assignment: the record object of setting for the assignment
 * @return a temporary directory that all files of this assignment will be stored
 */
function get_temp_dir_for_assignment($assignment) {
    $dir = PLAGIARISM_TEMP_DIR.$assignment->cmid.'/';
    if (!is_dir($dir)) {
        mkdir($dir);
    }
    return $dir;
}

/** Create a file for write. This function will create a directory along the file path if it doesn't exist
 * @param $fullpath: the path of the file
 * @return the write file handle. fclose have to be called when finishing with it
 */
function create_file($fullpath) {
    $dir_path = dirname($fullpath);
    if (is_dir($dir_path)) { // directory already exist
        return fopen($fullpath, 'w');
    } else {
        $dirs = explode('/', $dir_path);
        $path = '';
        foreach ($dirs as $dir) {
            $path .= $dir.'/';
            if (!is_dir($path)) {
                mkdir($path);
            }
        }
        return fopen($fullpath, 'w');
    }
}

/**
 * Search the file to clear the students' name and clear them
 * @param $filecontent: the content of a file
 * @param $userid: the user id of the file (in order to search the username and id to clear from the content)
 */
function clear_student_name(&$filecontent, $userid) {
    global $DB;
    // find all the comments. First version just support C++ style comments
    $pattern = '/\/\*.*?\*\//s'; // C style
    preg_match_all($pattern, $filecontent, $comments1);
    $pattern = '/\/\/.*/';       // C++ style
    preg_match_all($pattern, $filecontent, $comments2);
    $allcomments = array_merge($comments1[0], $comments2[0]);

    // get student name
    $student = $DB->get_record('user', array('id'=>$userid));
    $fname = $student->firstname;
    $lname = $student->lastname;
    $find_array = array($fname, $lname, $student->idnumber);
    $replace_array = array('#firstname', '#lastname', '#id');

    $finds = array();
    $replaces = array();

    foreach ($allcomments as $comment) {
        if (stripos($comment, $fname)!=false || stripos($comment, $lname)!=false || strpos($comment, $userid)!=false) {
            $new_comment = str_ireplace($find_array, $replace_array, $comment);
            $finds[]= $comment;
            $replaces[]=$new_comment;
            // to be safe, delete the comment with author inside, maybe the student write his name in another form
        } else if (strpos($comment, 'author')!=false) {
            $finds[]= $comment;
            $replaces[]= '';
        }
    }
    $filecontent = str_replace($finds, $replaces, $filecontent);
}

/**
 * Extract a zip file. In addition, this function also clear students' name and id if there
 * are in the comments and the name of the file
 * @param string $zip_file full path of the zip file
 * @param array $extensions extension of files that should be extracted (for example, just .java file should be extracted)
 * @param string $location directory of 
 * @param stored_file $file 
 */
function extract_zip($zip_file, $extensions, $location, stored_file $file) {
    $zip_handle = zip_open($zip_file);
    if ($zip_handle) {
        while ($zip_entry = zip_read($zip_handle)) {
            $entry_name = zip_entry_name($zip_entry);
            // if an entry name contain the id, remove it
            $entry_name = preg_replace('/[0-9]{8}/', '_id_', $entry_name);
            // if it's a file (skip directory entry since directories along the path
            // will be created when writing to the files
            if (substr($entry_name, -1)!='/' && check_extension($entry_name, $extensions)) {
                $fp = create_file($location.$entry_name);
                if (zip_entry_open($zip_handle, $zip_entry, "r")) {
                    $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                    clear_student_name($buf, $file->get_userid());
                    fwrite($fp, "$buf");
                    zip_entry_close($zip_entry);
                    fclose($fp);
                }
            }
        }
    }
}

/**
 * Extract students' assignments. This function will extract the students' compressed files and save them temporarily.
 * The directory is $CFG->dataroot/plagiarism_programming/student_id/files
 * @param $assignment: the record object of setting for the assignment
 * @return boolean true if extraction is successful and there are at least 2 students submitted their assignments
 *         boolean false if there are less than 2 students submitted (not need to send for marking)
 */
function extract_assignment($assignment) {
    global $OUTPUT;

    echo get_string('extract', 'plagiarism_programming');
    // make a subdir for this assignment in the plugin subdir
    $temp_submission_dir = get_temp_dir_for_assignment($assignment);

    // select all the submitted files of this assignment
    $context = get_context_instance(CONTEXT_MODULE, $assignment->cmid, MUST_EXIST);
    $fs = get_file_storage();
    $file_records = $fs->get_area_files($context->id, 'mod_assignment', 'submission', false, 'userid', false);

    if (count($file_records) < 2) {
        return false;
    }

    $extensions = get_file_extension_by_language($assignment->language);

    foreach ($file_records as $file) {
        $userid = $file->get_userid();
        $userdir = $temp_submission_dir.$userid.'/';
        if (!is_dir($userdir)) {
            mkdir($userdir);
        }

        // check if the file is zipped files
        if ($file->get_mimetype()=='application/zip') { //unzip files
            // copy to a temporary file, then read and extract from it (Moodle does not allow to read file directly)
            $temp_file_path = $temp_submission_dir.$file->get_filename();
            $file->copy_content_to($temp_file_path);
            extract_zip($temp_file_path, $extensions, $userdir, $file);
            unlink($temp_file_path);
        } else if (check_extension($file->get_filename(), $extensions)) { // if it is an uncompressed code file
            $file->copy_content_to($userdir.$file->get_filename());
        }
        // TODO: support other types of compression files, e.g. rar, 7z
    }

    return true;
}

/**
 * Submit an assignment to a specified tool. This method must be call after extracting the assignments
 * @param $assignment: the record object of setting for the assignment
 * @param $tool: the tool object (either of type moss_tool or jplag_tool)
 * @param $scan_info: the status record object of that tool
 * @return the updated scan_info object
 */
function submit_assignment($assignment, $tool, $scan_info) {
    global $DB;
    $tool_name = $tool->get_name();

    // status must be pending or error
    if ($scan_info->status!='pending' && $scan_info->status!='error') {
        // don't need to submit
        return $scan_info;
    }
    // update the status to uploading
    $scan_info->status = 'uploading';
    $scan_info->progress = 0;
    $DB->update_record('programming_'.$tool_name, $scan_info);

    debugging("Start sending to $tool_name \n");
    $temp_submission_dir = get_temp_dir_for_assignment($assignment);

    // submit the assignment
    $scan_info = $tool->submit_assignment($temp_submission_dir, $assignment, $scan_info);
    $DB->update_record('programming_'.$tool_name, $scan_info);
    debugging("Finish sending to $tool_name. Status: $scan_info->status\n");

    // note that scan_info is the object containing
    // the corresponding record in table programming_jplag
    return $scan_info;
}

/**
 * Return the updated status of the assignment. Use this function to check whether the scanning has been done.
 * This function also update the status record of the tool in the database
 * @param $assignment: the record object of setting for the assignment
 * @param $tool: the tool object (either of type moss_tool or jplag_tool)
 * @param $scan_info: the status record object of that tool
 * @return the updated scan_info object.
 */
function check_scanning_status($assignment, $tool, $scan_info) {
    global $DB;
    // if the assignment is processed by the server, ask the server to update the status
    // for every other stages, just return the assignment status since it is updated in parallel
    if ($scan_info->status=='scanning') {
        $scan_info = $tool->check_status($assignment, $scan_info);
        $DB->update_record('programming_'.$tool->get_name(), $scan_info);
    }
    return $scan_info;
}

/**
 * Download the similarity report. The scanning must be finished and its status must be 'done'
 * after calling check_scanning_status function
 * @param $assignment: the record object of setting for the assignment
 * @param $tool: the tool need to check (either moss or jplag)
 * @param $scan_info: the status record object of that tool
 * @return the $scan_info record object with status updated to 'finished' if download has been successful
 */
function download_result($assignment, $tool, $scan_info) {
    global $DB;

    // check and update the status first
    if ($scan_info->status!='done') {
        return;
    }
    $scan_info->status = 'downloading';
    $scan_info->progress = 0;
    $DB->update_record('programming_'.$tool->get_name(), $scan_info);

    echo "Download begin!\n";
    $scan_info = $tool->download_result($assignment, $scan_info);
    echo "Download finish!\n";

    echo "Parse begin\n";
    $scan_info = $tool->parse_result($assignment, $scan_info);
    echo "Parse end\n";
    $DB->update_record('programming_'.$tool->get_name(), $scan_info);
    return $scan_info;
}

/**
 * Check whether the assignment was sent to all the tools or not.
 * @param  $assignment: the object record of the plagiarism setting for the assignment
 * @return true  if the assignment has been sent to all the selected tools
 *         false if there is one tool to which the assignment hasn't been sent
 */
function already_uploaded($assignment) {
    global $DB, $detection_tools;

    $uploaded = true;
    foreach ($detection_tools as $tool_name => $tool_info) {
        if (!$assignment->$tool_name) {
            continue;
        }
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        if (!$scan_info || $scan_info->status=='pending' || $scan_info->status=='finished' || $scan_info->status=='error') {
            $uploaded = false;
        }
    }
    return $uploaded;
}

/**
 * The entry call to scan an assignment. First, students' assignments are extracted (if it is a compressed files),
 * then this function forks separate processes to scan the report
 * 
 * @param $assignment: the object record of the plagiarism setting for the assignment
 * @param $wait_for_result wait for the scanning to finish to get the result (default true)
 * @return void
 */
function scan_assignment($assignment, $wait_for_result=true) {
    global $DB, $CFG, $detection_tools;

    // if the assignment is not submitted, extract them into a temporary directory first
    // this if prevent unnecessary extraction, since another cron can run over when the scanning hasn't finished
    if (!already_uploaded($assignment)) {
        if (!extract_assignment($assignment)) {
            return;
        }
    }

    // send the data
    $links = array();
    $logfiles = array();

    $wait = ($wait_for_result)?1:0;
    // generating the token
    $token = md5(time()+  rand(1000000, 9999999));
    foreach ($detection_tools as $toolname => $tool) {
        if (!$assignment->$toolname) {    // this detector is not selected
            continue;
        }
        $scan_info = $DB->get_record('programming_'.$toolname, array('settingid'=>$assignment->id));
        $scan_info->token = $token;
        $DB->update_record('programming_'.$toolname, $scan_info);
        $links[] = "$CFG->wwwroot/plagiarism/programming/scan_after_extract.php?"
            ."cmid=$assignment->cmid&tool=$toolname&token=$token&wait=$wait";

        $tool_class_name = $tool['class_name'];
        $logfiles[] = $tool_class_name::get_report_path()."/script_log_$assignment->id-$toolname.html";
    }

    // register the start scanning time
    $assignment = $DB->get_record('programming_plagiarism', array('id'=>$assignment->id));
    $assignment->latestscan = time();
    $DB->update_record('programming_plagiarism', $assignment);

    curl_download($links, $logfiles);

    foreach ($logfiles as $logfile) {
        echo file_get_contents($logfile);
        echo "\n";
    }
}

/**
 * This function is used by scan_assignment to send an assignment to a specific tool. The assignment is first sent to the tool,
 * then either wait until the scanning finished to download or download it latter. In the second case, calling the function another
 * time will check the scanning status and, if finished, download it again.
 * @param $assignment: the object record of the plagiarism setting for the assignment
 * @param $toolname: the name of the tool
 * @param $wait_to_download: if set to true, the scanning will wait and periodically check the status until it finish and download
 * 
 */
function scan_after_extract_assignment($assignment, $toolname, $wait_to_download=true) {
    global $detection_tools, $DB;
    $tool = $detection_tools[$toolname];
    $tool_class_name = $tool['class_name'];
    $tool_class = new $tool_class_name();
    $scan_info = $DB->get_record('programming_'.$toolname, array('settingid'=>$assignment->id));
    assert($scan_info!=null);

    if ($scan_info->status=='pending' || $scan_info->status=='error') {
        // not processed by this tool yet
        $scan_info = submit_assignment($assignment, $tool_class, $scan_info);
    }

    check_scanning_status($assignment, $tool_class, $scan_info);
    if ($wait_to_download && $scan_info->status=='scanning') {
        // waiting for the detectors to process
        // check if the result is available
        while ($scan_info->status!='done') {
            sleep(5);
            check_scanning_status($assignment, $tool_class, $scan_info);
        }
    }

    if ($scan_info->status=='done') {
        debugging("Start downloading with $toolname \n");
        download_result($assignment, $tool_class, $scan_info);
        debugging("Finish downloading with $toolname. Status: $scan_info->status \n");
    }
}

/**
 * This function will update scanning status with error message when a fatal error occurs.
 * It is registered by register_shutdown_function in start_scanning.php and in scan_after_extract.php scripts
 * Should never be called in code.
 */
function handle_shutdown() {
    global $DB, $CFG, $detection_tools;
    global $PROCESSING_INFO;  // this global is an array consisting of stage and cmid

    $error = error_get_last();
    if ($PROCESSING_INFO && $error && ($error['type']==E_ERROR)) {
        // the value is one of "extract" (extraction of files before MOSS and JPlag), "jplag" or "moss"
        $stage = $PROCESSING_INFO['stage'];
        $cmid = $PROCESSING_INFO['cmid'];

        $assignment = $DB->get_record('programming_plagiarism', array('cmid'=>$cmid));

        /** If extract, set all statuses  */
        if ($stage=='extract') {
            $tools = array_keys($detection_tools);
        } else {
            $tools = array($stage);
        }

        foreach ($tools as $tool) {
            if ($assignment->$tool) {
                $scan_info = $DB->get_record('programming_'.$tool, array('settingid'=>$assignment->id));
                $scan_info->status = 'error';
                $message = get_string('unexpected_error', 'plagiarism_programming');
                if ($stage=='extract') {
                    $message = get_string('unexpected_error_extract', 'plagiarism_programming');
                } else if ($scan_info->status=='uploading') {
                    $message = get_string('unexpected_error_upload', 'plagiarism_programming');
                } else if ($scan_info->status=='downloading') {
                    $message = get_string('unexpected_error_download', 'plagiarism_programming');
                }
                $scan_info->message = $message;
                $DB->update_record('programming_'.$tool, $scan_info);
            }
        }
    }

    if ($PROCESSING_INFO && $PROCESSING_INFO['stage']!='extract') {
        $cmid = $PROCESSING_INFO['cmid'];
        $tool = $PROCESSING_INFO['stage'];
        $assignment = $DB->get_record('programming_plagiarism', array('cmid'=>$cmid));
        $scan_info = $DB->get_record('programming_'.$tool, array('settingid'=>$assignment->id));
        echo 'Before shutdown: status: '.$scan_info->status;
        if ($scan_info->status!='error' && $scan_info->status!='finished') {
            $scan_info->status = 'error';
            $scan_info->message = 'An unknown error has occurred!';
            $DB->update_record('programming_'.$tool, $scan_info);
        }
    }
    $file = "$CFG->dataroot/plagiarism_report/".$PROCESSING_INFO['stage'].'.txt';
    $content = ob_get_contents();
    file_put_contents($file, $content);
}