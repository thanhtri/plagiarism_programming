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

//used in extract_assignment
define('NOT_SUFFICIENT_SUBMISSION', 1);
define('CONTEXT_NOT_EXIST', 2);
define('NOT_CORRECT_FILE_TYPE', 3);

define('CODE_FILE_DIRECTORY_FORMAT', 'code_file_directory_format');
define('CODE_FILE_ARCHIVE_FORMAT', 'code_file_archive_format');
define('CODE_FILE_SINGLE_SUBMISSION_FORMAT', 'code_file_single_submission_format');

global $CFG;

require_once(__DIR__.'/utils.php');
require_once(__DIR__.'/plagiarism_tool.php');
require_once(__DIR__.'/jplag_tool.php');
require_once(__DIR__.'/moss_tool.php');
require_once(__DIR__.'/utils.php');

define('PLAGIARISM_TEMP_DIR', $CFG->tempdir.'/plagiarism_programming/');

/**
 * Create a temporary directory for this plugin in $CFG->tempdir directory
 */
function plagiarism_programming_create_temp_dir() {
    if (!is_dir(PLAGIARISM_TEMP_DIR)) {
        mkdir(PLAGIARISM_TEMP_DIR);
    }
}

/** 
 * Create the temporary directory (if doesn't exist) for the assignment.
 * Students' code will be extracted here
 * @param: $assignment: the record object of setting for the assignment
 * @return a temporary directory that all files of this assignment will be stored
 */
function plagiarism_programming_get_assignment_dir($assignment, $empty_dir=false) {
    $dir = PLAGIARISM_TEMP_DIR.$assignment->cmid.'/';
    if ($empty_dir) {
        plagiarism_programming_rrmdir($dir);
        mkdir($dir);
    } else if (!is_dir($dir)) {
        mkdir($dir);
    }
    return $dir;
}

/**
 * Get the submitted files for the assignment
 * This function will take into account the difference of component and
 * file area between Moodle 2.3 and previous version
 * @param context_module $assignment_context The context of the assignment
 */
function plagiarism_programming_get_submitted_files($assignment_context) {
    global $CFG;

    $cm = get_coursemodule_from_id('', $assignment_context->instanceid);
    if ($cm->modname =='assign') {
        $component = 'assignsubmission_file';
        $file_area = 'submission_files';
    } else {
        $component = 'mod_assignment';
        $file_area = 'submission';
    }
    $fs = get_file_storage();
    $file_records = $fs->get_area_files($assignment_context->id, $component, $file_area, false, 'userid', false);
    return $file_records;
}

/**
 * Extract a compressed file, either zip or rar
 */
function plagiarism_programming_extract_file($file, $extensions, $location, $user=null, $textfile_only=true) {

    if ($file instanceof stored_file) {
        $temp_file_path = dirname($location).'/'.$file->get_filename();
        $file->copy_content_to($temp_file_path);
        $filename = $file->get_filename();
        $file_ext = substr($filename, -4, 4);
    } else { // file is a string
        $temp_file_path = $file;
        $filename = basename($temp_file_path);
        $file_ext = substr($filename, -4, 4);
    }
    if ($file_ext == '.zip') {
        $valid_file = plagiarism_programming_extract_zip($temp_file_path, $extensions, $location, $user, $textfile_only);
    } else if ($file_ext == '.rar') {
        $valid_file = plagiarism_programming_extract_rar($temp_file_path, $extensions, $location, $user, $textfile_only);
    } else {
        debugging("Error extracting file: ".$filename.' is not a compressed file');
    }

    if ($file instanceof stored_file) {
        unlink($temp_file_path);
    }
    return $valid_file;
}

/**
 * Determine the validity of the structure of the additional code file (the file submitted in the addtional code section when creating/editing an assignment).
 * The zip file could contain:
 *  + an assignment as a whole (in this case, there must be at least 1 file
 *    at the top most directory level
 *  + a number of directories, each contains an assignment
 *  + a number zip files, each contains an assignment
 */
