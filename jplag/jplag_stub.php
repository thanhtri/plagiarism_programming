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
 * The stub class making SOAP call to JPlag webservice and receive the result
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

define('JPLAG_WSDL_URL', dirname(__FILE__) . '/jplag.wsdl');
define('JPLAG_TYPE_NAMESPACE', 'http://jplag.ipd.kit.edu/JPlagService/types');
define('INVALID_CREDENTIAL', 1);
define('MAX_CHUNK_SIZE', 81920); // Maximum size per chunk is 81920.

define('SUBMISSION_STATUS_UPLOADING', 0);
define('SUBMISSION_STATUS_INQUEUE', 50);
define('SUBMISSION_STATUS_PARSING', 100);
define('SUBMISSION_STATUS_COMPARING', 200);
define('SUBMISSION_STATUS_RESULT', 230);
define('SUBMISSION_STATUS_DONE', 300);
define('SUBMISSION_STATUS_ERROR', 400);
define('SUBMISSION_STATUS_ERROR_BAD_LANGUAGE', 401);
define('SUBMISSION_STATUS_ERROR_NOT_ENOUGH_SUBMISSION', 402);
define('SUBMISSION_STATUS_ERROR_ABORTED', 403);

define('JPLAG_CREDENTIAL_ERROR', 'server_error');
define('JPLAG_CREDENTIAL_EXPIRED', 'credential_expired');
define('JPLAG_SERVER_UNKNOWN_ERROR', 'server_unknown_error');
define('WS_CONNECT_ERROR', 'connect_error');

require_once(dirname(__FILE__).'/jplag_option.php');

