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
 * Initiate the plagiarism scanning for all assignments of which the scanning date already passed.
 *
 * Called by the cron script.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

// Used in extract_assignment.
define('NOT_SUFFICIENT_SUBMISSION', 1);
define('CONTEXT_NOT_EXIST', 2);
define('NOT_CORRECT_FILE_TYPE', 3);

define('CODE_FILE_DIRECTORY_FORMAT', 'code_file_directory_format');
define('CODE_FILE_ARCHIVE_FORMAT', 'code_file_archive_format');
define('CODE_FILE_SINGLE_SUBMISSION_FORMAT', 'code_file_single_submission_format');

global $CFG;

require_once(__DIR__ . '/utils.php');
require_once(__DIR__ . '/plagiarism_tool.php');
require_once(__DIR__ . '/jplag_tool.php');
require_once(__DIR__ . '/moss_tool.php');
require_once(__DIR__ . '/utils.php');

define('PLAGIARISM_TEMP_DIR', "{$CFG->tempdir}/plagiarism_programming/");

/**
 * Create a temporary directory for this plugin in $CFG->tempdir/plagiarism_programming/ directory
 */
function plagiarism_programming_create_temp_dir() {
    if (!is_dir(PLAGIARISM_TEMP_DIR)) {
        mkdir(PLAGIARISM_TEMP_DIR);
    }
}

/**
 * Create the temporary directory for the assignment where student's code will be extracted.
 * @param Object $assignment The record object of setting for the assignment
 * @param boolean $emptydir
 * @return string A temporary directory that all files of this assignment will be stored
 */
function plagiarism_programming_get_assignment_dir($assignment, $emptydir = false) {
    $dir = PLAGIARISM_TEMP_DIR . $assignment->cmid . '/';
    if ($emptydir) {
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
 *
 * @param context_module $assignmentcontext The context of the assignment
 */
function plagiarism_programming_get_submitted_files($assignmentcontext) {
    global $CFG;

    $cm = get_coursemodule_from_id('', $assignmentcontext->instanceid);
    if ($cm->modname == 'assign') {
        $component = 'assignsubmission_file';
        $filearea = 'submission_files';
    } else {
        $component = 'mod_assignment';
        $filearea = 'submission';
    }
    $fs = get_file_storage();
    $filerecords = $fs->get_area_files($assignmentcontext->id, $component, $filearea, false, 'userid', false);
    return $filerecords;
}

/**
 * Extract a compressed file, either zip or rar
 */
/**
 * Extracts either zip or rar.
 * @param Object $file
 * @param Array $extensions
 * @param String $location
 * @param stdClass $user
 * @param boolean $textfileonly
 * @return true
 */
function plagiarism_programming_extract_file($file, $extensions, $location, $user = null, $textfileonly = true) {
    if ($file instanceof stored_file) {
        $tempfilepath = dirname($location) . '/' . $file->get_filename();
        $file->copy_content_to($tempfilepath);
        $filename = $file->get_filename();
        $fileextension = substr($filename, -4, 4);
    } else { // File is a string.
        $tempfilepath = $file;
        $filename = basename($tempfilepath);
        $fileextension = substr($filename, -4, 4);
    }
    if ($fileextension == '.zip') {
        $validfile = plagiarism_programming_extract_zip($tempfilepath, $extensions, $location, $user, $textfileonly);
    } else if ($fileextension == '.rar') {
        $validfile = plagiarism_programming_extract_rar($tempfilepath, $extensions, $location, $user, $textfileonly);
    } else {
        debugging("Error extracting file: " . $filename . ' is not a compressed file');
    }

    if ($file instanceof stored_file) {
        unlink($tempfilepath);
    }
    return $validfile;
}
/**
 * Determine the validity of the structure of the additional code file (submitted when editing an assignment).
 * The zip file could contain:
 * + an assignment as a whole (in this case, there must be at least 1 file
 * at the top most directory level
 * + a number of directories, each contains an assignment
 * + a number zip files, each contains an assignment
 * @param String $decompresseddir
 * @return NULL|string
 */
function plagiarism_programming_check_additional_code_structure($decompresseddir) {
    if (!is_dir($decompresseddir)) {
        debugging("$decompresseddir is not a directory to decompress");
        return null;
    }

    $notalldir = false;
    $notallcompressed = false;
    $filelist = scandir($decompresseddir);
    foreach ($filelist as $file) {
        if (!is_dir($decompresseddir . '/' . $file)) {
            $notalldir = true;
            if (!plagiarism_programming_is_compressed_file($file)) {
                $notallcompressed = true;
            }
        }
        if ($notalldir && $notallcompressed) {
            break;
        }
    }
    if (!$notalldir) { // All directory, should be.
        return CODE_FILE_DIRECTORY_FORMAT;
    } else if (!$notallcompressed) {
        return CODE_FILE_ARCHIVE_FORMAT;
    } else {
        return CODE_FILE_SINGLE_SUBMISSION_FORMAT;
    }
}

/**
 * Extract the additional code file submitted in assignment settings.
 * @param String $decompresseddir
 * @param array $extensions
 * @param String $location
 * @return NULL
 */
function process_code_file_archive_format($decompresseddir, array $extensions, $location) {
    if (!is_dir($decompresseddir)) {
        debugging("$decompresseddir is not a directory!");
        return null;
    }

    $files = scandir($decompresseddir);
    foreach ($files as $file) {

        if ($file == '.' || $file == '..') {
            continue;
        }

        $filefullpath = "$decompresseddir/$file";
        // It is assumed that file must all be zip or rar.
        if (!plagiarism_programming_is_compressed_file($filefullpath)) {
            debugging("File $file is not a compressed file");
            continue;
        }

        plagiarism_programming_extract_file($filefullpath, $extensions, "$location/ext_$file/");
    }
}

/**
 * Prepares the file directory.
 * @param String $decompresseddir
 * @param array $extensions File extensions which are used.
 * @param String $location Location where the files are saved
 */
function process_code_file_directory_format($decompresseddir, array $extensions, $location) {
    if (!is_dir($decompresseddir)) {
        debugging("$decompresseddir is not a directory");
        return null;
    }

    $files = scandir($decompresseddir);
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }

        $fullpath = "$decompresseddir/$file";

        if (is_dir($fullpath)) {
            mkdir("$location/$file");
            process_code_file_directory_format($fullpath, $extensions, "$location/$file");
        } else { // Not a directory but a file.
            if (plagiarism_programming_check_extension($file, $extensions)) {
                // Move the file (faster than copy).
                rename($fullpath, "$location/$file");
            }
        }
    }
}

