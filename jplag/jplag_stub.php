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

define('JPLAG_WSDL_URL',  dirname(__FILE__).'/jplag.wsdl');
define('JPLAG_TYPE_NAMESPACE','http://www.ipd.uni-karlsruhe.de/jplag/types');
define('INVALID_CREDENTIAL',1);
define('MAX_CHUNK_SIZE',81920);     // maximum size per chunk is 81920

define('SUBMISSION_STATUS_UPLOADING',0);
define('SUBMISSION_STATUS_INQUEUE',50);
define('SUBMISSION_STATUS_PARSING',100);
define('SUBMISSION_STATUS_COMPARING',200);
define('SUBMISSION_STATUS_RESULT',230);
define('SUBMISSION_STATUS_DONE',300);
define('SUBMISSION_STATUS_ERROR',400);

define('JPLAG_CREDENTIAL_ERROR', 'server_error');
define('JPLAG_CREDENTIAL_EXPIRED','credential_expired');
define('WS_CONNECT_ERROR','connect_error');

include_once dirname(__FILE__).'/jplag_option.php';

class jplag_stub {
    
    private $client = null;
    
    public function __construct($username=null,$password=null) {
        $this->client = new SoapClient(JPLAG_WSDL_URL);
        if ($username && $password) {
            $this->set_credential($username, $password);
        }
    }
    
    public function set_credential($username,$password) {
        $credential = array('username' => $username,'password' => $password,'compatLevel' => 4);
        $header = new SoapHeader(JPLAG_TYPE_NAMESPACE,'Access',$credential);
        $this->client->__setSoapHeaders($header);
    }
    
    public function check_credential($username=null,$password=null) {
        if ($username && $password) {
            $credential = array('username' => $username,'password' => $password,'compatLevel' => 4);
            $header = new SoapHeader(JPLAG_TYPE_NAMESPACE,'Access',$credential);
            $client = new SoapClient(JPLAG_WSDL_URL);
            $client->__setSoapHeaders($header);
        } else {
            $client = $this->client;
        }
        try {
            $server_info = $client->getServerInfo();
            return TRUE;
        } catch (SoapFault $fault) {
            return $this->interpret_soap_fault($fault);
        }
    }


    /** Send the compressed file to jplag service using SOAP
     *  @param $zip_full_path: path to the zip file
     *  @param $options: options of jplag specifying its parameters. See class jplag_option for detail
     *  @param $progress_handler: a function to inform the upload progress, with one parameter
     *                            that is the percentage of the progress
     */
    public function send_file($zip_full_path, $options, $progress_handler=null) {
        $file = fopen($zip_full_path, 'r');
        $initial_size = filesize($zip_full_path);
        $content = fread($file, MAX_CHUNK_SIZE);
        
        $submissionUpload = new stdClass();
        $submissionUpload->submissionParams = $options;
        $submissionUpload->filesize = $initial_size;
        $submissionUpload->data = $content;
        $uploadParams = new SoapParam($submissionUpload,'startSubmissionUploadParams');
        $submissionID = $this->client->startSubmissionUpload($uploadParams);
        
        $size = $initial_size - strlen($content);
        $this->update_progress($progress_handler, 'uploading', 1-$size/$initial_size);
        
        while ($size > 0) {
            $content = fread($file, MAX_CHUNK_SIZE);
            $this->client->continueSubmissionUpload($content);
            $size -= strlen($content);
            $this->update_progress($progress_handler, 'uploading', 1-$size/$initial_size);
        }
        
        fclose($file);
        return $submissionID;
    }
    
    public function check_status($submissionID) {
        $status = $this->client->getStatus($submissionID);
        return $status;
    }

    public function download_result($submissionID,&$fileHandle,$progress_handler=null) {
        $download_data = $this->client->startResultDownload($submissionID);
        fwrite($fileHandle, $download_data->data);

        $initial_size = $download_data->filesize;
        $size = $initial_size - strlen($download_data->data);
        $this->update_progress($progress_handler, 'downloading', 1-$size/$initial_size);

        while ($size > 0) {
            $data = $this->client->continueResultDownload(0);
            fwrite($fileHandle, $data);
            $size -= strlen($data);
            $this->update_progress($progress_handler, 'downloading', 1-$size/$initial_size);
        }
        return;
    }
    
    private function update_progress($handler,$stage,$percentage) {
        if ($handler) {
            $handler->update_progress($stage,intval($percentage*100));
        }
    }
    
    public function interpret_soap_fault($fault) {
        if (strpos($fault->faultcode,'Server')!==FALSE) {
            if (strpos($fault->detail->JPlagException->repair,'expired')!==FALSE) {
                return JPLAG_CREDENTIAL_EXPIRED;
            } elseif (strpos($fault->detail->JPlagException->repair,'username')!==FALSE) {
                return JPLAG_CREDENTIAL_ERROR;
            }
        } elseif (strpos($fault->faultcode,'HTTP')!==FALSE) {
            return WS_CONNECT_ERROR;
        }
    }
}