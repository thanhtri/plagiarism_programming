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
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once(__DIR__.'/plagiarism_tool.php');
require_once(__DIR__.'/moss/moss_stub.php');
require_once(__DIR__.'/moss/moss_parser.php');
require_once(__DIR__.'/reportlib.php');

class moss_tool implements plagiarism_tool {

    private $moss_stub;

    private static $supported_languages = array(
        'java' => 'java',
        'c' => 'cc',
        'c#' => 'csharp',
        'scheme'=>'scheme',
        'text' => 'Plain text',
        'python' => 'python',
        'vb' => 'Visual Basic',
        'js' => 'Javascript',
        'pascal' => 'pascal',
        'lisp' => 'lisp',
        'perl' => 'perl',
        'prolog' => 'prolog',
        'plsql' => 'plsql',
        'mathlab' => 'matlab',
        'fortran' => 'fortran',
        'mips' => 'mips',
        'a8086' => 'a8086',
    );

    private function init_stub($moss_param=null) {
        global $CFG;

        if (!isset($this->moss_stub)) {
            $userid = get_config('plagiarism_programming', 'moss_user_id');
            $proxyhost = isset($CFG->proxyhost)?$CFG->proxyhost:'';
            $proxyport = isset($CFG->proxyport)?$CFG->proxyport:'';
            $proxyuser = isset($CFG->proxyuser)?$CFG->proxyuser:'';
            $proxypass = isset($CFG->proxypassword)?$CFG->proxypassword:'';
            if (!empty($userid)) {
                $this->moss_stub = new moss_stub($userid, $proxyhost, $proxyport,
                    $proxyuser, $proxypass);
            } else if ($moss_param) {
                $moss_param->status = 'error';
                $moss_param->message = get_string('credential_not_provided', 'plagiarism_programming');
            }
        }
        return $this->moss_stub;
    }

    public function submit_assignment($inputdir, $assignment, $moss_param) {

        if (!$this->init_stub($moss_param)) { // credential not provided
            return $moss_param;
        }

        // check supported language
        if (!isset(self::$supported_languages[$assignment->language])) {
            $moss_param->status = 'error';
            $moss_param->message= 'Language not supported by MOSS';
            return $moss_param;
        }

        // MOSS require each students' submission is in a flat directory.
        // Therefore, the filename should be tweaked a bit. e.g. /assignment/main.java => assignment~main.java

        $all_dir = scandir($inputdir);
        $files = array();
        foreach ($all_dir as $dir) {
            if ($dir=='.' || $dir=='..') {
                continue;
            }
            $fullpath = $inputdir.$dir.'/';
            $files = array_merge($files, $this->scan_directory($fullpath, $dir, ''));
        }

        // send to server
        $language = $this->get_language_code($assignment->language);
        $progress_handler = new progress_handler('moss', $moss_param);
        $result = $this->moss_stub->scan_assignment($files, $language, $progress_handler);
        if ($result['status']=='OK') {
            $moss_param->resultlink = $result['link'];
            $moss_param->status = 'done';
            $moss_param->progress = 100;
        } else {
            $moss_param->message = $result['error'];
            $moss_param->status = 'error';
            $moss_param->progress = 100;
        }

        return $moss_param;
    }

    /**
     * Check scanning status.
     * Since MOSS doesn't have API to probe the scanning status on the server,
     * the status in the db is returned.
     */
    public function check_status($assignment_param, $moss_param) {
        // moss doesn't allow to query the scanning progress
        return $moss_param;
    }

    public function display_link($setting) {
        global $CFG;
        $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$setting->cmid&tool=moss";
        return "<a target='_blank' href='$link'>MOSS report</a>";
    }

    private function scan_directory($full_basedir, $short_basedir, $mergedir) {
        // construct an array like path=>name
        $results = array();
        $directory = $full_basedir.$mergedir;
        $all_files = scandir($directory);
        foreach ($all_files as $file) {
            if ($file=='.' || $file=='..') {
                continue;
            }
            $path = $directory.$file;
            if (is_dir($path)) {
                $new_mergedir = $mergedir.$file.'/';
                $files = $this->scan_directory($full_basedir, $short_basedir, $new_mergedir);
                $results = array_merge($results, $files);
            } else {
                $results[$path] = $short_basedir.'/'.str_replace(array('/', '\\'), '~', $mergedir).$file;
            }
        }
        return $results;
    }

    private function get_language_code($language) {
        return self::$supported_languages[$language];
    }

    // the download page to page of the report is very slow.
    // Therefore, it call another process to function
    public function download_result($assignment, $moss_info) {
        if (!$this->init_stub($moss_info)) { // credential not provided
            return $moss_info;
        }
        // Create the directory
        $report = create_next_report($assignment->cmid, 'moss');
        $report_path = self::get_report_path($report);
        if (is_dir($report_path)) {
            rrmdir($report_path);
        }
        mkdir($report_path);

        $handler = new progress_handler($this->get_name(), $moss_info);
        $report_url = trim($moss_info->resultlink);
        $this->moss_stub->download_result($report_url, $report_path, $handler);

        return $moss_info;
    }

    public function parse_result($assignment, $moss_info) {

        $parser = new moss_parser($assignment->cmid);
        $parser->parse();

        $moss_info->status = 'finished';
        $moss_info->progress = 100;

        return $moss_info;
    }

    /**
     * Return the path of the directory containing the report
     * @param number $cmid the course module id of the assignment. If null, it will return the root directory of all the report
     * @param number $version the version of report. If null, it will return the directory of the latest report of this assignment
     * (if cmid not null)
     */
    public static function get_report_path($report=null) {
        global $CFG;
        if (!$report) {
            return "{$CFG->tempdir}/plagiarism_report/";
        } else {
            return "{$CFG->tempdir}/plagiarism_report/moss{$report->cmid}"."_v{$report->version}";
        }
    }

    public static function get_supported_laguage() {
        return self::$supported_languages;
    }

    public function get_name() {
        return 'moss';
    }
}