/**
 * Wrapper class for jplag stub.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jplag_stub{
    /**
     * @var $client
     */
    private $client = null;

    /**
     * Initializes the important variables
     * @param String $username
     * @param String $password
     * @param String $proxyhost
     * @param Number $proxyport
     * @param String $proxyuser
     * @param String $proxypass
     */
    public function __construct($username = null, $password = null, $proxyhost = null,
        $proxyport = null, $proxyuser = null, $proxypass = null) {

        $proxyparam = array();
        if (!empty($proxyhost) && !empty($proxyport)) {
            $proxyparam['proxy_host'] = $proxyhost;
            $proxyparam['proxy_port'] = $proxyport;
        }
        if (!empty($proxyuser) && !empty($proxypass)) {
            $proxyparam['proxy_login'] = $proxyuser;
            $proxyparam['proxy_password'] = $proxypass;
        }
        $this->client = new SoapClient(JPLAG_WSDL_URL, $proxyparam);
        if ($username && $password) {
            $this->set_credential($username, $password);
        }
    }

    /**
     * Set the JPlag credential for the stub.
     * This credential is used for every request made by this stub.
     * The credential could be also provided via the constructor. Note that this method doesn't verify the credential
     * with JPlag server
     *
     * @param string $username JPlag username
     * @param string $password JPlag password
     * @return void
     */
    public function set_credential($username, $password) {
        $credential = array(
            'username' => $username,
            'password' => $password,
            'compatLevel' => 4
        );
        $header = new SoapHeader(JPLAG_TYPE_NAMESPACE, 'Access', $credential);
        $this->client->__setSoapHeaders($header);
    }

    /**
     * Check the provided username and password to see if it's a valid JPlag account.
     *
     * @param string $username JPlag username
     * @param string $password JPlag password
     * @return true if it is a valid credential,
     *         array('code'=><errorcode>, 'message'=><error message>) if not valid or unable to check.
     *         See the defines const at the beginning of the file for the code used
     */
    public function check_credential($username = null, $password = null) {
        if ($username && $password) {
            $credential = array(
                'username' => $username,
                'password' => $password,
                'compatLevel' => '4'
            );
            $header = new SoapHeader(JPLAG_TYPE_NAMESPACE, 'Access', $credential);
            $client = new SoapClient(JPLAG_WSDL_URL);
            $client->__setSoapHeaders($header);
        } else {
            $client = $this->client;
        }
        try {
            $serverinfo = $client->getServerInfo();
            return true;
        } catch (SoapFault $fault) {
            return self::interpret_soap_fault($fault);
        }
    }

    /**
     * Send the compressed file to jplag service using SOAP
     *
     * @param String $zipfullpath path to the zip file
     * @param Object $options options of jplag specifying its parameters. See class jplag_option for detail
     * @param Object $progresshandler a function to inform the upload progress, with one parameter
     *            that is the percentage of the progress
     * @return Number $submission_id the id of this submission, used to ask the server the scanning status of
     *         this submission and download the report
     */
    public function send_file($zipfullpath, $options, $progresshandler = null) {
        $file = fopen($zipfullpath, 'r');
        $initialsize = filesize($zipfullpath);
        $content = fread($file, MAX_CHUNK_SIZE);

        $submissionupload = new stdClass();
        $submissionupload->submissionParams = $options;
        $submissionupload->filesize = $initialsize;
        $submissionupload->data = $content;
        $uploadparams = new SoapParam($submissionupload, 'startSubmissionUploadParams');
        $submissionid = $this->client->startSubmissionUpload($uploadparams);

        $size = $initialsize - strlen($content);
        $this->update_progress($progresshandler, 'uploading', 1 - $size / $initialsize);

        while ($size > 0) {
            $content = fread($file, MAX_CHUNK_SIZE);
            $this->client->continueSubmissionUpload($content);
            $size -= strlen($content);
            $this->update_progress($progresshandler, 'uploading', 1 - $size / $initialsize);
        }

        fclose($file);
        return $submissionid;
    }

    /**
     * Check the status of the scanning.
     * This method is called to ask the server whether a scanning is finished or not.
     *
     * @param string $submissionid
     *            the id returned when calling send_file
     * @return int status of the submission (see the define constants in this file for the possible status)
     */
    public function check_status($submissionid) {
        $status = $this->client->getStatus($submissionid);
        return $status;
    }

    /**
     * Download the report.
     * It is a zip files containing html pages.
     * It throws a SoapFault object if an error occurs (use interpret_soap_fault method to get a meaningfull error message)
     *
     * @param string $submissionid
     *            the id returned when calling send_file
     * @param string $filehandle
     *            a write handle (returned by fopen) to write the report file
     * @param progress_handler $progresshandler
     *            the handler to inform the progress
     * @return void
     */
    public function download_result($submissionid, &$filehandle, $progresshandler = null) {
        $downloaddata = $this->client->startResultDownload($submissionid);
        fwrite($filehandle, $downloaddata->data);

        $initialsize = $downloaddata->filesize;
        $size = $initialsize - strlen($downloaddata->data);
        $this->update_progress($progresshandler, 'downloading', 1 - $size / $initialsize);

        while ($size > 0) {
            $data = $this->client->continueResultDownload(0);
            fwrite($filehandle, $data);
            $size -= strlen($data);
            $this->update_progress($progresshandler, 'downloading', 1 - $size / $initialsize);
        }
        return;
    }

    /**
     * Cancel the submission in case the server inform an error or the result is not needed anymore
     *
     * @param string $submissionid the id returned when calling send_file
     * @return void
     */
    public function cancel_submission($submissionid) {
        $this->client->cancelSubmission($submissionid);
    }

    /**
     * Updates the progress handler
     *
     * @param Object $handler Object a function to inform the upload progress, with one parameter
     *            that is the percentage of the progress
     * @param String $stage
     * @param Number $percentage
     */
    private function update_progress($handler, $stage, $percentage) {
        if ($handler) {
            $handler->update_progress($stage, intval($percentage * 100));
        }
    }

    /**
     * Functions in this class will throw a SoapFault object once an error occur.
     * This method will provide a meaningfull
     * message for the fault object
     *
     * @param SoapFault $fault
     *            the fault object thrown
     * @return array associated array with 'code' is the code of the fault and 'message' is the interpreted message
     */
    public static function interpret_soap_fault($fault) {
        if (strpos($fault->faultcode, 'Server') !== false) {
            if (strpos($fault->detail->JPlagException->repair, 'expired') !== false) {
                return array(
                    'code' => JPLAG_CREDENTIAL_EXPIRED,
                    'message' => get_string('jplag_account_expired', 'plagiarism_programming')
                );
            } else if (strpos($fault->detail->JPlagException->repair, 'username') !== false) {
                return array(
                    'code' => JPLAG_CREDENTIAL_ERROR,
                    'message' => get_string('jplag_account_error', 'plagiarism_programming')
                );
            } else { // Defult: get message directly from server.
                return array(
                    'code' => JPLAG_SERVER_UNKNOWN_ERROR,
                    'message' => $fault->detail->JPlagException->description . ' ' . $fault->detail->JPlagException->repair
                );
            }
        } else if (strpos($fault->faultcode, 'HTTP') !== false) {
            return array(
                'code' => WS_CONNECT_ERROR,
                'message' => get_string('jplag_connection_error', 'plagiarism_programming')
            );
        }
    }

    /**
     * Gets the string to be displayed.
     *
     * @param String $status
     * @return string
     */
    public static function translate_scanning_status($status) {
        switch ($status->state) {
            case SUBMISSION_STATUS_UPLOADING:
                return get_string('uploading', 'plagiarism_programming');
            case SUBMISSION_STATUS_COMPARING:
                return get_string('scanning', 'plagiarism_programming');
            case SUBMISSION_STATUS_DONE:
                return get_string('scanning_done', 'plagiarism_programming');
            case SUBMISSION_STATUS_INQUEUE:
                return get_string('inqueue_on_server', 'plagiarism_programming');
            case SUBMISSION_STATUS_PARSING:
                return get_string('parsing_on_server', 'plagiarism_programming');
            case SUBMISSION_STATUS_RESULT:
                return get_string('generating_report_on_server', 'plagiarism_programming');
            case SUBMISSION_STATUS_ERROR_BAD_LANGUAGE:
                return get_string('error_bad_language', 'plagiarism_programming');
            case SUBMISSION_STATUS_ERROR_NOT_ENOUGH_SUBMISSION:
                return get_string('error_not_enough_submission', 'plagiarism_programming');
            case SUBMISSION_STATUS_ERROR:
                return $status->report;
        }
    }
}