/**
 * Extract students' assignments.
 * This function will extract the students' compressed files and save them temporarily.
 * The directory is $CFG->tempdir/plagiarism_programming/student_id/files
 * @param Object $assignment The record object of setting for the assignment
 * @return boolean true if extraction is successful and there are at least 2 students submitted their assignments
 *         boolean false if there are less than 2 students submitted (not need to send for marking)
 */
function plagiarism_programming_extract_assignment($assignment) {
    global $DB;

    echo "[".date(DATE_RFC822)."] ".get_string('extract', 'plagiarism_programming')."\n";
    // Make a subdir for this assignment in the plugin subdir and emptying it.
    $tempsubmissiondir = plagiarism_programming_get_assignment_dir($assignment, true);

    // Select all the submitted files of this assignment.
    $context = context_module::instance($assignment->cmid, IGNORE_MISSING);
    if (!$context) { // It is $context=false when the assignment has been deleted (checked for safety).
        return CONTEXT_NOT_EXIST;
    }
    $filerecords = plagiarism_programming_get_submitted_files($context);

    if (count($filerecords) < 2) {
        return NOT_SUFFICIENT_SUBMISSION;
    }

    $extensions = plagiarism_programming_get_file_extension($assignment->language);
    $validsubmissions = 0;

    foreach ($filerecords as $file) {
        $validfile = false;
        $userid = $file->get_userid();
        $userdir = $tempsubmissiondir . $userid . '/';
        if (!is_dir($userdir)) {
            mkdir($userdir);
        }

        $student = $DB->get_record('user', array(
            'id' => $file->get_userid()
        ));
        // Check if the file is zipped files.
        if (plagiarism_programming_is_compressed_file($file->get_filename())) { // Decompress file.
            $validfile = plagiarism_programming_extract_file($file, $extensions, $userdir, $student);
        } else if (plagiarism_programming_check_extension($file->get_filename(), $extensions)) {
            // If it is an uncompressed code file.
            $file->copy_content_to($userdir . $file->get_filename());
            $validfile = true;
        }

        // TODO Support other types of compression files: 7z, tar.gz.
        if ($validfile) {
            $validsubmissions++;
        }
    }

    // Include the code teacher upload.
    $fs = get_file_storage();
    $additionalcodefiles = $fs->get_area_files($context->id, 'plagiarism_programming', 'codeseeding', $assignment->id, '', false);

    $count = 1;
    foreach ($additionalcodefiles as $codefile) {

        $filename = $codefile->get_filename();
        if (!plagiarism_programming_is_compressed_file($filename)) {
            mtrace("Invalid code seeding file");
            return NOT_CORRECT_FILE_TYPE;
        }

        // Create a temporary directory to store the extracted files.
        $tempdir = $tempsubmissiondir . 'tmp_code' . ($count++ ) . '/';
        if (!is_dir($tempdir)) {
            mkdir($tempdir);
        }

        // Extract all files to this directory.
        plagiarism_programming_extract_file($codefile, null, $tempdir, null, false);
        $codefiletype = plagiarism_programming_check_additional_code_structure($tempdir);
        switch ($codefiletype) {
            case CODE_FILE_ARCHIVE_FORMAT:
                process_code_file_archive_format($tempdir, $extensions, $tempsubmissiondir);
                break;
            case CODE_FILE_DIRECTORY_FORMAT:
                process_code_file_directory_format($tempdir, $extensions, $tempsubmissiondir);
                break;
        }
        plagiarism_programming_rrmdir($tempdir);
    }

    if ($validsubmissions >= 2) {
        return true;
    } else {
        return NOT_CORRECT_FILE_TYPE;
    }
}

