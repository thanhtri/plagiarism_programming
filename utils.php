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
 * Common functions which are used in many place
 *
 * @package plagiarism
 * @subpackage programming
 * @author thanhtri
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Return an array containing the possible extensions
 * of source code file of the provided language
 *
 * @param $language {string}
 *            Either java, c, csharp
 * @return array of extensions of the language
 */
function plagiarism_programming_get_file_extension($language) {
    $extensions = array();
    switch ($language) {
        case 'java':
            $extensions = array(
                'java',
                'JAVA',
                'Java'
            );
            break;
        case 'c':
            $extensions = array(
                'h',
                'c',
                'cpp',
                'C',
                'CPP',
                'H'
            );
            break;
        case 'c#':
            $extensions = array(
                'cs',
                'CS',
                'Cs'
            );
            break;
        case 'scheme':
            $extensions = array(
                'scm',
                'SCM'
            );
            break;
        case 'plsql':
            $extensions = array(
                'sql',
                'pls',
                'pks'
            );
            break;
        case 'pascal':
            $extensions = array(
                'pas',
                'tp',
                'bp',
                'p'
            );
            break;
        case 'perl':
            $extensions = array(
                'pl',
                'PL'
            );
            break;
        case 'python':
            $extensions = array(
                'py',
                'PY'
            );
            break;
        case 'vb':
            $extensions = array(
                'vb',
                'VB',
                'Vb'
            );
            break;
        case 'javascript':
            $extensions = array(
                'js',
                'JS',
                'Js'
            );
            break;
        case 'text':
            $extensions = array(
                'txt'
            );
            break;
    }
    return $extensions;
}

/**
 * Check if the file has the appropriate extension
 *
 * @param $filename {string}
 *            name of the file
 * @param $extension {array}
 *            an array of possible extensions
 * @return true if the file has the extension in the array
 */
function plagiarism_programming_check_extension($filename, $extensions) {
    if ($extensions == null) { // If extensions array is null, accept all extension.
        return true;
    }
    $dotindex = strrpos($filename, '.');

    if ($dotindex === false) {
        return false;
    }

    $ext = substr($filename, $dotindex + 1);
    return in_array($ext, $extensions);
}

/**
 * Create a file for write.
 * This function will create a directory along the file path if it doesn't exist
 *
 * @param $fullpath {string}
 *            The path of the file
 * @return {object} the write file handle. fclose have to be called when finishing with it
 */
function plagiarism_programming_create_file($fullpath) {
    $dirpath = dirname($fullpath);
    if (is_dir($dirpath)) { // Directory already exist.
        return fopen($fullpath, 'w');
    } else {
        $dirs = explode('/', $dirpath);
        $path = '';
        foreach ($dirs as $dir) {
            $path .= $dir . '/';
            if (!is_dir($path)) {
                mkdir($path);
            }
        }
        return fopen($fullpath, 'w');
    }
}

/**
 * Search the file to clear the students' name and clear them
 *
 * @param $filecontent: the
 *            content of a file
 * @param $student {object}
 *            the user record object of the students. Name and id occurrences will be cleared
 */
function plagiarism_programming_annonymise_students(&$filecontent, $student) {
    if ($student == null) { // Do not have information to clear.
        return;
    }
    // Dind all the comments. First version just support C++ style comments.
    $pattern = '/\/\*.*?\*\//s'; // C style.
    $comments1 = array();
    preg_match_all($pattern, $filecontent, $comments1);
    $pattern = '/\/\/.*/'; // C++ style.
    $comments2 = array();
    preg_match_all($pattern, $filecontent, $comments2);
    $allcomments = array_merge($comments1[0], $comments2[0]);

    // Get student name.
    $fname = $student->firstname;
    $lname = $student->lastname;
    $idnumber = $student->idnumber;
    $findarray = array(
        $fname,
        $lname,
        $idnumber
    );
    $replacearray = array(
        '#firstname',
        '#lastname',
        '#id'
    );

    $finds = array();
    $replaces = array();

    foreach ($allcomments as $comment) {
        if (stripos($comment, $fname) != false || stripos($comment, $lname) != false || (!empty($idnumber) && strpos($comment, $idnumber) != false)) {
            $newcomment = str_ireplace($findarray, $replacearray, $comment);
            $finds[] = $comment;
            $replaces[] = $newcomment;
            // To be safe, delete the comment with author inside, maybe the student write his name in another form.
        } else if (strpos($comment, 'author') !== false) {
            $finds[] = $comment;
            $replaces[] = '';
        }
    }
    $filecontent = str_replace($finds, $replaces, $filecontent);
}