function plagiarism_programming_check_additional_code_structure($decompressed_dir) {
    if (!is_dir($decompressed_dir)) {
        debugging("$decompressed_dir is not a directory to decompress");
        return NULL;
    }

    $not_all_dir = false;
    $not_all_compressed = false;
    $file_list = scandir($decompressed_dir);
    foreach ($file_list as $file) {
        if (!is_dir($decompressed_dir.'/'.$file)) {
            $not_all_dir = true;
            if (!plagiarism_programming_is_compressed_file($file)) {
                $not_all_compressed = true;
            }
        }
        if ($not_all_dir && $not_all_compressed) {
            break;
        }
    }
    if (!$not_all_dir) { // all directory, should be 
        return CODE_FILE_DIRECTORY_FORMAT;
    } else if (!$not_all_compressed) {
        return CODE_FILE_ARCHIVE_FORMAT;
    } else {
        return CODE_FILE_SINGLE_SUBMISSION_FORMAT;
    }
}

/**
 * Extract the additional code file
 */
function process_code_file_archive_format($decompressed_dir, array $extensions, $location) {
    if (!is_dir($decompressed_dir)) {
        debugging("$decompressed_dir is not a directory!");
        return null;
    }

    $files = scandir($decompressed_dir);
    foreach ($files as $file) {

        if ($file=='.' || $file=='..') {
            continue;
        }

        $file_fullpath = "$decompressed_dir/$file";
        // it is assumed that file must all be zip or rar
        if (!plagiarism_programming_is_compressed_file($file_fullpath)) {
            debugging("File $file is not a compressed file");
            continue;
        }

        plagiarism_programming_extract_file($file_fullpath, $extensions, "$location/ext_$file/");
    }
}

function process_code_file_directory_format($decompressed_dir, array $extensions, $location) {
    if (!is_dir($decompressed_dir)) {
        debugging("$decompressed_dir is not a directory");
        return null;
    }

    $files = scandir($decompressed_dir);
    foreach ($files as $file) {
        if ($file=='.' || $file=='..') {
            continue;
        }

        $fullpath = "$decompressed_dir/$file";

        if (is_dir($fullpath)) {
            mkdir("$location/$file");
            process_code_file_directory_format($fullpath, $extensions, "$location/$file");
        } else { // is a file
            if (plagiarism_programming_check_extension($file, $extensions)) { // move the file (faster than copy)
                rename($fullpath, "$location/$file");
            }
        }
    }
}

/**
 * Extract students' assignments. This function will extract the students' compressed files and save them temporarily.
 * The directory is $CFG->tempdir/plagiarism_programming/student_id/files
 * @param $assignment: the record object of setting for the assignment
 * @return boolean true if extraction is successful and there are at least 2 students submitted their assignments
 *         boolean false if there are less than 2 students submitted (not need to send for marking)
 */
