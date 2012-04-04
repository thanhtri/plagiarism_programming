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

global $CFG;

include_once dirname(__FILE__).'/utils.php';
include_once dirname(__FILE__).'/plagiarism_tool.php';
include_once dirname(__FILE__).'/jplag_tool.php';
include_once dirname(__FILE__).'/moss_tool.php';

define('PLAGIARISM_TEMP_DIR', $CFG->dataroot.'/temp/plagiarism_programming/');

function create_temporary_dir() {
    if (is_dir(PLAGIARISM_TEMP_DIR)) {
        rrmdir(PLAGIARISM_TEMP_DIR);
    }
    mkdir(PLAGIARISM_TEMP_DIR);
}

/** Return the temporary directory for the assignment.
 *  Students' code will be extracted here
 */
function get_temp_dir_for_assignment($assignment) {
    return PLAGIARISM_TEMP_DIR.$assignment->courseid.'/';
}

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
 */
function clear_student_name(&$filecontent,$userid) {
    global $DB;
    // find all the comments. First version just support C++ style comments
    $pattern = '/\/\*.*?\*\//s'; // C style
    preg_match_all($pattern, $filecontent, $comments1);
    $pattern = '/\/\/.*/';		// C++ style
    preg_match_all($pattern, $filecontent, $comments2);
    $allcomments = array_merge($comments1[0],$comments2[0]);

    // get student name
    $student = $DB->get_record('user',array('id'=>$userid));
    $fname = $student->firstname;
    $lname = $student->lastname;
    $find_array = array($fname,$lname,$student->idnumber);
    $replace_array = array('#firstname','#lastname','#id');

    $finds = array();
    $replaces = array();

    foreach ($allcomments as $comment) {
        if (stripos($comment, $fname)!=false || stripos($comment, $lname)!=false || strpos($comment,$userid)!=false) {
            $newComment = str_ireplace($find_array,$replace_array,$comment);
            $finds[]= $comment;
            $replaces[]=$newComment;
        } elseif (strpos($comment, 'author')!=false) { // to be safe, delete the comment with author inside, maybe the student write his name in another form
            $finds[]= $comment;
            $replaces[]= '';
        }
    }
    $filecontent = str_replace($finds, $replaces, $filecontent);
}

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
                    clear_student_name($buf,$file->get_userid());
                    fwrite($fp,"$buf");
                    zip_entry_close($zip_entry);
                    fclose($fp);
                }
            }
        }
    }
}

function extract_assignment($assignment) {
    echo "Extracting files\n";
    // make a subdir for this assignment in the plugin subdir
    $temp_submission_dir = get_temp_dir_for_assignment($assignment);
    if (!is_dir($temp_submission_dir)) {
        mkdir($temp_submission_dir);
    }

    // select all the submitted files of this assignment
    $context = get_context_instance(CONTEXT_MODULE,$assignment->courseid,MUST_EXIST);
    $fs = get_file_storage();
    $file_records = $fs->get_area_files($context->id, 'mod_assignment', 'submission', false, 'userid', false);

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
        } elseif (check_extension($file->get_filename(), $extensions)) { // if it is an uncompressed code file
            $file->copy_content_to($userdir);
        }
        // TODO: support other types of compression files, e.g. rar, 7z
    }
}

function submit_assignment($assignment,$tool,$scan_info) {
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
    
    echo "Start sending to $tool_name \n";
    $temp_submission_dir = get_temp_dir_for_assignment($assignment);

    // submit the assignment
    $scan_info = $tool->submit_assignment($temp_submission_dir, $assignment, $scan_info);
    $DB->update_record('programming_'.$tool_name, $scan_info);
    echo "Finish sending to $tool_name. Status: ".$scan_info->status."\n";

    // note that scan_info is the object containing
    // the corresponding record in table programming_jplag
    return $scan_info;
}

function check_scanning_status($assignment,$tool,$scan_info) {
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
 * Download and parse the result
 */
function download_result($assignment,$tool,$scan_info) {
    global $DB;

    
    // check and update the status first
    if ($scan_info->status!='done') {
        return;
    }
    $scan_info->status = 'downloading';
    $scan_info->progress = 0;
    $DB->update_record('programming_'.$tool->get_name(), $scan_info);

    echo "Download begin!\n";
    $scan_info = $tool->download_result($assignment,$scan_info);
    echo "Download finish!\n";

    echo "Parse begin\n";
    $scan_info = $tool->parse_result($assignment,$scan_info);
    echo "Parse end\n";
    $DB->update_record('programming_'.$tool->get_name(), $scan_info);
    return $scan_info;
}

function is_all_tools_uploaded($assignment) {
    global $DB, $detection_tools;

    $uploaded = true;
    foreach ($detection_tools as $tool_name=>$tool_info) {
        if (!$assignment->$tool_name)
            continue;
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        if (!$scan_info || $scan_info->status=='pending' || $scan_info->status=='error') {
            $uploaded = false;
        }
    }
    return $uploaded;
}

function scan_assignment($assignment,$check_together=true) {
    global $DB, $detection_tools;
    // if the assignment is not submitted, extract them into a temporary directory first
    if (!is_all_tools_uploaded($assignment)) {
        extract_assignment($assignment);
    }

    // send the data
    foreach ($detection_tools as $toolname=>$tool) {
        if (!$assignment->$toolname)    // this detector is not selected
            continue;

        $tool_class_name = $tool['class_name'];
        $tool_class = new $tool_class_name();
        $scan_info = $DB->get_record('programming_'.$toolname, array('settingid'=>$assignment->id));

        if (!$scan_info) { // the record hasn't been created yet
            $scan_info = new stdClass();
            $scan_info->settingid=$assignment->id;
            $scan_info->status='pending';
            $scan_info->id = $DB->insert_record('programming_'.$toolname,$scan_info);
        }

        if ($scan_info->status=='pending' || $scan_info->status=='error') {
            // not processed by this tool yet
            $scan_info = submit_assignment($assignment, $tool_class, $scan_info);
        }

        if (!$check_together)
            continue;

        if ($scan_info->status=='scanning') {
            // waiting for the detectors to process
            // check if the result is available
            echo "Start checking with $toolname \n";
            check_scanning_status($assignment, $tool_class, $scan_info);
            echo "Finished checking with $toolname. Status: $scan_info->status. Progress: $scan_info->progress \n";
        }

        if ($scan_info->status=='done') {
            echo "Start downloading with $toolname \n";
            download_result($assignment, $tool_class, $scan_info);
            echo "Finish downloading with $toolname. Status: $scan_info->status \n";
        }        
    }
    $DB->update_record('programming_plagiarism',$assignment);
}
