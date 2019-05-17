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

    private $mossstub;

    private static $supportedlanguages = array(
        'java' => 'java',
        'c' => 'cc',
        'c#' => 'csharp',
        'scheme' => 'scheme',
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

    private function init_stub($mossparam = null) {
        global $CFG;

        if (!isset($this->mossstub)) {
            $userid = get_config('plagiarism_programming', 'moss_user_id');
            $proxyhost = isset($CFG->proxyhost) ? $CFG->proxyhost : '';
            $proxyport = isset($CFG->proxyport) ? $CFG->proxyport : '';
            $proxyuser = isset($CFG->proxyuser) ? $CFG->proxyuser : '';
            $proxypass = isset($CFG->proxypassword) ? $CFG->proxypassword : '';
            if (!empty($userid)) {
                $this->mossstub = new moss_stub($userid, $proxyhost, $proxyport,
                    $proxyuser, $proxypass);
            } else if ($mossparam) {
                $mossparam->status = 'error';
                $mossparam->message = get_string('credential_not_provided', 'plagiarism_programming');
            }
        }
        return $this->mossstub;
    }

    public function submit_assignment($inputdir, $assignment, $mossparam) {

        if (!$this->init_stub($mossparam)) { // Credentials not provided.
            return $mossparam;
        }

        // Check supported language.
        if (!isset(self::$supportedlanguages[$assignment->language])) {
            $mossparam->status = 'error';
            $mossparam->message = 'Language not supported by MOSS';
            return $mossparam;
        }

        // MOSS require each students' submission is in a flat directory.
        // Therefore, the filename should be tweaked a bit. e.g. /assignment/main.java => assignment~main.java.

        $alldir = scandir($inputdir);
        $files = array();
        foreach ($alldir as $dir) {
            if ($dir == '.' || $dir == '..') {
                continue;
            }
            $fullpath = $inputdir.$dir.'/';
            $files = array_merge($files, $this->scan_directory($fullpath, $dir, ''));
        }

        // Send to server.
        $language = $this->get_language_code($assignment->language);
        $progresshandler = new progress_handler('moss', $mossparam);
        $result = $this->mossstub->scan_assignment($files, $language, $progresshandler);
        if ($result['status'] == 'OK') {
            $mossparam->resultlink = $result['link'];
            $mossparam->status = 'done';
            $mossparam->progress = 100;
        } else {
            $mossparam->message = $result['error'];
            $mossparam->status = 'error';
            $mossparam->progress = 100;
        }

        return $mossparam;
    }

    /**
     * Check scanning status.
     * Since MOSS doesn't have API to probe the scanning status on the server,
     * the status in the db is returned.
     */
    public function check_status($assignmentparam, $mossparam) {
        // Moss does not allow to query the scanning progress.
        return $mossparam;
    }

    public function display_link($setting) {
        global $CFG;
        $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$setting->cmid&tool=moss";
        return "<a target='_blank' href='$link'>MOSS report</a>";
    }

    private function scan_directory($fullbasedir, $shortbasedir, $mergedir) {
        // Construct an array like path=>name.
        $results = array();
        $directory = $fullbasedir.$mergedir;
        $allfiles = scandir($directory);
        foreach ($allfiles as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            $path = $directory.$file;
            if (is_dir($path)) {
                $newmergedir = $mergedir.$file.'/';
                $files = $this->scan_directory($fullbasedir, $shortbasedir, $newmergedir);
                $results = array_merge($results, $files);
            } else {
                $results[$path] = $shortbasedir.'/'.str_replace(array('/', '\\'), '~', $mergedir).$file;
            }
        }
        return $results;
    }

    private function get_language_code($language) {
        return self::$supportedlanguages[$language];
    }

    // The download page to page of the report is very slow.
    // Therefore, it call another process to function.
    public function download_result($assignment, $mossinfo) {
        if (!$this->init_stub($mossinfo)) { // Credentials not provided.
            return $mossinfo;
        }
        // Create the directory.
        $report = plagiarism_programming_create_new_report($assignment->cmid, 'moss');

        // Create report directory.
        $reportpath = self::get_report_path();
        if (!is_dir($reportpath)) {
            mkdir($reportpath);
        }
        $reportpath = self::get_report_path($report);
        if (is_dir($reportpath)) {
            plagiarism_programming_rrmdir($reportpath);
        }
        mkdir($reportpath);

        $handler = new progress_handler($this->get_name(), $mossinfo);
        $reporturl = trim($mossinfo->resultlink);
        $this->mossstub->download_result($reporturl, $reportpath, $handler);

        return $mossinfo;
    }

    public function parse_result($assignment, $mossinfo) {

        $parser = new moss_parser($assignment->cmid);
        $parser->parse();

        $mossinfo->status = 'finished';
        $mossinfo->progress = 100;

        return $mossinfo;
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
            return "{$CFG->tempdir}/plagiarism_report/moss{$report->cmid}_v$report->version";
        }
    }

    public static function get_supported_laguage() {
        return self::$supportedlanguages;
    }

    public function get_name() {
        return 'moss';
    }
}