function plagiarism_programming_extract_assignment($assignment) {
    global $DB;

    echo get_string('extract', 'plagiarism_programming');
    // make a subdir for this assignment in the plugin subdir and emptying it
    $temp_submission_dir = plagiarism_programming_get_assignment_dir($assignment, true);

    // select all the submitted files of this assignment
    $context = context_module::instance($assignment->cmid, IGNORE_MISSING);
    if (!$context) { // $context=false in case when the assignment has been deleted (checked for safety)
        return CONTEXT_NOT_EXIST;
    }
    $file_records = plagiarism_programming_get_submitted_files($context);

    if (count($file_records) < 2) {
        return NOT_SUFFICIENT_SUBMISSION;
    }

    $extensions = plagiarism_programming_get_file_extension($assignment->language);
    $valid_submission = 0;

    foreach ($file_records as $file) {
        $valid_file = false;
        $userid = $file->get_userid();
        $userdir = $temp_submission_dir.$userid.'/';
        if (!is_dir($userdir)) {
            mkdir($userdir);
        }

        $student = $DB->get_record('user', array('id' => $file->get_userid()));
        // check if the file is zipped files
        mtrace("File ".$file->get_filename()." has mime type: ".$file->get_mimetype());

        if (plagiarism_programming_is_compressed_file($file->get_filename())) { // decompress file
            $valid_file = plagiarism_programming_extract_file($file, $extensions, $userdir, $student);
        } else if (plagiarism_programming_check_extension($file->get_filename(), $extensions)) { // if it is an uncompressed code file
            $file->copy_content_to($userdir.$file->get_filename());
            $valid_file = true;
        }
        // TODO: support other types of compression files: 7z, tar.gz

        if ($valid_file) {
            $valid_submission++;
        }
    }

    // include the code teacher upload
    $fs = get_file_storage();
    $additional_code_files = $fs->get_area_files($context->id, 'plagiarism_programming',
            'codeseeding', $assignment->id, '', false);

    $count = 1;
    foreach ($additional_code_files as $code_file) {

        $filename = $code_file->get_filename();
        if (!plagiarism_programming_is_compressed_file($filename)) {
            mtrace("Invalid code seeding file");
            return NOT_CORRECT_FILE_TYPE;
        }

        // create a temporary directory to store the extracted files
        $temp_dir = $temp_submission_dir.'tmp_code'.($count++).'/';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir);
        }

        // extract all files to this directory
        plagiarism_programming_extract_file($code_file, null, $temp_dir, null, false);
        $code_file_type = plagiarism_programming_check_additional_code_structure($temp_dir);
        switch ($code_file_type) {
            case CODE_FILE_ARCHIVE_FORMAT:
                process_code_file_archive_format($temp_dir, $extensions, $temp_submission_dir);
                break;
            case CODE_FILE_DIRECTORY_FORMAT:
                process_code_file_directory_format($temp_dir, $extensions, $temp_submission_dir);
                break;
        }
        plagiarism_programming_rrmdir($temp_dir);
    }

    if ($valid_submission >= 2) {
        return true;
    } else {
        return NOT_CORRECT_FILE_TYPE;
    }
}

/**
 * Submit an assignment to a specified tool. This method must be call after extracting the assignments
 * @param $assignment: the record object of setting for the assignment
 * @param $tool: the tool object (either of type moss_tool or jplag_tool)
 * @param $scan_info: the status record object of that tool
 * @return the updated scan_info object
 */
