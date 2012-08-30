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
 * This class parse the result of the generated webpages of MOSS
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

class moss_parser {

    private $cmid;
    private $filename;
    private $report;

    /**
     * Create a moss_parser object correspond to an assignment
     * @param int $cmid: course module id of the assignment
     */
    public function __construct($cmid) {
        $this->cmid = $cmid;
        $this->report = get_latest_report($cmid, 'moss');
        $this->filename = moss_tool::get_report_path($this->report).'/index.html';
        assert(is_file($this->filename));
    }

    /**
     * Parse the index file of the report and populate the record in plagiarism_programming_reslt table
     */
    public function parse() {
        global $DB;

        $content = file_get_contents($this->filename);
        // this pattern extract the link
        $pattern = '/<A HREF=\"(match[0-9]*\.html)\">([0-9]*)\/\s\(([0-9]*)%\)<\/A>/';
        $matches = null;
        preg_match_all($pattern, $content, $matches);
        $filenames = $matches[1];
        $studentids = $matches[2];
        $similarity = $matches[3];
        $num = count($filenames);

        $record = new stdClass();
        $record->reportid = $this->report->id;
        for ($i=0; $i<$num; $i+=2) {
            $record->student1_id = $studentids[$i];
            $record->student2_id = $studentids[$i+1];
            $record->similarity1 = $similarity[$i];
            $record->similarity2 = $similarity[$i+1];
            $record->comparison = $filenames[$i];
            $DB->insert_record('plagiarism_programming_reslt', $record);
        }

        $this->get_similar_parts();
    }

    /**
     * Extract the similarities of each student with all the others. This function will read through all comparison files
     * in the report, extract the marked similar blocks and produce the marked file.
     * 
     * Input: the similarity report in the report directory (dataroot/plagiarism_report/moss<cmid>/* and the record in
     * plagiarism_programming_reslt table
     * Output: output one file for each student, with the file name is the student id, which include all the codes
     * concatenated into one file, and mark the similarity blocks with <span> tags. The begining of the similarity blocks
     * will be marked by <span type='begin' sid='studentids' color=''> (note that one block can be similar to many other students)
     */
    public function get_similar_parts() {
        global $DB;
        $pairs = $DB->get_records('plagiarism_programming_reslt', array('reportid'=>$this->report->id));
        $path = dirname($this->filename);

        $similarity_array = array();

        foreach ($pairs as $pair) {
            $file = $pair->comparison;
            $file_0 = $path.'/'.substr($file, 0, -5).'-0.html';
            $file_1 = $path.'/'.substr($file, 0, -5).'-1.html';
            $file = $path.'/'.$file;

            $this->parse_similar_parts($pair->student1_id, $pair->student2_id, $file_0, $similarity_array);
            $this->parse_similar_parts($pair->student2_id, $pair->student1_id, $file_1, $similarity_array);

            // TODO: uncomment to delete these files after debugging
            if (!debugging()) {
                unlink($file_0);
                unlink($file_1);
            }
        }
        $this->save_similarity($similarity_array);
    }

    /**
     * This function extract the similar blocks of one student with another. The block is recorded in the $similarity_array
     * passed into the function.
     * @param $student_id: id of the student whose similarity blocks with another is going to be extracted
     * @param $other_student_id: id of the other student
     * @param $filename: the comparison file of the report of the pair
     * @param $similarity_array: contain the recorded blocks and blocks that will be recorded in this call,
     *        which is a multidimensional array $similarity_array[$student][0,1...] = array('begin_line'=>?,'end_line'=>?,
     *        'student'=>?,'color'=>?,'anchor'=>?)
     */
    private function parse_similar_parts($student_id, $other_student_id, $filename, &$similarity_array) {

        if (!isset($similarity_array[$student_id])) {
            $similarity_array[$student_id] = array();
        }

        /* Since the whole code (every file) is encapsulated in only one pre tag (not like JPlag in many)
         * we can save it immediately
         * Another difference is that each similarity block in MOSS always span the whole lines (it never starts and end at the
         * middle of a line). Therefore, we don't need to use dom parser here
         */

        if (!is_file($filename)) {
            trigger_error("File $filename does not exist", E_USER_ERROR);
        }

        $this->save_code_file($filename, $student_id);
        $comparison_file = fopen($filename, 'r');
        if (!$comparison_file) {
            trigger_error("Cannot open file $filename", E_USER_ERROR);
        }

        // We extract the pre block first, bypassing lines not ending with <PRE>
        $line = '';
        do {
            $line = fgets($comparison_file);
        } while ($line!=='' && substr(trim($line), -5)!='<PRE>');

        if (feof($comparison_file)) {
            trigger_error("File $filename corrupted", E_USER_ERROR);
        }

        $line_no = 1;
        $line=trim(fgets($comparison_file));
        // loop until end of pre-block encountered
        while (!feof($comparison_file) && substr($line, -6)!='</PRE>') {

            $start_block=$this->is_start_block($line);
            if ($start_block) { // start a similarity block
                $anchor = $start_block[0];
                $color = $start_block[1];
                assert(trim(fgets($comparison_file))==''); // swallow one blank line after the start block

                // seek the end block line
                $num_line = 0;
                $line = fgets($comparison_file);
                while (!feof($comparison_file) && !$this->is_end_block($line)) { //go until the end of this similarity block
                    $num_line++;
                    $line = fgets($comparison_file);
                }

                if (feof($comparison_file)) {
                    trigger_error("File $filename corrupted", E_USER_ERROR);
                }

                $similarity_array[$student_id][] = array(
                    'begin_line' => $line_no,
                    'end_line' => $line_no+$num_line,
                    'student' => $other_student_id,
                    'color' => $color,
                    'anchor' => $anchor
                );
                $line_no += $num_line; // include the additional line at the end line

                // since a line end block may also start another block. If such a line encountered, don't read another line
                if (!$this->is_start_block($line)) {
                    $line=trim(fgets($comparison_file));
                    $line_no++;
                }
            } else { // not a start of a block line - read another line
                $line=trim(fgets($comparison_file));
                $line_no++;
            }
        }

        if (substr($line, -6)!='</PRE>') {
            trigger_error("File $filename corrupted", E_USER_ERROR);
        }

        fclose($comparison_file);
    }