function plagiarism_programming_send_scanning_notification_email($assignment, $toolname) {
    global $CFG, $DB;

    $contextassignment = context_module::instance($assignment->cmid);
    $cm = get_coursemodule_from_id('', $assignment->cmid);

    $markers = get_enrolled_users($contextassignment, "mod/$cm->modname:grade");
    $moodlesupport = generate_email_supportuser();
    $course = $DB->get_record('course', array(
        'id' => $cm->course
    ));
    $assign = $DB->get_record($cm->modname, array(
        'id' => $cm->instance
    ));

    $emailparams = array(
        'course_short_name' => $course->shortname,
        'course_name' => $course->fullname,
        'assignment_name' => $assign->name,
        'time' => userdate(time(), get_string('strftimerecent')),
        'link' => "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$assignment->cmid&detector=$toolname"
    );

    $markerscount = count($markers);
    mtrace("Sending email to $markerscount markers\n");
    foreach ($markers as $marker) {
        mtrace("Email to $marker->firstname $marker->lastname\n");
        $emailparams['recipientname'] = fullname($marker);
        email_to_user($marker, $moodlesupport,
            get_string('scanning_complete_email_notification_subject', 'plagiarism_programming', $emailparams),
            get_string('scanning_complete_email_notification_body_txt', 'plagiarism_programming', $emailparams),
            get_string('scanning_complete_email_notification_body_html', 'plagiarism_programming', $emailparams));
    }
}

/**
 * Delete a directory (taken from php.net) with all of its sub-dirs and files
 *
 * @param string $dir:
 *            the directory to be deleted
 */
