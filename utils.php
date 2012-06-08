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
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

/**
 * Return an array containing the possible extensions
 * of source code file of the provided language
 * @param $language: either java, c, csharp
 * @return array of extensions of the language
 */
function get_file_extension_by_language($language) {
    $extensions = array();
    switch ($language) {
        case 'java':
            $extensions = array('java', 'JAVA', 'Java');
            break;
        case 'c':
            $extensions = array('h', 'c', 'cpp', 'C', 'CPP', 'H');
            break;
        case 'c#':
            $extensions = array('cs', 'CS', 'Cs');
            break;
        case 'scheme':
            $extensions = array('scm', 'SCM');
            break;
        case 'plsql':
            $extensions = array('sql', 'pls', 'pks');
            break;
        case 'pascal':
            $extensions = array('pas', 'tp', 'bp', 'p');
            break;
        case 'perl':
            $extensions = array('pl', 'PL');
            break;
        case 'python':
            $extensions = array('py', 'PY');
            break;
        case 'vb':
            $extensions = array('vb', 'VB', 'Vb');
            break;
        case 'javascript':
            $extensions = array('js', 'JS', 'Js');
            break;
    }
    return $extensions;
}

/**
 * Check if the file has the appropriate extension
 * @param $filename: name of the file
 * @param $extension: an array of possible extensions
 * @return true if the file has the extension in the array
 */
function check_extension($filename, $extensions) {
    $dot_index = strrpos($filename, '.');

    if ($dot_index === false) {
        return 0;
    }

    $ext = substr($filename, $dot_index + 1);
    return in_array($ext, $extensions);
}

/**
 * Delete a directory (taken from php.net) with all of its sub-dirs and files
 * @param string $dir: the directory to be deleted
 */
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir.'/'.$object) == 'dir') {
                    rrmdir($dir."/".$object);
                } else {
                    unlink($dir.'/'.$object);
                }
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

/**
 * Get a list of links in parallel. curl is used to call the parallel
 * @param array $links: list of links
 * @param mixed $directory: if null, the contents get by the links will not be stored, if a string, the content
 *  will be stored in that directory, if an array, each link will be saved in the corresponding directory entry
 * (must be the same size with the links array)
 * (each directory entry should contain the full path, including the filename)
 * @param int $timeout the maximum time (in number of second) to wait
 */
function curl_download($links, $directory=null, $timeout=0) {
    $curl_handle_array = array();
    $multi_handler = curl_multi_init();

    // initialise
    if (is_array($directory)) {
        foreach ($links as $key => $link) {
            $curl_handle_array[$key] = curl_init($link);
            curl_setopt($curl_handle_array[$key], CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl_handle_array[$key], CURLOPT_TIMEOUT, $timeout);
            curl_multi_add_handle($multi_handler, $curl_handle_array[$key]);
        }
    } else {
        foreach ($links as $link) {
            $filename = substr($link, strrpos($link, '/'));
            $curl_handle_array[$filename] = curl_init($link);
            curl_setopt($curl_handle_array[$filename], CURLOPT_RETURNTRANSFER, true);
            curl_multi_add_handle($multi_handler, $curl_handle_array[$filename]);
        }
    }

    // download
    $still_running = 0;
    do {
        curl_multi_exec($multi_handler, $still_running);
    } while ($still_running>0);

    if (!$directory) {
        return;
    }

    if (is_array($directory)) {
        foreach ($curl_handle_array as $key => $handle) {
            $result = curl_multi_getcontent($handle);
            $file = fopen($directory[$key], 'w');
            fwrite($file, $result);
            fclose($file);
        }
    } else { // directory is a string
        // add a slash at the end if it doesn't exist
        $directory = (substr($directory, -1)!='/')?$directory.'/':$directory;
        foreach ($curl_handle_array as $filename => $handle) {
            $result = curl_multi_getcontent($handle);
            $file = fopen($directory.$filename, 'w');
            fwrite($file, $result);
            fclose($file);
        }
    }
}

/**
 * Count the number of line and the number of characters at the final line in the provided string
 * @param: the string to count
 */
function count_line(&$text) {
    $line_count = substr_count($text, "\n");
    $char_num = strlen($text)-strrpos($text, "\n");
    return array($line_count, $char_num);
}

/**
 * This class is used to inform the progress of something (scanning, downloading). It is passed to the stub and
 * used by the stub to inform the progress by calling update_progress. It decouples the generic stubs, which contain
 * client code with the database.
 */
class progress_handler {
    private $tool_name;
    private $tool_param;

    /**
     * Construct the object
     * @param $tool_name: the tool need progress update (either JPlag or MOSS)
     * @param $tool_param: the record object of MOSS param status
     */
    public function __construct($tool_name, $tool_param) {
        $this->tool_name = $tool_name;
        $this->tool_param = $tool_param;
    }

    /**
     * Update the progress of the tool indicated in the constructor
     * @param $stage: the stage the scanning is in (upload, download, scanning...)
     * @param $progress: the percentage finished (between 0 and 100)
     */
    public function update_progress($stage, $progress) {
        global $DB;
        $record = $DB->get_record('programming_'.$this->tool_name, array('id'=>$this->tool_param->id));
        $record->status = $stage;
        $record->progress = intval($progress);
        $DB->update_record('programming_'.$this->tool_name, $record);
        $this->tool_param->progress = intval($progress);
        $this->tool_param->status = $stage;
    }
}