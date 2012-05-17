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

/** Return an array containing the possible extension
 *  of source code file of the provided language
 */
function get_file_extension_by_language($language) {
    $extensions = array();
    if ($language=='java') {
        $extensions = array('java','JAVA','Java');
    } elseif ($language=='c') {
        $extensions = array('h','c','cpp','C','CPP','H');
    } elseif ($language=='c#') {
        $extensions = array('cs','CS','Cs');
    }
    return $extensions;
}

function check_extension($filename,$extensions) {
	$dot_index = strrpos($filename, '.');
	
	if ($dot_index === FALSE)
		return 0;
	
    $ext = substr($filename, $dot_index + 1);
    return in_array($ext, $extensions);
}

// taken from php.net: delete a non empty directory
function rrmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir.'/'.$object) == 'dir') rrmdir($dir."/".$object); else unlink($dir.'/'.$object);
            }
        }
        reset($objects);
        rmdir($dir);
    }
}

// download in parallel by curl
function curl_download($links,$directory=null) {
    $curl_handle_array = array();
    $multi_handler = curl_multi_init();
    
    // initialise
    foreach ($links as $link) {
        $filename = substr($link,strrpos($link, '/'));
        $curl_handle_array[$filename] = curl_init($link);
        curl_setopt($curl_handle_array[$filename], CURLOPT_RETURNTRANSFER, true);
        curl_multi_add_handle($multi_handler, $curl_handle_array[$filename]);
    }
    
    // download
    $still_running = 0;
    do {
        curl_multi_exec($multi_handler, $still_running);
    } while ($still_running>0);

    if (!$directory) {
        return;
    }
    
    // save the result
    if (substr($directory, -1)!='/') {
        $directory .= '/';
    }

    foreach ($curl_handle_array as $filename=>$handle) {
        $result = curl_multi_getcontent($handle);
        $file = fopen($directory.$filename, 'w');
        fwrite($file, $result);
        fclose($file);
    }
}

function count_line(&$text) {
    $line_count = substr_count($text, "\n");
    $char_num = strlen($text)-strrpos($text, "\n");
    return array($line_count,$char_num);
}

class progress_handler {
    private $tool_name;
    private $tool_param;

    public function __construct($tool_name,$tool_param) {
        $this->tool_name = $tool_name;
        $this->tool_param = $tool_param;
    }
    public function update_progress($stage,$progress) {
        global $DB;
        $record = $DB->get_record('programming_'.$this->tool_name, array('id'=>  $this->tool_param->id));
        $record->status = $stage;
        $record->progress = intval($progress);
        $DB->update_record('programming_'.$this->tool_name, $record);
        $this->tool_param->progress = intval($progress);
        $this->tool_param->status = $stage;
    }
}