function plagiarism_programming_submit_assignment($assignment, $tool, $scan_info) {
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
    $DB->update_record('plagiarism_programming_'.$tool_name, $scan_info);

    mtrace("Start sending to $tool_name");
    $temp_submission_dir = plagiarism_programming_get_assignment_dir($assignment);

    // submit the assignment
    $scan_info = $tool->submit_assignment($temp_submission_dir, $assignment, $scan_info);
    $DB->update_record('plagiarism_programming_'.$tool_name, $scan_info);
    mtrace("Finish sending to $tool_name. Status: $scan_info->status");

    // note that scan_info is the object containing
    // the corresponding record in table plagiarism_programming_jplag
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
function plagiarism_programming_check_scanning_status($assignment, $tool, $scan_info) {
    global $DB;
    // if the assignment is processed by the server, ask the server to update the status
    // for every other stages, just return the assignment status since it is updated in parallel
    if ($scan_info->status=='scanning') {
        $scan_info = $tool->check_status($assignment, $scan_info);
        $DB->update_record('plagiarism_programming_'.$tool->get_name(), $scan_info);
    }
    return $scan_info;
}

/**
 * Download the similarity report. The scanning must be finished and its status must be 'done'
 * after calling plagiarism_programming_check_scanning_status function
 * @param $assignment: the record object of setting for the assignment
 * @param $tool: the tool need to check (either moss or jplag)
 * @param $scan_info: the status record object of that tool
 * @return the $scan_info record object with status updated to 'finished' if download has been successful
 */
function plagiarism_programming_download_result($assignment, $tool, $scan_info) {
    global $DB;

    // check and update the status first
    if ($scan_info->status!='done') {
        return;
    }
    $scan_info->status = 'downloading';
    $scan_info->progress = 0;
    $DB->update_record('plagiarism_programming_'.$tool->get_name(), $scan_info);

    mtrace("Download begin!");
    $scan_info = $tool->download_result($assignment, $scan_info);
    mtrace("Download finish!");

    mtrace("Parse begin");
    $scan_info = $tool->parse_result($assignment, $scan_info);
    mtrace("Parse end");
    $DB->update_record('plagiarism_programming_'.$tool->get_name(), $scan_info);
    return $scan_info;
}

/**
 * Check whether the assignment was sent to all the tools or not.
 * @param  $assignment: the object record of the plagiarism setting for the assignment
 * @return true  if the assignment has been sent to all the selected tools
 *         false if there is one tool to which the assignment hasn't been sent
 */
function plagiarism_programming_is_uploaded($assignment) {
    global $DB, $detection_tools;

    $uploaded = true;
    foreach ($detection_tools as $tool_name => $tool_info) {
        if (!$assignment->$tool_name) {
            continue;
        }
        $scan_info = $DB->get_record('plagiarism_programming_'.$tool_name, array('settingid'=>$assignment->id));
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
function plagiarism_programming_scan_assignment($assignment, $wait_for_result=true, $notification_mail=false) {
    global $DB, $CFG, $detection_tools;

    // if the assignment is not submitted, extract them into a temporary directory first
    // this if prevent unnecessary extraction, since another cron can run over when the scanning hasn't finished
    if (!plagiarism_programming_is_uploaded($assignment)) {
        $extract_result = plagiarism_programming_extract_assignment($assignment);
        if ($extract_result===true) {
        } else if ($extract_result==NOT_SUFFICIENT_SUBMISSION || $extract_result==CONTEXT_NOT_EXIST) {
            return;
        } else if ($extract_result==NOT_CORRECT_FILE_TYPE) {
            $message = get_string('invalid_file_type', 'plagiarism_programming')
                .implode(', ', plagiarism_programming_get_file_extension($assignment->language));
            foreach ($detection_tools as $toolname => $tool) {
                if (!$assignment->$toolname) {    // this detector is not selected
                    continue;
                }
                $scan_info = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment->id));
                $scan_info->message = $message;
                $scan_info->status = 'error';
                $DB->update_record('plagiarism_programming_'.$toolname, $scan_info);
            }
            return;
        }
    }

    // send the data
    $links = array();
    $logfiles = array();

    $wait = ($wait_for_result)?1:0;
    $mail = ($notification_mail)?1:0;
    // generating the token
    $token = md5(time()+  rand(1000000, 9999999));
    foreach ($detection_tools as $toolname => $tool) {
        if (!$assignment->$toolname) {    // this detector is not selected
            continue;
        }
        $scan_info = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment->id));
        $scan_info->token = $token;
        $DB->update_record('plagiarism_programming_'.$toolname, $scan_info);
        $links[] = "$CFG->wwwroot/plagiarism/programming/scan_after_extract.php?"
            ."cmid=$assignment->cmid&tool=$toolname&token=$token&wait=$wait&mail=$mail";

        if ($toolname=='jplag_') {
            $logfiles[] = jplag_tool::get_report_path()."/script_log_$assignment->id-$toolname.html";
        } else {
            $logfiles[] = moss_tool::get_report_path()."/script_log_$assignment->id-$toolname.html";
        }
    }

    // register the start scanning time
    $assignment = $DB->get_record('plagiarism_programming', array('id'=>$assignment->id));
    $assignment->latestscan = time();
    $DB->update_record('plagiarism_programming', $assignment);

    plagiarism_programming_curl_download($links, $logfiles);

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
function scan_after_extract_assignment($assignment, $toolname, $wait_to_download=true, $notification_mail=false) {
    global $detection_tools, $DB, $PROCESSING_INFO;
    $tool = $detection_tools[$toolname];
    $tool_class_name = $tool['class_name'];
    $tool_class = new $tool_class_name();
    $scan_info = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment->id));
    assert($scan_info!=null);

    if ($scan_info->status=='pending' || $scan_info->status=='error') {
        // not processed by this tool yet
        $scan_info = plagiarism_programming_submit_assignment($assignment, $tool_class, $scan_info);
    }

    plagiarism_programming_check_scanning_status($assignment, $tool_class, $scan_info);
    if ($wait_to_download && $scan_info->status=='scanning') {
        // waiting for the detectors to process
        // check if the result is available
        while ($scan_info->status!='done') {
            sleep(5);
            plagiarism_programming_check_scanning_status($assignment, $tool_class, $scan_info);
        }
    } else if ($scan_info->status=='scanning'){
        $PROCESSING_INFO['scanning_not_wait'] = 1;
    }

    if ($scan_info->status=='done') {
        debugging("Start downloading with $toolname \n");
        plagiarism_programming_download_result($assignment, $tool_class, $scan_info);
        debugging("Finish downloading with $toolname. Status: $scan_info->status \n");

        if ($notification_mail) {
            plagiarism_programming_send_scanning_notification_email($assignment, $toolname);
        }
    }
}