/**
 * Submit an assignment to a specified tool.
 * This method must be call after extracting the assignments
 *
 * @param Object $assignment The record object of setting for the assignment
 * @param Object $tool The tool object (either of type moss_tool or jplag_tool)
 * @param Object $scaninfo The status record object of that tool
 * @return $scaninfo The updated scan_info object
 */
function plagiarism_programming_submit_assignment($assignment, $tool, $scaninfo) {
    global $DB;
    $toolname = $tool->get_name();

    // Status must be pending or error.
    if ($scaninfo->status != 'pending' && $scaninfo->status != 'error') {
        // Don't need to submit.
        return $scaninfo;
    }
    // Update the status to uploading.
    $scaninfo->status = 'uploading';
    $scaninfo->progress = 0;
    $DB->update_record('plagiarism_programming_' . $toolname, $scaninfo);

    mtrace("[".date(DATE_RFC822)."] Start sending to $toolname");
    $tempsubmissiondir = plagiarism_programming_get_assignment_dir($assignment);

    // Submit the assignment.
    $scaninfo = $tool->submit_assignment($tempsubmissiondir, $assignment, $scaninfo);
    $DB->update_record('plagiarism_programming_' . $toolname, $scaninfo);
    mtrace("[".date(DATE_RFC822)."] Finish sending to $toolname. Status: $scaninfo->status");

    // Note that scan_info is the object containing the corresponding record in table plagiarism_programming_jplag.
    return $scaninfo;
}

/**
 * Return the updated status of the assignment.
 * Use this function to check whether the scanning has been done.
 * This function also update the status record of the tool in the database
 *
 * @param Object $assignment The record object of setting for the assignment
 * @param Object $tool The tool object (either of type moss_tool or jplag_tool)
 * @param Object $scaninfo The status record object of that tool
 * @return $scaninfo The updated scan_info object.
 */
function plagiarism_programming_check_scanning_status($assignment, $tool, $scaninfo) {
    global $DB;
    // If the assignment is processed by the server, ask the server to update the status.
    // For every other stages, just return the assignment status since it is updated in parallel.
    if ($scaninfo->status == 'scanning') {
        $scaninfo = $tool->check_status($assignment, $scaninfo);
        $DB->update_record('plagiarism_programming_' . $tool->get_name(), $scaninfo);
    }
    return $scaninfo;
}

/**
 * Download the similarity report.
 * The scanning must be finished and its status must be 'done'
 * after calling plagiarism_programming_check_scanning_status function
 *
 * @param Object $assignment The record object of setting for the assignment
 * @param Object $tool The tool object (either of type moss_tool or jplag_tool)
 * @param Object $scaninfo The status record object of that tool
 * @return $scan_info Record object with status updated to 'finished' if download has been successful
 */
function plagiarism_programming_download_result($assignment, $tool, $scaninfo) {
    global $DB;

    // Check and update the status first.
    if ($scaninfo->status != 'done') {
        return;
    }
    $scaninfo->status = 'downloading';
    $scaninfo->progress = 0;
    $DB->update_record('plagiarism_programming_' . $tool->get_name(), $scaninfo);

    mtrace("[".date(DATE_RFC822)."] Download begin!");
    $scaninfo = $tool->download_result($assignment, $scaninfo);
    mtrace("[".date(DATE_RFC822)."] Download finish!");

    mtrace("[".date(DATE_RFC822)."] Parse begin");
    $scaninfo = $tool->parse_result($assignment, $scaninfo);
    mtrace("[".date(DATE_RFC822)."] Parse end");
    $DB->update_record('plagiarism_programming_' . $tool->get_name(), $scaninfo);
    return $scaninfo;
}

