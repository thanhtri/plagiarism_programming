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
 *
 * Transform data into the structure required by JPlag,
 * communicate with JPlag server and interpret the result
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once(__DIR__ . '/plagiarism_tool.php');
require_once(__DIR__ . '/jplag/jplag_stub.php');
require_once(__DIR__ . '/jplag/jplag_parser.php');
require_once(__DIR__ . '/reportlib.php');

/**
 * Wrapper class.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jplag_tool implements plagiarism_tool{
    /**
     * @var $jplagstub
     */
    private $jplagstub = null;
    /**
     * @var $supportedlanguages
     */
    private static $supportedlanguages = array(
        'java' => 'java15',
        'c' => 'c/c++',
        'c#' => 'c#-1.2',
        'scheme' => 'scheme',
        'text' => 'text'
    );

    /**
     * Initialise the SOAP stub.
     *
     * @param Object $jplaginfo
     * @return Object $jplaginfo
     */
    private function stub_init($jplaginfo = null) {
        global $CFG;
        // The stub is initiated lazily at most one time (per request) when it is required.
        if ($this->jplagstub == null) {
            // Get the username and password.
            $settings = get_config('plagiarism_programming');
            $proxyhost = isset($CFG->proxyhost) ? $CFG->proxyhost : '';
            $proxyport = isset($CFG->proxyport) ? $CFG->proxyport : '';
            $proxyuser = isset($CFG->proxyuser) ? $CFG->proxyuser : '';
            $proxypass = isset($CFG->proxypassword) ? $CFG->proxypassword : '';
            if (!empty($settings->jplag_user) && !empty($settings->jplag_pass)) {
                $this->jplagstub = new jplag_stub($settings->jplag_user,
                    $settings->jplag_pass, $proxyhost, $proxyport, $proxyuser, $proxypass);
            } else if ($jplaginfo != null) {
                $jplaginfo->status = 'error';
                $jplaginfo->message = get_string('credential_not_provided', 'plagiarism_programming');
                return false;
            }
        }
        return $this->jplagstub;
    }

    /**
     * Submit assignment to the JPlag server.
     * It will compress the codes in zip format before sending it
     *
     * @param string $inputdir
     *            the directory of all submission of the assignment, in which each student's submission is in a
     *            subdirectory named by their student id and the code associated with it
     * @param stdClass $assignment
     *            the record object of assignment config
     * @param stdClass $scaninfo
     *            the record object of the status of jplag
     * @return Object $scaninfo The same updated record object of jplag status
     */
    public function submit_assignment($inputdir, $assignment, $scaninfo) {
        if (!$this->stub_init($scaninfo)) {
            return $scaninfo;
        }

        // Check supported assignment.
        if (!isset(self::$supportedlanguages[$assignment->language])) {
            $scaninfo->status = 'error';
            $scaninfo->message = 'Language not supported by JPlag';
            return $scaninfo;
        }

        $zipfullpath = PLAGIARISM_TEMP_DIR . 'zip_jplag_' . $assignment->id . '_' . time() . '.zip'; // Prevent collision.
        $submitzipfile = new ZipArchive();
        $submitzipfile->open($zipfullpath, ZipArchive::CREATE);
        $this->jplag_zip_directory(strlen(dirname($inputdir)) + 1, $inputdir, $submitzipfile);
        $submitzipfile->close();
        return $this->jplag_send_to_server($zipfullpath, $assignment, $scaninfo);
    }

    /**
     * Send the zip file to JPlag server by SOAP protocol
     *
     * @param String $zipfilepath the path of zip file
     * @param stdClass $assignmentparam the record object of assignment config
     * @param stdClass $scaninfo the record object of the status of jplag (in plagiarism_programming_jplag table)
     * @return Object the same updated record object of jplag status
     */
    private function jplag_send_to_server($zipfilepath, $assignmentparam, $scaninfo) {
        if (!$this->stub_init($scaninfo)) {
            return $scaninfo;
        }

        $option = new jplag_option();
        $option->set_language($assignmentparam->language);
        $option->title = 'Test'; // This param doesn't matter.

        // Initialise progress handler.
        $handler = new progress_handler('jplag', $scaninfo);

        try {
            $submissionid = $this->jplagstub->send_file($zipfilepath, $option, $handler);
            unlink($zipfilepath);
            // Upload finished, pass to the scanning phase.
            $scaninfo->status = 'scanning';
            $scaninfo->submissionid = $submissionid;
        } catch (SoapFault $ex) {
            $error = jplag_stub::interpret_soap_fault($ex);
            $scaninfo->status = 'error';
            $scaninfo->message = $error['message'];
        }
        return $scaninfo;
    }

    /**
     * Checking the status of the scanning.
     * If the current status is scanning,
     * it will contact JPlag server to see if the scanning has been finished
     * Otherwise, it just return the status in the database
     *
     * @param Object $assignmentparam the assignment record object (plagiarism_programming table)
     * @param Object $jplagparam the jplag record object (plagiarism_programming_jplag table)
     * @return Object $jplag_param Updated
     */
    public function check_status($assignmentparam, $jplagparam) {
        if (!$this->stub_init($jplagparam)) {
            return $jplagparam;
        }

        // TODO: Handle network error.
        $submissionid = $jplagparam->submissionid;
        $status = $this->jplagstub->check_status($submissionid);

        $state = $status->state;
        if ($state >= SUBMISSION_STATUS_ERROR) {
            $jplagparam->status = 'error';
            $jplagparam->message = jplag_stub::translate_scanning_status($status);
            $jplagparam->error_detail = $status->report;

            $this->cancel_submission($jplagparam);
        } else if ($state == SUBMISSION_STATUS_DONE) {
            $jplagparam->status = 'done';
            $jplagparam->progress = 100;
        } else { // Not done yet.
            $jplagparam->status = 'scanning';
            $jplagparam->progress = $status->progress;
        }

        return $jplagparam;
    }

    /**
     * Download the result from jplag server.
     * Note that the scanning status must be "done"
     * Display the link to the report. This function returns html <a> tag of the link
     *
     * @param Object $assignmentparam parameter of the assignment
     * @param Object $jplagparam
     * @return Object $jplag_param Updated
     */
    public function download_result($assignmentparam, $jplagparam) {
        if (!$this->stub_init($jplagparam)) {
            return $jplagparam;
        }

        // Create a directory.
        $reportpath = self::get_report_path();
        if (!is_dir($reportpath)) {
            mkdir($reportpath);
        }
        $report = plagiarism_programming_create_new_report($assignmentparam->cmid, 'jplag');
        $assignmentreportpath = self::get_report_path($report);
        if (is_dir($assignmentreportpath)) {
            plagiarism_programming_rrmdir($assignmentreportpath);
        }
        mkdir($assignmentreportpath);
        $resultfile = $assignmentreportpath . '/download.zip';
        $filehandle = fopen($resultfile, 'w');
        echo "Downloading result...\n";

        // Initialise the handler.
        $progresshandler = new progress_handler('jplag', $jplagparam);

        try {
            $this->jplagstub->download_result($jplagparam->submissionid, $filehandle, $progresshandler);
            fclose($filehandle);
            $this->extract_zip($resultfile);
            unlink($resultfile); // Delete the file after extracting.
            echo "Finished downloading. Everything OK\n";
            $jplagparam->status = 'done_downloading';
            $jplagparam->directory = $assignmentreportpath;
            $jplagparam->message = 'success';
        } catch (SoapFault $fault) {
            debug('Error occurs while downloading: ' . $fault->detail->JPlagException->description . "\n");
            $error = jplag_stub::interpret_soap_fault($fault);
            $jplagparam->status = 'error';
            $jplagparam->message = $error['message'];
            fclose($filehandle);
        }

        return $jplagparam;
    }

    /**
     * Cancels the current submission
     * @param Object $jplagparam
     */
    protected function cancel_submission($jplagparam) {
        try {
            $this->jplagstub->cancel_submission($jplagparam->submissionid);
        } catch (SoapFault $ex) {
            $fault = jplag_stub::interpret_soap_fault($ex);
            $jplagparam->message .= get_string('jplag_cancel_error', 'plagiarism_programming') . ' ' . $fault['message'];
        }
    }

    /**
     * Link to JPlag plagiarism report for the assignment
     *
     * @param stdClass $scaninfo
     *            the record object of the status of jplag (in plagiarism_programming_jplag table)
     */
    public function display_link($scaninfo) {
        global $CFG;
        $reportpath = $CFG->wwwroot . '/plagiarism/programming/view.php?cmid=' . $scaninfo->cmid;
        return "<a target='_blank' href='$reportpath'>JPlag report</a>";
    }

    /**
     * Gets the report path.
     * @param Object $report
     * @return string
     */
    public static function get_report_path($report = null) {
        global $CFG;
        if (!$report) {
            return "{$CFG->tempdir}/plagiarism_report/";
        } else {
            return "{$CFG->tempdir}/plagiarism_report/report{$report->cmid}_v$report->version";
        }
    }

    /**
     * The supported languages of jplag
     *
     * @return Array The supported languages of JPlag under an array('language', 'code')
     *         - code is used by the server to identify the language
     */
    public static function get_supported_language() {
        return self::$supportedlanguages;
    }

    /**
     * Compress all the students submission into one zip file
     *
     * @param int $baselength
     *            the length of the base directory
     * @param string $dir
     *            the directory to compress
     * @param ZipArchive $submitzipfile
     *            the ZipArchive to add files in
     * @return void
     */
    private function jplag_zip_directory($baselength, $dir, $submitzipfile) {
        if (is_dir($dir)) {
            $archivedirname = substr($dir, $baselength, -1);
            $submitzipfile->addEmptyDir($archivedirname);
            $allfiles = scandir($dir);
            foreach ($allfiles as $file) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                $path = $dir . $file;
                if (is_dir($path)) {
                    $this->jplag_zip_directory($baselength, $path . '/', $submitzipfile);
                } else {
                    $archivefilename = substr($path, $baselength);
                    $filecontent = file_get_contents($path);
                    $submitzipfile->addFromString($archivefilename, $filecontent);
                }
            }
        }
    }

    /**
     * Extract the report zip files
     *
     * @param string $zipfile
     *            the full path of the file to extract
     * @return void
     */
    private function extract_zip($zipfile) {
        assert(is_file($zipfile));
        $handle = zip_open($zipfile);
        $basedir = dirname($zipfile);
        if ($handle) {
            while ($zipentry = zip_read($handle)) {
                $entryname = zip_entry_name($zipentry);
                if (substr($entryname, -1) != '/') { // A file.
                    $fp = plagiarism_programming_create_file($basedir . '/' . $entryname);
                    if (zip_entry_open($handle, $zipentry, 'r')) {
                        $buf = zip_entry_read($zipentry, zip_entry_filesize($zipentry));
                        fwrite($fp, $buf);
                        zip_entry_close($zipentry);
                        fclose($fp);
                    }
                }
            }
        }
    }

    /**
     * Parse the report
     *
     * @param stdClass $assignment
     *            the assignment config record object (of plagiarism_programming table)
     * @param stdClass $jplaginfo
     *            the jplag status record object (of plagiarism_programming_jplag table)
     * @return stdClass the same $jplag_info record, with status updated
     */
    public function parse_result($assignment, $jplaginfo) {
        $parser = new jplag_parser($assignment->cmid);
        $parser->parse();
        $jplaginfo->status = 'finished';
        return $jplaginfo;
    }

    /**
     * Get the toolname
     */
    public function get_name() {
        return 'jplag';
    }
}
