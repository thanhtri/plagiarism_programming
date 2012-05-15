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
 * Define the entry calls for JPlag
 * Transform data into the structure required by JPlag,
 * communicate with JPlag server and interpret the result
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once dirname(__FILE__).'/plagiarism_tool.php';
include_once dirname(__FILE__).'/jplag/jplag_stub.php';
include_once dirname(__FILE__).'/jplag/jplag_parser.php';

class jplag_tool extends plagiarism_tool {

    private $jplag_stub=null;

    private function stub_init($jplag_info) {
        // the stub is initiated lazily at most one time (per request) when it is required
        if ($this->jplag_stub==null) {
            // get the username and password
            $settings = (array) get_config('plagiarism_programming');
            if (isset($settings['jplag_user']) && isset($settings['jplag_pass'])) {
                $this->jplag_stub = new jplag_stub($settings['jplag_user'],$settings['jplag_pass']);
            } else {
                $jplag_info->status = 'error';
                $jplag_info->message = 'Credential not provided!';
                return FALSE;
            }
        }
        return $this->jplag_stub;
    }
    
    public function submit_assignment($inputdir, $assignment, $scan_info) {
        if (!$this->stub_init($scan_info)) {
            return $scan_info;
        }
        
        $zip_full_path = PLAGIARISM_TEMP_DIR.'zip_jplag_'.$assignment->id.'_'.time().'.zip'; //prevent collision
        $submit_zip_file = new ZipArchive();
        $submit_zip_file->open($zip_full_path,ZipArchive::CREATE);
        $this->jplag_zip_directory(strlen(dirname($inputdir))+1, $inputdir, $submit_zip_file);
        $submit_zip_file->close();
        return $this->jplag_send_to_server($zip_full_path,$assignment, $scan_info);
    }

    private function jplag_send_to_server($zip_file_path,$assignment_param,$scan_info) {
        if (!$this->stub_init($scan_info)) {
            return $scan_info;
        }

        $option = new jplag_option();
        $option->set_language($assignment_param->language);
        $option->title = 'Test';

        // initialise progress handler
        $handler = new progress_handler('jplag',$scan_info);

        try {
            $submissionID = $this->jplag_stub->send_file($zip_file_path, $option, $handler);
            unlink($zip_file_path);
            // upload finished, pass to the scanning phase
            $scan_info->status = 'scanning';
            $scan_info->submissionid = $submissionID;
        } catch (SoapFault $ex) {
            $scan_info->status = 'error';
            $scan_info->message= $ex->detail->JPlagException->description.' '.$ex->detail->JPlagException->repair;
        }
        return $scan_info;
    }

    public function check_status($assignment_param, $jplag_param) {
        if (!$this->stub_init($jplag_param)) {
            return $scan_info;
        }

        // TODO: handle network error
        $submissionid = $jplag_param->submissionid;
        $status = $this->jplag_stub->check_status($submissionid);

        $state = $status->state;
        if ($state >= SUBMISSION_STATUS_ERROR) {
            $jplag_param->status = 'error';
            $jplag_param->directory = $status->report;
        } elseif ($state == SUBMISSION_STATUS_DONE) {
            $jplag_param->status = 'done';
            $jplag_param->progress = 100;
        } else { //not done yet
            $jplag_param->status = 'scanning';
            $jplag_param->progress = $status->progress;
        }

        return $jplag_param;
    }

    /** Download the result from jplag server.
     *  Note that the scanning status must be "done"
     */
    public function download_result($assignment_param,$jplag_param) {
        if (!$this->stub_init($jplag_param)) {
            return $scan_info;
        }

        // create a directory
        $report_path = $this->get_report_path();
        if (!is_dir($report_path)) {
            mkdir($report_path);
        }
        $assignment_report_path = $this->get_report_path($assignment_param->courseid);
        if (is_dir($assignment_report_path)) {
            rrmdir($assignment_report_path);
        }
        mkdir($assignment_report_path);
        $result_file = $assignment_report_path.'/download.zip';
        $fileHandle = fopen($result_file, 'w');
        echo "Downloading result...\n";

        // initialise the handler
        $progress_handler = new progress_handler('jplag', $jplag_param);

        try {
            $this->jplag_stub->download_result($jplag_param->submissionid, $fileHandle,$progress_handler);
            fclose($fileHandle);
            $this->extract_zip($result_file);
            unlink($result_file);  // delete the file after extracting
            echo "Finished downloading. Everything OK\n";
            $jplag_param->status='done_downloading';
            $jplag_param->directory=$assignment_report_path;
            $jplag_param->message = 'success';
        } catch (SoapFault $fault) {
            echo 'Error occurs while downloading: '.$fault->detail->JPlagException->description."\n";
            $jplag_param->status='error';
            $jplag_param->message=$fault->detail->JPlagException->description;
            fclose($fileHandle);
        }

        return $jplag_param;
    }

    public function display_link($param) {
        global $CFG;
        $report_path = $CFG->wwwroot.'/plagiarism/programming/view.php?cmid='.$param->courseid;
        return "<a target='_blank' href='$report_path'>JPlag report</a>";
    }

    public function get_report_path($cmid=null) {
        global $CFG;
        if (!$cmid) {
            return $CFG->dataroot.'/plagiarism_report/';
        } else {
            return $CFG->dataroot."/plagiarism_report/report$cmid";
        }
    }

    private function jplag_zip_directory($baselength,$dir,$submit_zip_file) {
        if (is_dir($dir)) {
            $archive_dir_name = substr($dir, $baselength,-1);
            $submit_zip_file->addEmptyDir($archive_dir_name);
            $all_files = scandir($dir);
            foreach ($all_files as $file) {
                if ($file=='.' || $file=='..')
                    continue;
                $path = $dir.$file;
                if (is_dir($path)) {
                    $this->jplag_zip_directory($baselength,$path.'/', $submit_zip_file);
                } else {
                    $archive_file_name = substr($path, $baselength);
                    $file_content = file_get_contents($path);
                    $submit_zip_file->addFromString($archive_file_name,$file_content);
                }
            }
        }
    }

    private function extract_zip($zip_file) {
        assert(is_file($zip_file));
        $handle = zip_open($zip_file);
        $base_dir = dirname($zip_file);
        if ($handle) {
            while ($zip_entry = zip_read($handle)) {
                $entry_name = zip_entry_name($zip_entry);
                if (substr($entry_name, -1)!='/') {  // a file
                    $fp = create_file($base_dir.'/'.$entry_name);
                    if (zip_entry_open($handle, $zip_entry, 'r')) {
                        $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                        fwrite($fp, $buf);
                        zip_entry_close($zip_entry);
                        fclose($fp);
                    }
                }
            }
        }
    }

    public function parse_result($assignment,$jplag_info) {
        $parser = new jplag_parser($assignment->courseid);
        $parser->parse();
        $jplag_info->status = 'finished';
        return $jplag_info;
    }

    public function get_name() {
        return 'jplag';
    }
}