/**
 * Check whether the assignment was sent to all the tools or not.
 *
 * @param Object $assignment The record object of setting for the assignment
 * @return true if the assignment has been sent to all the selected tools
 *         false if there is one tool to which the assignment hasn't been sent
 */
function plagiarism_programming_is_uploaded($assignment) {
    global $DB, $detectiontools;

    $uploaded = true;
    foreach ($detectiontools as $toolname => $toolinfo) {
        if (!$assignment->$toolname) {
            continue;
        }
        $scaninfo = $DB->get_record('plagiarism_programming_' . $toolname, array(
            'settingid' => $assignment->id
        ));
        if (!$scaninfo || $scaninfo->status == 'pending' || $scaninfo->status == 'finished' || $scaninfo->status == 'error') {
            $uploaded = false;
        }
    }
    return $uploaded;
}

/**
 * The entry call to scan an assignment.
 * First, students' assignments are extracted (if it is a compressed files),
 * then this function forks separate processes to scan the report
 *
 * @param Object $assignment The record object of setting for the assignment
 * @param Boolean $waitforresult Wait for the scanning to finish to get the result (default true)
 * @param Boolean $notificationmail If a mail should be sent
 * @return void
 */
function plagiarism_programming_scan_assignment($assignment, $waitforresult = true, $notificationmail = false) {
    global $DB, $CFG, $detectiontools;

    // If the assignment is not submitted, extract them into a temporary directory first.
    // This if prevent unnecessary extraction, since another cron can run over when the scanning hasn't finished.
    if (!plagiarism_programming_is_uploaded($assignment)) {
        $extractresult = plagiarism_programming_extract_assignment($assignment);
        if ($extractresult === true) {
            // TODO: What should be done here? Empty if
        } else if ($extractresult == NOT_SUFFICIENT_SUBMISSION || $extractresult == CONTEXT_NOT_EXIST) {
            return;
        } else if ($extractresult == NOT_CORRECT_FILE_TYPE) {
            $message = get_string('invalid_file_type', 'plagiarism_programming').implode(', ', plagiarism_programming_get_file_extension($assignment->language));
            foreach ($detectiontools as $toolname => $tool) {
                if (!$assignment->$toolname) { // This detector is not selected.
                    continue;
                }
                $scaninfo = $DB->get_record('plagiarism_programming_' . $toolname, array('settingid' => $assignment->id));
                $scaninfo->message = $message;
                $scaninfo->status = 'error';
                $DB->update_record('plagiarism_programming_' . $toolname, $scaninfo);
            }
            return;
        }
    }

    // Send the data.
    $links = array();
    $logfiles = array();

    $wait = ($waitforresult) ? 1 : 0;
    $mail = ($notificationmail) ? 1 : 0;
    // Generating the token.
    $token = md5(time() + rand(1000000, 9999999));
    foreach ($detectiontools as $toolname => $tool) {
        if (!$assignment->$toolname) { // This detector is not selected.
            continue;
        }
        $scaninfo = $DB->get_record('plagiarism_programming_' . $toolname, array('settingid' => $assignment->id));
        $scaninfo->token = $token;
        $DB->update_record('plagiarism_programming_' . $toolname, $scaninfo);
        $links[] = "$CFG->wwwroot/plagiarism/programming/scan_after_extract.php?"
        ."cmid=$assignment->cmid&tool=$toolname&token=$token&wait=$wait&mail=$mail";

        if ($toolname == 'jplag_') {
            $logfiles[] = jplag_tool::get_report_path() . "/script_log_$assignment->id-$toolname.html";
        } else {
            $logfiles[] = moss_tool::get_report_path() . "/script_log_$assignment->id-$toolname.html";
        }
    }

    // Register the start scanning time.
    $assignment = $DB->get_record('plagiarism_programming', array('id' => $assignment->id));
    $assignment->latestscan = time();
    $DB->update_record('plagiarism_programming', $assignment);

    plagiarism_programming_curl_download($links, $logfiles);

    foreach ($logfiles as $logfile) {
        echo file_get_contents($logfile);
        echo "\n";
    }
}

/**
 * This function is used by scan_assignment to send an assignment to a specific tool.
 * The assignment is first sent to the tool,
 * then either wait until the scanning finished to download or download it latter. In the second case, calling the function another
 * time will check the scanning status and, if finished, download it again.
 *
 * @param Object $assignment The record object of setting for the assignment
 * @param String $toolname The name of the tool
 * @param Boolean $waittodownload If set to true, the scanning will wait and periodically check the status until it finish and download
 * @param Boolean $notificationmail If a mail should be sent
 */
