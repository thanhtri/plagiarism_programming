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
 * Define entry function for MOSS engine
 * Organise the result into the structure required by MOSS,
 * upload the assignment and interpret the result.
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once dirname(__FILE__).'/plagiarism_tool.php';
include_once dirname(__FILE__).'/moss/moss_stub.php';

class moss_tool extends plagiarism_tool {

    private $moss_stub;
    
    public function __construct() {
        $this->moss_stub = new moss_stub(658775742);
    }
    
    public function submit_assignment($inputdir,$assignment,$moss_param) {
        // MOSS require each students' submission is in a flat directory.
        // Therefore, the filename should be tweaked a bit. e.g. /assignment/main.java => assignment~main.java

        $all_dir = scandir($inputdir);
        $files = array();
        foreach ($all_dir as $dir) {
            if ($dir=='.' || $dir=='..')
                continue;
            $fullpath = $inputdir.$dir.'/';
            $files = array_merge($files,$this->scan_directory($fullpath,$dir,''));
        }

        // send to server
        $language = $this->get_language_code($assignment->language);
        $progress_handler = new progress_handler('moss', $moss_param);
        $result_link = $this->moss_stub->scan_assignment($files,$language,$progress_handler);
        if (substr($result_link,0,4)=='http') {
            $moss_param->resultlink = $result_link;
            $moss_param->status = 'done';
            $moss_param->progress = 100;
        } else {
            $moss_param->message = $result_link;
            $moss_param->status = 'error';
            $moss_param->progress = 100;
        }

        return $moss_param;
    }

    public function check_status($assignment_param,$moss_param) {
        // moss doesn't allow to query the scanning progress
        return $moss_param;
    }
    
    public function display_link($setting) {
        global $CFG;
        $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$setting->courseid&tool=moss";
        return "<a target='_blank' href='$link'>MOSS report</a>";
    }

    private function scan_directory($full_basedir,$short_basedir,$mergedir) {
        // construct an array like path=>name
        $results = array();
        $directory = $full_basedir.$mergedir;
        $all_files = scandir($directory);
        foreach ($all_files as $file) {
            if ($file=='.' || $file=='..')
                continue;
            $path = $directory.$file;
            if (is_dir($path)) {
                $new_mergedir = $mergedir.$file.'/';
                $files = $this->scan_directory($full_basedir,$short_basedir,$new_mergedir);
                $results = array_merge($results, $files);
            } else {
                $results[$path] = $short_basedir.'/'.str_replace(array('/','\\'),'~',$mergedir).$file;
            }
        }
        return $results;
    }

    private function get_language_code($language) {
        $code = '';
        switch ($language) {
            case 'java': $code='java'; break;
            case 'c': $code='cc'; break;
            case 'c#': $code = 'csharp'; break;
            default: $code = $language;
        }
        return $code;
    }

    // the download page to page of the report is very slow.
    // Therefore, it call another process to function
    public function download_result($assignment,$moss_info) {
        // Create the directory
        $report_path = $this->get_report_path($assignment->courseid);
        if (is_dir($report_path)) {
            rrmdir($report_path);
        }
        mkdir($report_path);
        

        $handler = new progress_handler($this->get_name(), $moss_info);
        $report_url = trim($moss_info->resultlink);
        $this->moss_stub->download_result($report_url, $report_path, $handler);

        return $moss_info;
    }
    
    public function parse_result($assignment,$moss_info) {
        global $DB;
        
        $report_path = $this->get_report_path($assignment->courseid);
        
        $result_file = $report_path.'/index.html';
        assert(is_file($result_file));
        
        // delete the old records (in case this tool is run several times on one assignment)
        $DB->delete_records('programming_result',array('cmid'=>$assignment->courseid,'detector'=>'moss'));
        
        $content = file_get_contents($result_file);
        // this pattern extract the link
        $pattern = '/<A HREF=\"(match[0-9]*\.html)\">([0-9]*)\/\s\(([0-9]*)%\)<\/A>/';
        preg_match_all($pattern, $content, $matches);
        $filenames = $matches[1];
        $studentids = $matches[2];
        $similarity = $matches[3];
        $num = count($filenames);

        $record = new stdClass();
        $record->detector = $this->get_name();
        $record->cmid = $assignment->courseid;
        for ($i=0; $i<$num; $i+=2) {
            $record->student1_id = $studentids[$i];
            $record->student2_id = $studentids[$i+1];
            $record->similarity1 = $similarity[$i];
            $record->similarity2 = $similarity[$i+1];
            $record->comparison = $filenames[$i];
            $DB->insert_record('programming_result',$record);
        }
        $moss_info->status = 'finished';
        $moss_info->progress = 100;
        
        return $moss_info;
    }

    public function get_report_path($cmid=null) {
        global $CFG;
        if (!$cmid) {
            return $CFG->dataroot.'/plagiarism_report/';
        } else {
            return $CFG->dataroot."/plagiarism_report/moss$cmid";
        }
    }

    public function get_name() {
        return 'moss';
    }
}