    /**
     * Save the code of a student into one file having studentid as filename. This function is called many times for one student
     * but the file will be saved only once.
     * @param $filename: the name of the file
     * @param $student_id: id of the file
     */
    private function save_code_file($filename, $student_id) {
        static $file_array = array();
        if (isset($file_array[$student_id])) { // already saved?
            return;
        }
        // if not saved!
        $comparison_file = fopen($filename, 'r');
        $code_file = fopen(dirname($this->filename).'/'.$student_id, 'w');

        // skip all the html header lines
        do {
            $line = rtrim(fgets($comparison_file));
        } while (!feof($comparison_file) && substr($line, -5)!='<PRE>');

        // write the code lines to the code file
        $line = rtrim(fgets($comparison_file));
        while (!feof($comparison_file) && substr($line, -6)!='</PRE>' || $line===false) {
            if ($this->is_start_block($line)) { // start of a block, skip this line
                fgets($comparison_file); // skip another blank line
            } else if ($this->is_end_block($line)) {
                fwrite($code_file, substr($line, 7)."\n"); //skip the </FONT> tag
            } else {
                fwrite($code_file, $line."\n");
            }
            $line = rtrim(fgets($comparison_file));
        }
        fwrite($code_file, substr($line, 0, -6));

        $file_array[$student_id] = true;
        fclose($comparison_file);
        fclose($code_file);
    }

    /**
     * Check to see the line is a start of a similarity block
     * @param $line: the line to be checked
     * @return array(anchor,color) if it starts a similarity block (we need anchor to match it with the similar block of
     * another student) or false if not
     */
    private function is_start_block($line) {
        static $pattern = '/<A NAME=\"([0-9]+)\"><\/A><FONT color = #([0-9A-F]+)>/';
        $match = null;
        $match_num = preg_match($pattern, $line, $match);
        if ($match_num>0) {
            return array($match[1], $match[2]);
        } else {
            return false;
        }
    }

    /**
     * Check if the line is the end of a similarity block
     * @param $line: the line to be checked
     * @return true if the line is the end of a similarity block, otherwise false
     */
    private function is_end_block($line) {
        return substr($line, 0, 7)=='</FONT>';
    }

    /**
     * Mark the code file with the similarities for all students
     * @param $similarity_array: array of blocks output by parse_similar_parts function
     */
    private function save_similarity(&$similarity_array) {
        $directory = dirname($this->filename);

        foreach ($similarity_array as $student_id => $similar_blocks) {
            $this->merge_and_sort_blocks($similar_blocks);

            $filename = $directory.'/'.$student_id;

            $content = file_get_contents($filename);
            $this->mark_similarities($content, $similar_blocks);
            file_put_contents($filename, $content);
        }
    }

    /**
     * Merge the same block together, since one block can be similar to many students,
     * then sorted from the end of file to the beginning (block at the end will appear first)
     * @param $similarities: the array of blocks of just one student
     */
    private function merge_and_sort_blocks(&$similarities) {
        $merged_array = array(); // this is used as a hash table: (begin_line.end_line)=>similarity info
        foreach ($similarities as $block) {
            $key = $block['begin_line'].'.'.$block['end_line'];
            if (!isset($merged_array[$key])) {
                $block['student'] = array($block['student']);
                $block['anchor']  = array($block['anchor']);
                $block['color']   = array($block['color']);

                $merged_array[$key] = $block;
            } else {
                $merged_array[$key]['student'][] = $block['student'];
                $merged_array[$key]['anchor'][] = $block['anchor'];
                $merged_array[$key]['color'][] = $block['color'];
            }
        }
        usort($merged_array, array('moss_parser', 'position_compare'));
        $similarities = $merged_array;
    }

    /** Compare the position of two blocks*/
    public static function position_compare($p1, $p2) {
        return $p2['begin_line'] - $p1['begin_line'];
    }

    /**
     * Mark the blocks by <span> tag. Each block will be marked by two <span/>
     * one at the beginning (<span type='begin'/> and another at the end  <span type='begin'/>
     * Each <span> has student id and color in the sid and color attribute
     * @param $content: the content of the code file (saved by the save_code function)
     * @param $blocks: the array of blocks of one student
     */
    private function mark_similarities(&$content, &$blocks) {
        $lines = explode("\n", $content);

        foreach ($blocks as $block) {
            $anchor = implode(',', $block['anchor']);
            $student = implode(',', $block['student']);
            $color = implode(',', $block['color']);

            $lines[$block['begin_line']-1] =
                "<span sid='$student' anchor='$anchor' type='begin' color='$color'></span>".$lines[$block['begin_line']-1];
            $lines[$block['end_line']-1] =
                "<span sid='$student' anchor='$anchor' type='end' color='$color'></span>".$lines[$block['end_line']-1];
        }

        $content = implode("\n", $lines);
    }

}