function plagiarism_programming_rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . '/' . $object) == 'dir') {
                    plagiarism_programming_rrmdir($dir . "/" . $object);
                } else {
                    unlink($dir . '/' . $object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Get a list of links in parallel.
 * curl is used to call the parallel
 *
 * @param array $links:
 *            list of links
 * @param mixed $directory:
 *            if null, the contents get by the links will not be stored, if a string, the content
 *            will be stored in that directory, if an array, each link will be saved in the corresponding directory entry
 *            (must be the same size with the links array)
 *            (each directory entry should contain the full path, including the filename)
 * @param int $timeout
 *            the maximum time (in number of second) to wait
 */
function plagiarism_programming_curl_download($links, $directory = null, $timeout = 0) {
    $curlhandlearray = array();
    $multihandler = curl_multi_init();

    // Initialise.
    if (is_array($directory)) {
        foreach ($links as $key => $link) {
            $curlhandlearray[$key] = curl_init($link);
            curl_setopt($curlhandlearray[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlhandlearray[$key], CURLOPT_TIMEOUT, $timeout);
            curl_multi_add_handle($multihandler, $curlhandlearray[$key]);
        }
    } else {
        foreach ($links as $link) {
            $filename = substr($link, strrpos($link, '/'));
            $curlhandlearray[$filename] = curl_init($link);
            curl_setopt($curlhandlearray[$filename], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($multihandler, $curlhandlearray[$filename]);
        }
    }

    // Download.
    $stillrunning = 0;
    do {
        curl_multi_exec($multihandler, $stillrunning);
    } while ($stillrunning > 0);

    if (!$directory) {
        return;
    }

    if (is_array($directory)) {
        foreach ($curlhandlearray as $key => $handle) {
            $result = curl_multi_getcontent($handle);
            $file = fopen($directory[$key], 'w');
            fwrite($file, $result);
            fclose($file);
        }
    } else { // Directory is a string.
        // Add a slash at the end if it doesn't exist.
        $directory = (substr($directory, -1) != '/') ? $directory . '/' : $directory;
        foreach ($curlhandlearray as $filename => $handle) {
            $result = curl_multi_getcontent($handle);
            $file = fopen($directory . $filename, 'w');
            fwrite($file, $result);
            fclose($file);
        }
    }
}

/**
 * Extract a rar file.
 * In addition, this function also clear students' name and id if there
 * are in the comments and the name of the file
 *
 * @param string $zip_file
 *            full path of the zip file
 * @param array $extensions
 *            extension of files that should be extracted (for example, just .java file should be extracted)
 * @param string $location
 *            directory of
 * @param stdClass $user:
 *            record object of the student who submitted the file
 * @return true if the file has appropriate extensions, otherwise false (i.e. empty code)
 */
function plagiarism_programming_extract_rar($rarfile, $extensions, $location, $student = null, $textfileonly = false) {
    mtrace("Extracting rar file...\n");
    if (!class_exists('RarArchive')) {
        mtrace("Rar library doesn't exist");
        return false;
    }
    $rararchive = RarArchive::open($rarfile);
    if (!$rararchive) {
        return false;
    }

    $hasvalidfile = false;

    // Finfo object to check for plain text file.
    $finfo = new finfo(FILEINFO_MIME);

    $entries = $rararchive->getEntries();
    foreach ($entries as $entry) {
        $entryname = $entry->getName();
        // If an entry name contain the id, remove it.
        if (isset($student->idnumber)) {
            $entryname = str_replace($student->idnumber, '_id_', $entryname);
        }
        // If it's a file (skip directory entry since directories along the path will be created $handlewhen writing to the files.
        if (!$entry->isDirectory() && plagiarism_programming_check_extension($entryname, $extensions)) {
            $stream = $entry->getStream();
            if ($stream) {
                $buf = fread($stream, $entry->getUnpackedSize());
                if (!$textfileonly || strpos($finfo->buffer($buf), 'text') !== false) { // Check if it is not a binary file.
                    $filepath = $location . $entryname;
                    $fp = plagiarism_programming_create_file($filepath);

                    plagiarism_programming_annonymise_students($buf, $student);
                    fwrite($fp, $buf);
                    fclose($fp);
                    $hasvalidfile = true;
                }
                fclose($stream);
            }
        }
    }

    return $hasvalidfile;
}

/**
 * Extract a zip file.
 * In addition, this function also clear students' name and id if there
 * are in the comments and the name of the file
 *
 * @param string $zipfile
 *            full path of the zip file
 * @param array $extensions
 *            extension of files that should be extracted (for example, just .java file should be extracted)
 * @param string $location
 *            directory of
 * @param stdClass $user:
 *            record object of the student who submitted the file
 * @return true if the file has appropriate extensions, otherwise false (i.e. empty code)
 */
function plagiarism_programming_extract_zip($zipfile, $extensions, $location, $user = null, $textfileonly = true) {
    $ziphandle = zip_open($zipfile);
    $hasvalidfile = false;
    if (!$ziphandle) {
        return false;
    }

    // Finfo object to check for plain text file.
    $finfo = new finfo(FILEINFO_MIME);

    while ($zipentry = zip_read($ziphandle)) {
        $entryname = zip_entry_name($zipentry);

        // Ff an entry name contain the student id, hide it.
        if ($user) {
            $entryname = str_replace($user->idnumber, '_id_', $entryname);
        }
        // Ff it's a file (skip directory entry since directories along the path will be created when writing to the files.
        if (substr($entryname, -1) != '/' && plagiarism_programming_check_extension($entryname, $extensions)) {
            if (zip_entry_open($ziphandle, $zipentry, 'r')) {
                $buf = zip_entry_read($zipentry, zip_entry_filesize($zipentry));
                if (!$textfileonly || strpos($finfo->buffer($buf), 'text') !== false) { // Check text file.
                    $filepath = $location . $entryname;
                    $fp = plagiarism_programming_create_file($filepath);
                    if ($user) {
                        plagiarism_programming_annonymise_students($buf, $user);
                    }
                    fwrite($fp, $buf);
                    fclose($fp);
                    $hasvalidfile = true;
                }
                zip_entry_close($zipentry);
            }
        }
    }

    return $hasvalidfile;
}

/**
 * The file is a compressed file or not.
 * Since the plugin supports only zip and
 * rar files, every other compression type will be considered not compressed.
 * Just a simple extension check is performed (zip or rar)
 */
function plagiarism_programming_is_compressed_file($filename) {
    $ext = substr($filename, -4, 4);
    return ($ext == '.zip') || ($ext == '.rar');
}

/**
 * Count the number of line and the number of characters at the final line in the provided string
 *
 * @param: the string to countstring
 */
function plagiarism_programming_count_line(&$text) {
    $linecount = substr_count($text, "\n");
    $charnum = strlen($text) - strrpos($text, "\n");
    return array(
        $linecount,
        $charnum
    );
}

/**
 * This class is used to inform the progress of something (scanning, downloading).
 * It is passed to the stub and
 * used by the stub to inform the progress by calling update_progress. It decouples the generic stubs, which contain
 * client code with the database.
 */
class progress_handler{
    private $toolname;
    private $toolparam;

    /**
     * Construct the object
     *
     * @param $toolname {string}
     *            The tool need progress update (either JPlag or MOSS)
     * @param $tool_param: the
     *            record object of MOSS param status
     */
    public function __construct($toolname, $toolparam) {
        $this->toolname = $toolname;
        $this->toolparam = $toolparam;
    }

    /**
     * Update the progress of the tool indicated in the constructor
     *
     * @param $stage {string} The stage the scanning is in (upload, download, scanning...)
     * @param $progress {number} The percentage finished (between 0 and 100)
     */
    public function update_progress($stage, $progress) {
        global $DB;
        $record = $DB->get_record('plagiarism_programming_' . $this->toolname, array(
            'id' => $this->toolparam->id
        ));
        $record->status = $stage;
        $record->progress = intval($progress);
        $DB->update_record('plagiarism_programming_' . $this->toolname, $record);
        $this->toolparam->progress = intval($progress);
        $this->toolparam->status = $stage;
    }
}