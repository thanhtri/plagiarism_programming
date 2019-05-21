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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Leafo\ScssPhp\Node\Number;
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

/**
 * Class for the moss parser.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moss_parser {

    /**
     * @var $cmid Number Course module id.
     */
    private $cmid;
    /**
     * @var $filename string Name of the file.
     */
    private $filename;
    /**
     * @var $report object Latest Scan for plagiarism.
     */
    private $report;

    /**
     * Creates a moss_parser object correspond to an assignment.
     * @param Number $cmid course module id of the assignment
     */
    public function __construct($cmid) {
        $this->cmid = $cmid;
        $this->report = plagiarism_programming_get_latest_report($cmid, 'moss');
        $this->filename = moss_tool::get_report_path($this->report).'/index.html';
        assert(is_file($this->filename));
    }

    /**
     * Parses the index file of the report and populate the record in plagiarism_programming_reslt table.
     */
    public function parse() {
        global $DB;

        $content = file_get_contents($this->filename);
        // This pattern extract the link.
        $pattern = '/<A HREF=\"(match[0-9]*\.html)\">([^\/]*)\/\s\(([0-9]*)%\)<\/A>/';
        $matches = null;
        preg_match_all($pattern, $content, $matches);
        $filenames = $matches[1];
        $studentids = $matches[2];
        $similarity = $matches[3];
        $num = count($filenames);

        $record = new stdClass();
        $record->reportid = $this->report->id;
        for ($i = 0; $i < $num; $i += 2) {
            // We only need to save pairs in which there is at least one real student.
            if (ctype_digit($studentids[$i]) || ctype_digit($studentids[$i + 1])) {
                $record->student1_id = $studentids[$i];
                $record->student2_id = $studentids[$i + 1];
                $record->similarity1 = $similarity[$i];
                $record->similarity2 = $similarity[$i + 1];
                $record->comparison = $filenames[$i];
                plagiarism_programming_save_similarity_pair($record);
            }
        }

        $this->get_similar_parts();
    }

    /**
     * Extract the similarities of each student with all the others. This function will read through all comparison files
     * in the report, extract the marked similar blocks and produce the marked file.
     *
     * Input: the similarity report in the report directory (dataroot/temp/plagiarism_report/moss<cmid>/* and the record in
     * plagiarism_programming_reslt table
     * Output: output one file for each student, with the file name is the student id, which include all the codes
     * concatenated into one file, and mark the similarity blocks with <span> tags. The begining of the similarity blocks
     * will be marked by <span type='begin' sid='studentids' color=''> (note that one block can be similar to many other students)
     */
    public function get_similar_parts() {
        global $DB;
        $pairs = $DB->get_records('plagiarism_programming_reslt', array('reportid' => $this->report->id));
        // Uniformise the pairs where 1 is external code.
        $pairs = plagiarism_programming_transform_similarity_pair($pairs);
        $path = dirname($this->filename);

        $similarityarray = array();

        foreach ($pairs as $pair) {
            $file = $pair->comparison;
            $file0 = $path.'/'.substr($file, 0, -5).'-0.html';
            $file1 = $path.'/'.substr($file, 0, -5).'-1.html';
            $file = $path.'/'.$file;

            $this->parse_similar_parts($pair->student1_id, $pair->student2_id, $file0, $similarityarray);
            $this->parse_similar_parts($pair->student2_id, $pair->student1_id, $file1, $similarityarray);

            // TODO: Uncomment to delete these files after debugging.
            if (!debugging()) {
                unlink($file0);
                unlink($file1);
            }
        }
        $this->save_similarity($similarityarray);
    }

    /**
     * This function extract the similar blocks of one student with another. The block is recorded in the $similarity_array
     * passed into the function.
     * @param Number $studentid id of the student whose similarity blocks with another is going to be extracted
     * @param Number $otherstudentid id of the other student
     * @param String $filename the comparison file of the report of the pair
     * @param Array $similarityarray contain the recorded blocks and blocks that will be recorded in this call,
     *        which is a multidimensional array $similarity_array[$student][0,1...] = array('begin_line'=>?,'end_line'=>?,
     *        'student'=>?,'color'=>?,'anchor'=>?)
     */
    private function parse_similar_parts($studentid, $otherstudentid, $filename, &$similarityarray) {

        if (!isset($similarityarray[$studentid])) {
            $similarityarray[$studentid] = array();
        }

        /* Since the whole code (every file) is encapsulated in only one pre tag (not like JPlag in many)
         * we can save it immediately
         * Another difference is that each similarity block in MOSS always span the whole lines (it never starts and end at the
         * middle of a line). Therefore, we don't need to use dom parser here
         */

        if (!is_file($filename)) {
            trigger_error("File $filename does not exist", E_USER_ERROR);
        }

        $this->save_code_file($filename, $studentid);
        $comparisonfile = fopen($filename, 'r');
        if (!$comparisonfile) {
            trigger_error("Cannot open file $filename", E_USER_ERROR);
        }

        // We extract the pre block first, bypassing lines not ending with <PRE>.
        $line = '';
        do {
            $line = fgets($comparisonfile);
        } while ($line !== '' && substr(trim($line), -5) != '<PRE>');

        if (feof($comparisonfile)) {
            trigger_error("File $filename corrupted", E_USER_ERROR);
        }

        $linenumber = 1;
        $line = trim(fgets($comparisonfile));
        // Loop until end of pre-block encountered.
        while (!feof($comparisonfile) && substr($line, -6) != '</PRE>') {

            $startblock = $this->is_start_block($line);
            if ($startblock) { // Start a similarity block.
                $anchor = $startblock[0];
                $color = $startblock[1];
                assert(trim(fgets($comparisonfile)) == ''); // Swallow one blank line after the start block.

                // Seek the end block line.
                $numline = 0;
                $line = fgets($comparisonfile);
                while (!feof($comparisonfile) && !$this->is_end_block($line)) { // Go until the end of this similarity block.
                    $numline++;
                    $line = fgets($comparisonfile);
                }

                if (feof($comparisonfile)) {
                    trigger_error("File $filename corrupted", E_USER_ERROR);
                }

                $similarityarray[$studentid][] = array(
                    'begin_line' => $linenumber,
                    'end_line' => $linenumber + $numline,
                    'student' => $otherstudentid,
                    'color' => $color,
                    'anchor' => $anchor
                );
                $linenumber += $numline; // Include the additional line at the end line.

                // Since a line end block may also start another block. If such a line encountered, don't read another line.
                if (!$this->is_start_block($line)) {
                    $line = trim(fgets($comparisonfile));
                    $linenumber++;
                }
            } else { // Not a start of a block line - read another line.
                $line = trim(fgets($comparisonfile));
                $linenumber++;
            }
        }

        if (substr($line, -6) != '</PRE>') {
            trigger_error("File $filename corrupted", E_USER_ERROR);
        }

        fclose($comparisonfile);
    }

    /**
     * Save the code of a student into one file having studentid as filename. This function is called many times for one student
     * but the file will be saved only once.
     * @param String $filename the name of the file
     * @param Number $studentid id of the file
     */
    private function save_code_file($filename, $studentid) {
        static $filearray = array();
        if (isset($filearray[$studentid])) { // Already saved?
            return;
        }
        // If not saved!
        $comparisonfile = fopen($filename, 'r');
        $codefile = fopen(dirname($this->filename).'/'.$studentid, 'w');

        // Skip all the html header lines.
        do {
            $line = rtrim(fgets($comparisonfile));
        } while (!feof($comparisonfile) && substr($line, -5) != '<PRE>');

        // Write the code lines to the code file.
        $line = rtrim(fgets($comparisonfile));
        while (!feof($comparisonfile) && substr($line, -6) != '</PRE>' || $line === false) {
            if ($this->is_start_block($line)) { // Start of a block, skip this line.
                fgets($comparisonfile); // Skip another blank line.
            } else if ($this->is_end_block($line)) {
                fwrite($codefile, substr($line, 7)."\n"); // Skip the </FONT> tag.
            } else {
                fwrite($codefile, $line."\n");
            }
            $line = rtrim(fgets($comparisonfile));
        }
        fwrite($codefile, substr($line, 0, -6));

        $filearray[$studentid] = true;
        fclose($comparisonfile);
        fclose($codefile);
    }

    /**
     * Check to see the line is a start of a similarity block
     * @param Number $line The line to be checked
     * @return array(anchor,color) if it starts a similarity block (we need anchor to match it with the similar block of
     * another student) or false if not
     */
    private function is_start_block($line) {
        static $pattern = '/<A NAME=\"([0-9]+)\"><\/A><FONT color = #([0-9A-F]+)>/';
        $match = null;
        $matchnum = preg_match($pattern, $line, $match);
        if ($matchnum > 0) {
            return array($match[1], $match[2]);
        } else {
            return false;
        }
    }

    /**
     * Check if the line is the end of a similarity block
     * @param Number $line The line to be checked
     * @return true if the line is the end of a similarity block, otherwise false
     */
    private function is_end_block($line) {
        return substr($line, 0, 7) == '</FONT>';
    }

    /**
     * Mark the code file with the similarities for all students
     * @param Array $similarityarray Blocks output by parse_similar_parts function
     */
    private function save_similarity(&$similarityarray) {
        $directory = dirname($this->filename);

        foreach ($similarityarray as $studentid => $similarblocks) {
            $this->merge_and_sort_blocks($similarblocks);

            $filename = $directory.'/'.$studentid;

            $content = file_get_contents($filename);
            $this->mark_similarities($content, $similarblocks);
            file_put_contents($filename, $content);
        }
    }

    /**
     * Merge the same block together, since one block can be similar to many students,
     * then sorted from the end of file to the beginning (block at the end will appear first)
     * @param Array $similarities The array of blocks of just one student
     */
    private function merge_and_sort_blocks(&$similarities) {
        $mergedarray = array(); // This is used as a hash table: (begin_line.end_line)=>similarity info.
        foreach ($similarities as $block) {
            $key = $block['begin_line'].'.'.$block['end_line'];
            if (!isset($mergedarray[$key])) {
                $block['student'] = array($block['student']);
                $block['anchor']  = array($block['anchor']);
                $block['color']   = array($block['color']);

                $mergedarray[$key] = $block;
            } else {
                $mergedarray[$key]['student'][] = $block['student'];
                $mergedarray[$key]['anchor'][] = $block['anchor'];
                $mergedarray[$key]['color'][] = $block['color'];
            }
        }
        usort($mergedarray, array('moss_parser', 'position_compare'));
        $similarities = $mergedarray;
    }

    /**
     * Compare the position of two blocks
     * @param Object $p1
     * @param Object $p2
     * @return number
     */
    public static function position_compare($p1, $p2) {
        return $p2['begin_line'] - $p1['begin_line'];
    }

    /**
     * Mark the blocks by <span> tag. Each block will be marked by two <span/>
     * one at the beginning (<span type='begin'/> and another at the end  <span type='begin'/>
     * Each <span> has student id and color in the sid and color attribute
     * @param String $content The content of the code file (saved by the save_code function)
     * @param Array $blocks The array of blocks of one student
     */
    private function mark_similarities(&$content, &$blocks) {
        $lines = explode("\n", $content);

        foreach ($blocks as $block) {
            $anchor = implode(',', $block['anchor']);
            $student = implode(',', $block['student']);
            $color = implode(',', $block['color']);

            $lines[$block['begin_line'] - 1] =
                "<span sid='$student' anchor='$anchor' type='begin' color='$color'></span>".$lines[$block['begin_line'] - 1];
            $lines[$block['end_line'] - 1] =
                "<span sid='$student' anchor='$anchor' type='end' color='$color'></span>".$lines[$block['end_line'] - 1];
        }

        $content = implode("\n", $lines);
    }

}
