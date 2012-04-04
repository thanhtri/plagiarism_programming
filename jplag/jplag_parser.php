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
 * This class parse the result of the generated webpages of JPlag
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class jplag_parser {
    private $filename;
    private $cmid;

    public function __construct($file,$cmid) {
        $this->filename = $file;
        $this->cmid = $cmid;
    }

    public function parse() {
        global $DB;
        
        // delete the result already exist
        $DB->delete_records('programming_result',array('cmid'=> $this->cmid,'detector'=>'jplag'));

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        @$dom->loadHTMLFile($this->filename);

        // extract table elements, only the third and forth tables contain the required info
        $tables = $dom->getElementsByTagName('table');
        $average_tbl = $tables->item(2);
        $rows = $average_tbl->getElementsByTagName('tr');
        $rownum = $rows->length;
        
        $res = new stdClass();
        $res->detector = 'jplag';
        $res->cmid = $this->cmid;
        for ($i=0; $i<$rownum; $i++) {
            $row = $rows->item($i);
            $cells = $row->getElementsByTagName('td');
            $student1_id = $cells->item(0)->nodeValue;

            for ($j=2; $j<$cells->length; $j++) {
                $cell = $cells->item($j);
                $link = $cell->childNodes->item(0);
                $student2_id = $link->nodeValue;
                $file = $link->getAttribute('href');
                $percentage = substr($cell->childNodes->item(2)->nodeValue,1,-2);
                
                // save to the db
                $res->student1_id = $student1_id;
                $res->student2_id = $student2_id;
                $res->similarity1 = $percentage;
                $res->comparison = $file;
                $DB->insert_record('programming_result',$res);
            }
        }
    }
}