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
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

require_once(__DIR__.'/../utils.php');

class jplag_parser {
    private $filename;
    private $cmid;
    private $report;

    public function __construct($cmid) {
        $this->cmid = $cmid;
        $this->report = get_latest_report($cmid, 'jplag');
        $this->filename = jplag_tool::get_report_path($this->report).'/index.html';
    }

    public function parse() {
        global $DB;

        $directory = dirname($this->filename);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!@$dom->loadHTMLFile($this->filename)) {
            trigger_error('Loading index.html file failed!', E_USER_ERROR);
        }

        // extract table elements, only the third and forth tables contain the required info
        $tables = $dom->getElementsByTagName('table');
        $average_tbl = $tables->item(2);
        $rows = $average_tbl->getElementsByTagName('tr');
        $rownum = $rows->length;

        $res = new stdClass();
        $res->reportid = $this->report->id;
        for ($i=0; $i<$rownum; $i++) {
            $row = $rows->item($i);
            $cells = $row->getElementsByTagName('td');

            for ($j=2; $j<$cells->length; $j++) {
                $cell = $cells->item($j);
                $link = $cell->childNodes->item(0);
                $file = $link->getAttribute('href');

                // the similarity percentage of each student is contained in the -top file
                $pattern = '/<TR><TH><TH>([0-9]*) \(([0-9]*\.[0-9]*)%\)<TH>([0-9]*) \(([0-9]*\.[0-9]*)%\)<TH>/';
                $top_filename = $directory.'/'.substr($file, 0, -5).'-top.html';
                $top_content = file_get_contents($top_filename);
                $matches = null;
                preg_match($pattern, $top_content, $matches);

                // save to the db
                $res->student1_id = $matches[1];
                $res->student2_id = $matches[3];
                $res->similarity1 = $matches[2];
                $res->similarity2 = $matches[4];
                $res->comparison = $file;
                $DB->insert_record('plagiarism_programming_reslt', $res);
            }
        }
        $this->get_similar_parts();
    }

    public function get_similar_parts() {
        global $DB;
        $pairs = $DB->get_records('plagiarism_programming_reslt', array('reportid'=>$this->report->id));
        $path = dirname($this->filename);

        $similarity_array = array();
        $file_array = array();

        foreach ($pairs as $pair) {
            $file = $pair->comparison;
            $file_0 = $path.'/'.substr($file, 0, -5).'-0.html';
            $file_1 = $path.'/'.substr($file, 0, -5).'-1.html';
            $file = $path.'/'.$file;

            $this->parse_similar_parts($pair->student1_id, $pair->student2_id, $file_0, $similarity_array, $file_array);
            $this->parse_similar_parts($pair->student2_id, $pair->student1_id, $file_1, $similarity_array, $file_array);

            // TODO: uncomment to delete these files after debugging
            if (!debugging()) {
                unlink($file);
                unlink($file_0);
                unlink($file_1);
            }
        }
        $this->save_code($file_array, $similarity_array, $path);
    }

    private function parse_similar_parts($student_id, $other_student_id, $filename, &$similarity_array, &$file_array) {

        if (!isset($similarity_array[$student_id])) {
            $similarity_array[$student_id] = array();
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        @$dom->loadHTMLFile($filename);

        $codes = $dom->getElementsByTagName('pre');
        foreach ($codes as $code) {

            // save the code first
            $code_name = $this->register_code(dirname($filename), $student_id, $file_array, $code);
            if (!isset($similarity_array[$student_id][$code_name])) {
                $similarity_array[$student_id][$code_name] = array();
            }

            $line_number = 1;
            // filter all anchor name: <a name="..."></a>
            $anchors = $code->getElementsByTagName('a');
            $anchor_names = array();
            foreach ($anchors as $anchor) {
                $name = $anchor->getAttribute('name');
                if ($name!='') {
                    $anchor_names[] = $name;
                }
            }

            $char_number = 0;
            $char_number_2 = 0;
            $child_nodes = $code->childNodes;
            $font_num = 0;
            foreach ($child_nodes as $node) {
                if ($node->nodeType==XML_TEXT_NODE) {
                    list($lines, $chars) = count_line($node->nodeValue);
                    $line_number += $lines;
                    if ($lines==0) { // start of another block is on the same line
                        $char_number += $char_number_2 + $chars;
                    } else {
                        $char_number = $chars;
                    }
                } else if ($node->nodeType==XML_ELEMENT_NODE) {
                    $tag = $node->tagName;
                    if ($tag=='font') {
                        list($line_number_2, $char_number_2) = $this->process_font_node($node);
                        $line_number_2 += $line_number;
                        if ($line_number_2==$line_number) { // start and end block is on the same line
                            $char_number_2 += $char_number;
                        }
                        $anchor_name = $anchor_names[$font_num];
                        $color = substr($node->getAttribute('color'), 1); // strip the '#' sign at the beginning

                        $similarity_array[$student_id][$code_name][] =
                            array('begin_line'=>$line_number,
                                'begin_char'=>$char_number,
                                'end_line'=>$line_number_2,
                                'end_char'=>$char_number_2,
                                'color'=>$color,
                                'student'=>$other_student_id,
                                'anchor'=>$anchor_name);

                        $line_number = $line_number_2;
                        $font_num++;
                    }
                }
            }
            $line_number = 1;
        }
        return $similarity_array;
    }

    private function process_font_node($node) {
        assert($node->tagName=='font');
        $text = $node->childNodes->item(1)->nodeValue;
        list($lines_num, $char_num) = count_line($text);
        return array($lines_num, $char_num);
    }

    private function register_code($directory, $student_id, &$file_array, $code) {
        if (!isset($file_array[$student_id])) {
            $file_array[$student_id] = array();
        }

        $header = $code->previousSibling;
        while ($header->nodeType!=XML_ELEMENT_NODE || $header->tagName!='h3') {
            $header = $header->previousSibling;
        }
        $code_name = $header->nodeValue;

        // if this code is not recorded yet, record it to disk (avoid holding too much in memory)
        if (!isset($file_array[$student_id][$code_name])) {
            $filename = tempnam($directory, $student_id);
            $file_array[$student_id][$code_name]= $filename;
            file_put_contents($filename, htmlspecialchars($code->nodeValue));
        }
        return $code_name;
    }

    // all the code files of each student are concatenated and saved in one file
    private function save_code(&$file_array, &$record_array, $directory) {
        foreach ($file_array as $student_id => $code) {
            $file = fopen($directory.'/'.$student_id, 'w');
            foreach ($code as $code_name => $filename) {
                $code_name = htmlspecialchars($code_name);
                fwrite($file, "<hr/><h3>$code_name</h3><hr/>\n");
                $content = file_get_contents($filename);
                $this->mark_similarity($content, $record_array[$student_id][$code_name]);
                fwrite($file, $content);
                unlink($filename);
            }
            fclose($file);
        }
    }

    private function mark_similarity(&$content, $similarities) {

        $this->merge_similar_portions($similarities);
        $this->split_and_sort($similarities);

        $lines = explode("\n", $content);

        // mark in the reverse order so that it does not affect the char count
        // if two marks are on the same line
        foreach ($similarities as $position) {
            $anchor = implode(',', $position['anchor']);
            $student_id = implode(',', $position['student']);
            $color = implode(',', $position['color']);
            $type = $position['type'];
            $line = $lines[$position['line']-1];
            $line = substr($line, 0, $position['char']-1)
                    ."<span sid='$student_id' anchor='$anchor' type='$type' color='$color'></span>"
                    .substr($line, $position['char']-1);
            $lines[$position['line']-1] = $line;
        }
        $content = implode("\n", $lines);
    }

    private function merge_similar_portions(&$similarities) {
        $num = count($similarities);
        for ($i=0; $i<$num; $i++) {
            if (!isset($similarities[$i])) {
                continue;
            }
            $first = $similarities[$i];
            $first['student'] = array($first['student']);
            $first['anchor'] = array($first['anchor']);
            $first['color'] = array($first['color']);
            for ($j=$i+1; $j<$num; $j++) {
                if (!isset($similarities[$j])) {
                    continue;
                }
                $second = $similarities[$j];
                if ($first['begin_line']==$second['begin_line'] &&
                    $first['begin_char']==$second['begin_char'] &&
                    $first['end_line'] == $second['end_line'] &&
                    $first['end_char'] == $second['end_char'] ) {
                        unset($similarities[$j]);
                        $first['student'][] = $second['student'];
                        $first['anchor'][] = $second['anchor'];
                        $first['color'][] = $second['color'];
                }
            }
            $similarities[$i] = $first;
        }
    }

    public function split_and_sort(&$similarities) {
        $splited_positions = array();
        foreach ($similarities as $portion) {
            $splited_positions[] = array(
                'line' => $portion['begin_line'],
                'char' => $portion['begin_char'],
                'student' =>$portion['student'],
                'anchor' => $portion['anchor'],
                'color' => $portion['color'],
                'type' => 'begin'
            );
            $splited_positions[] = array(
                'line' => $portion['end_line'],
                'char' => $portion['end_char'],
                'student' =>$portion['student'],
                'anchor' => $portion['anchor'],
                'color' => $portion['color'],
                'type' => 'end'
            );
        }
        usort($splited_positions, array('jplag_parser', 'position_sorter'));
        $similarities = $splited_positions;
    }

    public static function position_sorter($p1, $p2) {
        if ($p1['line'] != $p2['line']) {
            return $p2['line'] - $p1['line'];
        } else {
            return $p2['char'] - $p1['char'];
        }
    }
}