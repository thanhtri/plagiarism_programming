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
 * This class is the PHP implementation of MOSS client
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');
require_once(__DIR__.'/../../../lib/filelib.php');

class moss_stub {
    private $userid;
    private $proxy_host;
    private $proxy_port;
    private $proxy_login;
    private $proxy_pass;

    const TIMEOUT = 3; // maximum time to open socket or download result
    const MOSS_HOST = 'moss.stanford.edu';
    const MOSS_PORT = 7690;

    public function __construct($userid, $proxy_host=null, $proxy_port=null, $proxy_login=null, $proxy_pass=null) {
        $this->userid = $userid;
        if (!empty($proxy_host) && !empty($proxy_port)) {
            $this->proxy_host = $proxy_host;
            $this->proxy_port = $proxy_port;
        }
        if (!empty($proxy_login) && !empty($proxy_pass)) {
            $this->proxy_login = $proxy_login;
            $this->proxy_pass = $proxy_pass;
        }
    }

    public function scan_assignment(&$file_list, $language, progress_handler $handler) {

        $total_size = $this->calculate_total_size($file_list);

        $this->update_progress($handler, 'uploading', 0, $total_size);
        $userid = $this->userid;

        $socket = $this->create_connection_to_moss();
        if (!$socket) {
            return array('status' => 'KO', 'error' => get_string('moss_connection_error', 'plagiarism_programming'));
        }

        echo "moss $userid\n";
        fwrite($socket, "moss $userid\n");
        // if userid is invalid, MOSS will automatically close the connection and the next write return false

        echo "directory 1\n";
        $result = fwrite($socket, "directory 1\n");
        // send other parameters. Should normally success unless connection is interrupted in the middle
        echo "X 0\n";
        $result = fwrite($socket, "X 0\n");
        echo "maxmatches 1000\n";
        $result = fwrite($socket, "maxmatches 1000\n");
        echo "show 250\n";
        $result = fwrite($socket, "show 250\n");
        echo "language $language\n";
        $result = fwrite($socket, "language $language\n");

        $answer = fgets($socket);
        echo "$answer\n";

        if ($answer=='no') {
            fwrite($socket, "end\n");
            return array('status' => 'KO', 'error'=>get_string('moss_unsupported_feature', 'plagiarism_programming'));
        } // else
        $fileid = 1;
        $currently_uploaded = 0;
        foreach ($file_list as $path => $moss_dir) {
            $content = file_get_contents($path);
            $size = strlen($content);
            $result = fwrite($socket, "file $fileid $language $size $moss_dir\n");
            $result = fwrite($socket, $content);
            $currently_uploaded += $size;
            $this->update_progress($handler, 'uploading', $currently_uploaded, $total_size);
            $fileid++;
        }
        fwrite($socket, "query 0 \n");
        $this->update_progress($handler, 'scanning', 0, $total_size);

        // this answer returns a link by MOSS to the similarity report
        fflush($socket);
        stream_set_timeout($socket, 10000); // wait for the result
        $answer = fgets($socket);
        $this->update_progress($handler, 'done', 0, $total_size);
        if ($answer!==FALSE) {
            echo "Response from server: $answer\n";
        } else {
            echo "Response from server: false";
        }
        fwrite($socket, "end\n");
        fflush($socket);
        fclose($socket);
        if (substr($answer, 0, 4)=='http') {
            $result = array('status' => 'OK', 'link' => $answer);
        } else {
            $result = array('status' => 'KO', 'error' => get_string('moss_send_error', 'plagiarism_programming'));
        }
        return $result;
    }

    /**
     * Create a connection to MOSS server. If proxy server information is provided, tunnel it through a proxy.
     * Otherwise, create a direct connection.
     */
    private function create_connection_to_moss() {
        $error_no = 0;
        $message = '';
        // connection is either direct or through a proxy
        if (!empty($this->proxy_host)) { // connect through proxy
            $socket = @fsockopen($this->proxy_host, $this->proxy_port, $error_no, $message, self::TIMEOUT);
            if (!$socket) {
                return false;
            }
            fwrite($socket, 'CONNECT '.self::MOSS_HOST.':'.self::MOSS_PORT." HTTP/1.0\n");
            if (!empty($this->proxy_login)) {
                $auth_token = base64_encode("$this->proxy_login:$this->proxy_pass");
                fwrite($socket, "Proxy-Authorization: Basic $auth_token\n");
            }
            fwrite($socket, "\n");
            $answer = fgets($socket);
            if (strpos($answer, "200 Connection established")===FALSE) {
                return false;
            }
            // swallow one more blank line
            $answer = fgets($socket);

        } else { // direct connection
            $socket = @fsockopen(self::MOSS_HOST, self::MOSS_PORT, $error_no, $message, self::TIMEOUT);
        }
        if ($socket) {
            stream_set_timeout($socket, self::TIMEOUT);
        }
        return $socket;
    }

    public function download_result($url, $download_dir, $handler=null) {
        // download the main page first
        if (substr($url, -1)!='/') {
            $url .= '/';
        }
        if (substr($download_dir, -1)!='/') {
            $download_dir .= '/';
        }
        $main_page = file_get_contents($url);
        $main_page = str_replace($url, '', $main_page); // strip full link (absolute link -> relative link)
        file_put_contents($download_dir.'index.html', $main_page);

        // download other comparison files
        $link_pattern = '/<A HREF=\"(match[0-9]*\.html)\"/'; // (extract the links to other files)
        preg_match_all($link_pattern, $main_page, $matches);
        $matches = array_unique($matches[1]);

        $all_links = array();
        foreach ($matches as $match) {
            $name_no_ext = substr($match, 0, -5);  // trip the html extension
            $all_links[]= array('url'=>$url.$name_no_ext.'-0.html', 'file'=>$name_no_ext.'-0.html');
            $all_links[]= array('url'=>$url.$name_no_ext.'-1.html', 'file'=>$name_no_ext.'-1.html');
        }

        $num = count($all_links);
        $curl = new curl(array('proxy'=>true));
        $concurrent_num = 10;  // concurrent files to download at a time

        // add a slash at the end if it doesn't exist
        $download_dir = (substr($download_dir, -1)!='/')?$download_dir.'/':$download_dir;

        for ($i=0; $i<$num; $i+=$concurrent_num) {
            $group = array_slice($all_links, $i, $concurrent_num);
            for ($j=0; $j<count($group); $j++) {
                $group[$j]['file'] = fopen($download_dir.$group[$j]['file'], 'wb');
            }
            $curl->download($group);
            $this->update_progress($handler, 'downloading', $i+$concurrent_num, $num);
        }
    }

    private function calculate_total_size(&$file_list) {
        $total_size = 0;
        foreach ($file_list as $path => $file) {
            $total_size += filesize($path);
        }
        return $total_size;
    }

    private function update_progress($handler, $stage, $current_size, $total_size) {
        if ($handler) {
            $percentage = intval($current_size*100/$total_size);
            $handler->update_progress($stage, $percentage);
        }
    }
}
