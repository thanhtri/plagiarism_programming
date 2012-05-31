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
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

define('JPLAG_WSDL_URL',  dirname(__FILE__).'/jplag.wsdl');
define('JPLAG_TYPE_NAMESPACE', 'http://www.ipd.uni-karlsruhe.de/jplag/types');
define('INVALID_CREDENTIAL', 1);
define('MAX_CHUNK_SIZE', 81920);     // maximum size per chunk is 81920

define('SUBMISSION_STATUS_UPLOADING', 0);
define('SUBMISSION_STATUS_INQUEUE', 50);
define('SUBMISSION_STATUS_PARSING', 100);
define('SUBMISSION_STATUS_COMPARING', 200);
define('SUBMISSION_STATUS_RESULT', 230);
define('SUBMISSION_STATUS_DONE', 300);
define('SUBMISSION_STATUS_ERROR', 400);

define('JPLAG_CREDENTIAL_ERROR', 'server_error');
define('JPLAG_CREDENTIAL_EXPIRED', 'credential_expired');
define('JPLAG_SERVER_UNKNOWN_ERROR', 'server_unknown_error');
define('WS_CONNECT_ERROR', 'connect_error');

require_once(dirname(__FILE__).'/jplag_option.php');

class jplag_stub {

    private $client = null;

    public function __construct($username=null, $password=null) {
        $this->client = new SoapClient(JPLAG_WSDL_URL);
        if ($username && $password) {
            $this->set_credential($username, $password);
        }
    }

    /**
     * Set the JPlag credential for the stub. This credential is used for every request made by this stub.
     * The credential could be also provided via the constructor. Note that this method doesn't verify the credential
     * with JPlag server
     * @param string username JPlag username
     * @param string password JPlag password
     * @return void
     */
    public function set_credential($username, $password) {
        $credential = array('username' => $username, 'password' => $password, 'compatLevel' => 4);
        $header = new SoapHeader(JPLAG_TYPE_NAMESPACE, 'Access', $credential);
        $this->client->__setSoapHeaders($header);
    }

    /**
     * Check the provided username and password to see if it's a valid JPlag account.
     * @param string username JPlag username
     * @param string password JPlag password
     * @return true if it is a valid credential,
     * array('code'=><errorcode>, 'message'=><error message>) if not valid or unable to check.
     * See the defines const at the beginning of the file for the code used
     */
    public function check_credential($username=null, $password=null) {
        if ($username && $password) {
            $credential = array('username' => $username, 'password' => $password, 'compatLevel' => 4);
            $header = new SoapHeader(JPLAG_TYPE_NAMESPACE, 'Access', $credential);
            $client = new SoapClient(JPLAG_WSDL_URL);
            $client->__setSoapHeaders($header);
        } else {
            $client = $this->client;
        }
        try {
            $server_info = $client->getServerInfo();
            return true;
        } catch (SoapFault $fault) {
            return self::interpret_soap_fault($fault);
        }
    }

    /** Send the compressed file to jplag service using SOAP
     * @param $zip_full_path path to the zip file
     * @param $options options of jplag specifying its parameters. See class jplag_option for detail
     * @param $progress_handler a function to inform the upload progress, with one parameter
     *                           that is the percentage of the progress
     * @return string $submission_id the id of this submission, used to ask the server the scanning status of
     * this submission and download the report
     */
    public function send_file($zip_full_path, $options, $progress_handler=null) {
        $file = fopen($zip_full_path, 'r');
        $initial_size = filesize($zip_full_path);
        $content = fread($file, MAX_CHUNK_SIZE);

        $submission_upload = new stdClass();
        $submission_upload->submissionParams = $options;
        $submission_upload->filesize = $initial_size;
        $submission_upload->data = $content;
        $upload_params = new SoapParam($submission_upload, 'startSubmissionUploadParams');
        $submission_id = $this->client->startSubmissionUpload($upload_params);

        $size = $initial_size - strlen($content);
        $this->update_progress($progress_handler, 'uploading', 1-$size/$initial_size);

        while ($size > 0) {
            $content = fread($file, MAX_CHUNK_SIZE);
            $this->client->continueSubmissionUpload($content);
            $size -= strlen($content);
            $this->update_progress($progress_handler, 'uploading', 1-$size/$initial_size);
        }

        fclose($file);
        return $submission_id;
    }

    /**
     * Check the status of the scanning. This method is called to ask the server whether a scanning is finished or not.
     * @param string $submission_id the id returned when calling send_file
     * @return int status of the submission (see the define constants in this file for the possible status)
     */
    public function check_status($submission_id) {
        $status = $this->client->getStatus($submission_id);
        return $status;
    }

    /**
     * Download the report. It is a zip files containing html pages.
     * It throws a SoapFault object if an error occurs (use interpret_soap_fault method to get a meaningfull error message)
     * @param string $submission_id the id returned when calling send_file
     * @param string $file_handle a write handle (returned by fopen) to write the report file
     * @param progress_handler $progress_handler the handler to inform the progress
     * @return void
     */
    public function download_result($submission_id, &$file_handle, $progress_handler=null) {
        $download_data = $this->client->startResultDownload($submission_id);
        fwrite($file_handle, $download_data->data);

        $initial_size = $download_data->filesize;
        $size = $initial_size - strlen($download_data->data);
        $this->update_progress($progress_handler, 'downloading', 1-$size/$initial_size);

        while ($size > 0) {
            $data = $this->client->continueResultDownload(0);
            fwrite($file_handle, $data);
            $size -= strlen($data);
            $this->update_progress($progress_handler, 'downloading', 1-$size/$initial_size);
        }
        return;
    }

    /**
     * update_progress hanlder
     */
    private function update_progress($handler, $stage, $percentage) {
        if ($handler) {
            $handler->update_progress($stage, intval($percentage*100));
        }
    }

    /**
     * Functions in this class will throw a SoapFault object once an error occur. This method will provide a meaningfull
     * message for the fault object
     * @param SoapFault $fault the fault object thrown
     * @return array associated array with 'code' is the code of the fault and 'message' is the interpreted message
     */
    public static function interpret_soap_fault($fault) {
        if (strpos($fault->faultcode, 'Server')!==false) {
            if (strpos($fault->detail->JPlagException->repair, 'expired')!==false) {
                return array('code'=>JPLAG_CREDENTIAL_EXPIRED,
                    'message'=>get_string('jplag_account_expired', 'plagiarism_programming'));
            } else if (strpos($fault->detail->JPlagException->repair, 'username')!==false) {
                return array('code'=>JPLAG_CREDENTIAL_ERROR,
                    'message'=>get_string('jplag_account_error', 'plagiarism_programming'));
            } else { // defult: get message directly from server
                return array('code'=>JPLAG_SERVER_UNKNOWN_ERROR,
                    'message'=>$fault->detail->JPlagException->description.' '.$fault->detail->JPlagException->repair);
            }
        } else if (strpos($fault->faultcode, 'HTTP')!==false) {
            return array('code'=>WS_CONNECT_ERROR,
                'message'=>get_string('jplag_connection_error', 'plagiarism_programming'));
        }
    }
}