function scan_after_extract_assignment($assignment, $toolname, $waittodownload = true, $notificationmail = false) {
    global $detectiontools, $DB, $processinginfo;
    $tool = $detectiontools[$toolname];
    $toolclassname = $tool['class_name'];
    $toolclass = new $toolclassname();
    $scaninfo = $DB->get_record('plagiarism_programming_' . $toolname, array(
        'settingid' => $assignment->id
    ));
    assert($scaninfo != null);

    if ($scaninfo->status == 'pending' || $scaninfo->status == 'error') {
        // Not processed by this tool yet.
        $scaninfo = plagiarism_programming_submit_assignment($assignment, $toolclass, $scaninfo);
    }

    plagiarism_programming_check_scanning_status($assignment, $toolclass, $scaninfo);
    if ($waittodownload && $scaninfo->status == 'scanning') {
        // Waiting for the detectors to process check if the result is available.
        while ($scaninfo->status != 'done') {
            sleep(5);
            plagiarism_programming_check_scanning_status($assignment, $toolclass, $scaninfo);
        }
    } else if ($scaninfo->status == 'scanning') {
        $processinginfo['scanning_not_wait'] = 1;
    }

    if ($scaninfo->status == 'done') {
        mtrace("[".date(DATE_RFC822)."] Start downloading with $toolname \n");
        plagiarism_programming_download_result($assignment, $toolclass, $scaninfo);
        mtrace("[".date(DATE_RFC822)."] Finish downloading with $toolname. Status: $scaninfo->status \n");

        /* Do not send emails as this cannot be changed in settings and task API is used instead of legacy cron
         * It also triggers a bug because of removed functions
        if ($notificationmail) {
            plagiarism_programming_send_scanning_notification_email($assignment, $toolname);
        }
        */
    }
}

/**
 * This function will update scanning status with error message when a fatal error occurs.
 * It is registered by register_shutdown_function in start_scanning.php and in scan_after_extract.php scripts
 * Should never be called in code.
 */
function plagiarism_programming_handle_shutdown() {
    global $DB, $CFG, $detectiontools;
    global $processinginfo; // This global is an array consisting of stage and cmid.

    $error = error_get_last();
    if ($processinginfo && $error && ($error['type'] == E_ERROR)) {
        // The value is one of "extract" (extraction of files before MOSS and JPlag), "jplag" or "moss".
        $stage = $processinginfo['stage'];
        $cmid = $processinginfo['cmid'];

        $assignment = $DB->get_record('plagiarism_programming', array(
            'cmid' => $cmid
        ));

        // If extract, set all statuses.
        if ($stage == 'extract') {
            $tools = array_keys($detectiontools);
        } else {
            $tools = array(
                $stage
            );
        }

        foreach ($tools as $tool) {
            if ($assignment->$tool) {
                $scaninfo = $DB->get_record('plagiarism_programming_' . $tool, array(
                    'settingid' => $assignment->id
                ));
                $scaninfo->status = 'error';
                $message = get_string('unexpected_error', 'plagiarism_programming');
                if ($stage == 'extract') {
                    $message = get_string('unexpected_error_extract', 'plagiarism_programming');
                } else if ($scaninfo->status == 'uploading') {
                    $message = get_string('unexpected_error_upload', 'plagiarism_programming');
                } else if ($scaninfo->status == 'downloading') {
                    $message = get_string('unexpected_error_download', 'plagiarism_programming');
                }
                $scaninfo->message = $message;
                $DB->update_record('plagiarism_programming_' . $tool, $scaninfo);
            }
        }
    }

    if ($processinginfo && $processinginfo['stage'] != 'extract' && !isset($processinginfo['scanning_not_wait'])) {
        $cmid = $processinginfo['cmid'];
        $tool = $processinginfo['stage'];
        $assignment = $DB->get_record('plagiarism_programming', array(
            'cmid' => $cmid
        ));
        $scaninfo = $DB->get_record('plagiarism_programming_' . $tool, array(
            'settingid' => $assignment->id
        ));
        echo "[".date(DATE_RFC822)."] Before shutdown: status: " . $scaninfo->status;
        if ($scaninfo->status != 'error' && $scaninfo->status != 'finished') {
            $scaninfo->status = 'error';
            $scaninfo->message = 'An unknown error has occurred!';
            $DB->update_record('plagiarism_programming_' . $tool, $scaninfo);
        }
    }
    $file = "{$CFG->tempdir}/plagiarism_report/" . $processinginfo['stage'] . '.txt';
    $content = ob_get_contents();
    file_put_contents($file, $content);
}