/**
 * This function will update scanning status with error message when a fatal error occurs.
 * It is registered by register_shutdown_function in start_scanning.php and in scan_after_extract.php scripts
 * Should never be called in code.
 */
function plagiarism_programming_handle_shutdown() {
    global $DB, $CFG, $detection_tools;
    global $PROCESSING_INFO;  // this global is an array consisting of stage and cmid

    $error = error_get_last();
    if ($PROCESSING_INFO && $error && ($error['type']==E_ERROR)) {
        // the value is one of "extract" (extraction of files before MOSS and JPlag), "jplag" or "moss"
        $stage = $PROCESSING_INFO['stage'];
        $cmid = $PROCESSING_INFO['cmid'];

        $assignment = $DB->get_record('plagiarism_programming', array('cmid'=>$cmid));

        /** If extract, set all statuses  */
        if ($stage=='extract') {
            $tools = array_keys($detection_tools);
        } else {
            $tools = array($stage);
        }

        foreach ($tools as $tool) {
            if ($assignment->$tool) {
                $scan_info = $DB->get_record('plagiarism_programming_'.$tool, array('settingid'=>$assignment->id));
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
                $DB->update_record('plagiarism_programming_'.$tool, $scan_info);
            }
        }
    }

    if ($PROCESSING_INFO && $PROCESSING_INFO['stage']!='extract' && !isset($PROCESSING_INFO['scanning_not_wait'])) {
        $cmid = $PROCESSING_INFO['cmid'];
        $tool = $PROCESSING_INFO['stage'];
        $assignment = $DB->get_record('plagiarism_programming', array('cmid'=>$cmid));
        $scan_info = $DB->get_record('plagiarism_programming_'.$tool, array('settingid'=>$assignment->id));
        echo 'Before shutdown: status: '.$scan_info->status;
        if ($scan_info->status!='error' && $scan_info->status!='finished') {
            $scan_info->status = 'error';
            $scan_info->message = 'An unknown error has occurred!';
            $DB->update_record('plagiarism_programming_'.$tool, $scan_info);
        }
    }
    $file = "{$CFG->tempdir}/plagiarism_report/".$PROCESSING_INFO['stage'].'.txt';
    $content = ob_get_contents();
    file_put_contents($file, $content);
}