<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of moss_stub
 *
 * @author thanhtri
 */
define ('MOSS_HOST','moss.stanford.edu');
define ('MOSS_PORT',7690);
class moss_stub {
    private $userid;
    
    public function __construct($userid) {
        $this->userid = $userid;
    }

    public function scan_assignment(&$file_list,$language,progress_handler $handler) {
        
        $total_size = $this->calculate_total_size($file_list);

        $this->update_progress($handler,'uploading', 0, $total_size);
        $userid = $this->userid;

        $socket = fsockopen(MOSS_HOST,MOSS_PORT);

        fwrite($socket,"moss $userid\n");
        fwrite($socket, "directory 1\n");
        fwrite($socket, "X 0\n");
        fwrite($socket, "maxmatches 1000\n");
        fwrite($socket, "show 250\n");
        fwrite($socket, "language $language\n");

        $answer = fgets($socket);

        if ($answer=='no') {
            fwrite($socket, "end\n");
            return false;
        } // else
        $fileid = 1;
        $currently_uploaded = 0;
        foreach ($file_list as $path=>$moss_dir) {
            $content = file_get_contents($path);
            $size = strlen($content);
            fwrite($socket, "file $fileid java $size $moss_dir\n");
            fwrite($socket,$content);
            fflush($socket);
            $currently_uploaded += $size;
            $this->update_progress($handler,'uploading', $currently_uploaded, $total_size);
            $fileid++;
        }
        fwrite($socket,"query 0 \n");
        $this->update_progress($handler,'scanning', 0, $total_size);
        $answer = fgets($socket);
        $this->update_progress($handler,'done', 0, $total_size);
        fwrite($socket,"end\n");
        fclose($socket);
        return $answer;
    }
    
    public function download_result($url,$download_dir,$handler=null) {
        // download the main page first
        if (substr($url, -1)!='/') {
            $url .= '/';
        }
        if (substr($download_dir,-1)!='/') {
            $download_dir .= '/';
        }
        $main_page = file_get_contents($url);
        $main_page = str_replace($url, '', $main_page); // strip full link (absolute link -> relative link)
        $index_file = fopen($download_dir.'index.html', 'w');
        fwrite($index_file,$main_page);
        fclose($index_file);

        // download other comparison files
        $link_pattern = '/<A HREF=\"(match[0-9]*\.html)\"/'; // (extract the links to other files)
        preg_match_all($link_pattern, $main_page, $matches);
        $matches = array_unique($matches[1]);

        $all_links = array();
        foreach ($matches as $match) {
            $all_links[]= $url.$match;
            $name_no_ext = substr($match,0,-5);  // trip the html extension
            $all_links[]= $url.$name_no_ext.'-top.html';
            $all_links[]= $url.$name_no_ext.'-0.html';
            $all_links[]= $url.$name_no_ext.'-1.html';
        }

        $num = count($all_links);
        $concurrent_num = 30;  // concurrent files to download at a time
        for ($i=0; $i<$num; $i+=$concurrent_num) {
            $group = array_slice($all_links, $i, $concurrent_num);
            curl_download($group, $download_dir);
            $this->update_progress($handler, 'downloading', $i+$concurrent_num, $num);
        }
    }
    
    private function calculate_total_size(&$file_list) {
        $total_size = 0;
        foreach ($file_list as $path=>$file) {
            $total_size += filesize($path);
        }
        return $total_size;
    }

    private function update_progress($handler,$stage,$current_size,$total_size) {
        if ($handler) {
            $percentage = intval($current_size*100/$total_size);
            $handler->update_progress($stage, $percentage);
        }
    }
}
