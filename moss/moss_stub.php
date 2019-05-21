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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');
require_once(__DIR__ . '/../../../lib/filelib.php');

/**
 * Wrapper class.
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moss_stub {
    /**
     * @var $userid
     */
    private $userid;
    /**
     * @var $proxyhost
     */
    private $proxyhost;
    /**
     * @var $proxyport
     */
    private $proxyport;
    /**
     * @var $proxylogin
     */
    private $proxylogin;
    /**
     * @var $proxypass
     */
    private $proxypass;
    /**
     * @var TIMEOUT
     */
    const TIMEOUT = 3;    // Maximum time to open socket or download result in seconds.
    /**
     * @var MOSSHOST
     */
    const MOSSHOST = 'moss.stanford.edu';
    /**
     * @var MOSSPORT
     */
    const MOSSPORT = 7690;

    /**
     * Initialize
     * @param Number $userid
     * @param String $proxyhost
     * @param Number $proxyport
     * @param String $proxylogin
     * @param String $proxypass
     */
    public function __construct($userid, $proxyhost = null, $proxyport = null, $proxylogin = null, $proxypass = null) {
        $this->userid = $userid;
        if (! empty($proxyhost) && ! empty($proxyport)) {
            $this->proxyhost = $proxyhost;
            $this->proxyport = $proxyport;
        }
        if (! empty($proxylogin) && ! empty($proxypass)) {
            $this->proxylogin = $proxylogin;
            $this->proxypass = $proxypass;
        }
    }

    /**
     * Scans the assignment.
     * @param Array $filelist
     * @param String $language
     * @param progress_handler $handler
     * @return string[]
     */
    public function scan_assignment(&$filelist, $language, progress_handler $handler) {
        $totalsize = $this->calculate_total_size($filelist);

        $this->update_progress($handler, 'uploading', 0, $totalsize);
        $userid = $this->userid;

        $socket = $this->create_connection_to_moss();
        if (! $socket) {
            return array(
                'status' => 'KO',
                'error' => get_string('moss_connection_error', 'plagiarism_programming')
            );
        }

        echo "moss $userid\n";
        fwrite($socket, "moss $userid\n");
        // If userid is invalid, MOSS will automatically close the connection and the next write return false.

        echo "directory 1\n";
        $result = fwrite($socket, "directory 1\n");
        // Send other parameters. Should normally success unless connection is interrupted in the middle.
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

        if ($answer == 'no') {
            fwrite($socket, "end\n");
            return array(
                'status' => 'KO',
                'error' => get_string('moss_unsupported_feature', 'plagiarism_programming')
            );
        } // TODO ? Add else ?
        $fileid = 1;
        $currentlyuploaded = 0;
        foreach ($filelist as $path => $mossdir) {
            // MOSS has some problem with white space in file names.
            $mossdir = str_replace(' ', '_', $mossdir);
            $content = file_get_contents($path);
            $size = strlen($content);
            $result = fwrite($socket, "file $fileid $language $size $mossdir\n");
            $result = fwrite($socket, $content);
            $currentlyuploaded += $size;
            $this->update_progress($handler, 'uploading', $currentlyuploaded, $totalsize);
            $fileid ++;
        }
        fwrite($socket, "query 0 \n");
        $this->update_progress($handler, 'scanning', 0, $totalsize);

        // This answer returns a link by MOSS to the similarity report.
        fflush($socket);
        stream_set_timeout($socket, 10000); // Wait for the result.
        $answer = fgets($socket);
        $this->update_progress($handler, 'done', 0, $totalsize);
        if ($answer !== false) {
            echo "Response from server: $answer\n";
        } else {
            echo "Response from server: false";
        }
        fwrite($socket, "end\n");
        fflush($socket);
        fclose($socket);
        if (substr($answer, 0, 4) == 'http') {
            $result = array(
                'status' => 'OK',
                'link' => $answer
            );
        } else {
            $result = array(
                'status' => 'KO',
                'error' => get_string('moss_send_error', 'plagiarism_programming')
            );
        }
        return $result;
    }

    /**
     * Create a connection to MOSS server.
     * If proxy server information is provided, tunnel it through a proxy.
     * Otherwise, create a direct connection.
     */
    private function create_connection_to_moss() {
        $errornumber = 0;
        $message = '';
        // Connection is either direct or through a proxy.
        if (! empty($this->proxyhost)) { // Connect through proxy.
            $socket = @fsockopen($this->proxyhost, $this->proxyport, $errornumber, $message, self::TIMEOUT);
            if (! $socket) {
                return false;
            }
            fwrite($socket, 'CONNECT ' . self::MOSSHOST . ':' . self::MOSSPORT . " HTTP/1.0\n");
            if (! empty($this->proxylogin)) {
                $authtoken = base64_encode("$this->proxylogin:$this->proxypass");
                fwrite($socket, "Proxy-Authorization: Basic $authtoken\n");
            }
            fwrite($socket, "\n");
            $answer = fgets($socket);
            if (strpos($answer, "200 Connection established") === false) {
                return false;
            }
            // Swallow one more blank line.
            $answer = fgets($socket);
        } else { // Direct connection.
            $socket = @fsockopen(self::MOSSHOST, self::MOSSPORT, $errornumber, $message, self::TIMEOUT);
        }
        if ($socket) {
            stream_set_timeout($socket, self::TIMEOUT);
        }
        return $socket;
    }

    /**
     * Download the results from moss.
     * @param String $url
     * @param String $downloaddir
     * @param Object $handler
     */
    public function download_result($url, $downloaddir, $handler = null) {
        // Download the main page first.
        if (substr($url, - 1) != '/') {
            $url .= '/';
        }
        if (substr($downloaddir, - 1) != '/') {
            $downloaddir .= '/';
        }
        $mainpage = file_get_contents($url);
        $mainpage = str_replace($url, '', $mainpage); // Strip full link (absolute link -> relative link).
        file_put_contents($downloaddir . 'index.html', $mainpage);

        // Download other comparison files.
        $linkpattern = '/<A HREF=\"(match[0-9]*\.html)\"/'; // Extract the links to other files.
        $matches = array();
        preg_match_all($linkpattern, $mainpage, $matches);
        $matches = array_unique($matches[1]);

        $alllinks = array();
        foreach ($matches as $match) {
            $namenoext = substr($match, 0, - 5); // Trip the html extension.
            $alllinks[] = array(
                'url' => $url . $namenoext . '-0.html',
                'file' => $namenoext . '-0.html'
            );
            $alllinks[] = array(
                'url' => $url . $namenoext . '-1.html',
                'file' => $namenoext . '-1.html'
            );
        }

        $num = count($alllinks);
        $curl = new curl(array(
            'proxy' => true
        ));
        $concurrentnum = 10; // Concurrent files to download at a time.

        // Add a slash at the end if it doesn't exist.
        $downloaddir = (substr($downloaddir, - 1) != '/') ? $downloaddir . '/' : $downloaddir;

        for ($i = 0; $i < $num; $i += $concurrentnum) {
            $group = array_slice($alllinks, $i, $concurrentnum);
            for ($j = 0; $j < count($group); $j ++) {
                $group[$j]['file'] = fopen($downloaddir . $group[$j]['file'], 'wb');
            }
            $curl->download($group);
            $this->update_progress($handler, 'downloading', $i + $concurrentnum, $num);
        }
    }

    /**
     * Gets the size of the filelist.
     * @param Array $filelist
     * @return number
     */
    private function calculate_total_size(&$filelist) {
        $totalsize = 0;
        foreach ($filelist as $path => $file) {
            $totalsize += filesize($path);
        }
        return $totalsize;
    }

    /**
     * Updates the progress of current stage.
     * @param Object $handler
     * @param Number $stage
     * @param Number $currentsize
     * @param Number $totalsize
     */
    private function update_progress($handler, $stage, $currentsize, $totalsize) {
        if ($handler) {
            $percentage = intval($currentsize * 100 / $totalsize);
            $handler->update_progress($stage, $percentage);
        }
    }
}
