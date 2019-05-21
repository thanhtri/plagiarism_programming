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
 * This class parse the result of the generated webpages of JPlag.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use Leafo\ScssPhp\Node\Number;
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

require_once(__DIR__ . '/../utils.php');

/**
 * Class for the jplag-parser.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class jplag_parser{
    /**
     * @var String $filename
     */
    private $filename;
    /**
     * @var Number $cmid
     */
    private $cmid;
    /**
     * @var Object $report
     */
    private $report;

    /**
     * Initializes the variables.
     *
     * @param Number $cmid
     */
    public function __construct($cmid) {
        $this->cmid = $cmid;
        $this->report = plagiarism_programming_get_latest_report($cmid, 'jplag');
        $this->filename = jplag_tool::get_report_path($this->report) . '/index.html';
    }

    /**
     * Parses the input to the jplag format, I guess.
     */
    public function parse() {
        global $DB;

        $directory = dirname($this->filename);

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        if (!@$dom->loadHTMLFile($this->filename)) {
            trigger_error('Loading index.html file failed!', E_USER_ERROR);
        }

        // Extract table elements, only the third and forth tables contain the required info.
        $tables = $dom->getElementsByTagName('table');
        $averagetable = $tables->item(2);
        $rows = $averagetable->getElementsByTagName('tr');
        $rownum = $rows->length;

        $res = new stdClass();
        $res->reportid = $this->report->id;
        for ($i = 0; $i < $rownum; $i++) {
            $row = $rows->item($i);
            $cells = $row->getElementsByTagName('td');

            for ($j = 2; $j < $cells->length; $j++) {
                $cell = $cells->item($j);
                $link = $cell->childNodes->item(0);
                $file = $link->getAttribute('href');

                // The similarity percentage of each student is contained in the -top file.
                $pattern = '/<TR><TH><TH>(.*) \(([0-9]*\.[0-9]*)%\)<TH>(.*) \(([0-9]*\.[0-9]*)%\)<TH>/';
                $topfilename = $directory . '/' . substr($file, 0, -5) . '-top.html';
                $topcontent = file_get_contents($topfilename);
                $matches = null;
                preg_match($pattern, $topcontent, $matches);

                // Save to the db.
                if (ctype_digit($matches[1]) || ctype_digit($matches[3])) {
                    $res->student1_id = $matches[1];
                    $res->student2_id = $matches[3];
                    $res->similarity1 = $matches[2];
                    $res->similarity2 = $matches[4];
                    $res->comparison = $file;
                    plagiarism_programming_save_similarity_pair($res);
                }
            }
        }
        $this->get_similar_parts();
    }

    /**
     * Gets the similar parts of two submissions.
     */
    public function get_similar_parts() {
        global $DB;
        $pairs = $DB->get_records('plagiarism_programming_reslt', array(
            'reportid' => $this->report->id
        ));
        $pairs = plagiarism_programming_transform_similarity_pair($pairs);
        $path = dirname($this->filename);

        $similarityarray = array();
        $filearray = array();

        foreach ($pairs as $pair) {
            $file = $pair->comparison;
            $file0 = $path . '/' . substr($file, 0, -5) . '-0.html';
            $file1 = $path . '/' . substr($file, 0, -5) . '-1.html';
            $file = $path . '/' . $file;

            $this->parse_similar_parts($pair->student1_id, $pair->student2_id, $file0, $similarityarray, $filearray);
            $this->parse_similar_parts($pair->student2_id, $pair->student1_id, $file1, $similarityarray, $filearray);

            // TODO Uncomment to delete these files after debugging.
            if (!debugging()) {
                unlink($file);
                unlink($file0);
                unlink($file1);
            }
        }
        $this->save_code($filearray, $similarityarray, $path);
    }

    /**
     * Parses the parts which are similar.
     *
     * @param Number $studentid
     * @param Number $otherstudentid
     * @param String $filename
     * @param Array $similarityarray
     * @param Array $filearray
     * @return Array with similarities
     */
    private function parse_similar_parts($studentid, $otherstudentid, $filename, &$similarityarray, &$filearray) {
        if (!isset($similarityarray[$studentid])) {
            $similarityarray[$studentid] = array();
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        @$dom->loadHTMLFile($filename);

        $codes = $dom->getElementsByTagName('pre');
        foreach ($codes as $code) {

            // Save the code first.
            $codename = $this->register_code(dirname($filename), $studentid, $filearray, $code);
            if (!isset($similarityarray[$studentid][$codename])) {
                $similarityarray[$studentid][$codename] = array();
            }

            $linenumber = 1;
            // Filter all anchor name: <a name="..."></a>.
            $anchors = $code->getElementsByTagName('a');
            $anchornames = array();
            foreach ($anchors as $anchor) {
                $name = $anchor->getAttribute('name');
                if ($name != '') {
                    $anchornames[] = $name;
                }
            }

            $charnumber = 0;
            $charnumber2 = 0;
            $childnodes = $code->childNodes;
            $fontnumber = 0;
            foreach ($childnodes as $node) {
                if ($node->nodeType == XML_TEXT_NODE) {
                    list ($lines, $chars) = plagiarism_programming_count_line($node->nodeValue);
                    $linenumber += $lines;
                    if ($lines == 0) { // Start of another block is on the same line.
                        $charnumber += $charnumber2 + $chars;
                    } else {
                        $charnumber = $chars;
                    }
                } else if ($node->nodeType == XML_ELEMENT_NODE) {
                    $tag = $node->tagName;
                    if ($tag == 'font') {
                        list ($linenumber2, $charnumber2) = $this->process_font_node($node);
                        $linenumber2 += $linenumber;
                        if ($linenumber2 == $linenumber) { // Start and end block is on the same line.
                            $charnumber2 += $charnumber;
                        }
                        $anchorname = $anchornames[$fontnumber];
                        $color = substr($node->getAttribute('color'), 1); // Strip the '#' sign at the beginning.

                        $similarityarray[$studentid][$codename][] = array(
                            'begin_line' => $linenumber,
                            'begin_char' => $charnumber,
                            'end_line' => $linenumber2,
                            'end_char' => $charnumber2,
                            'color' => $color,
                            'student' => $otherstudentid,
                            'anchor' => $anchorname
                        );

                        $linenumber = $linenumber2;
                        $fontnumber++;
                    }
                }
            }
            $linenumber = 1;
        }
        return $similarityarray;
    }

    /**
     * Processes the font node.
     *
     * @param object $node
     * @return Array
     */
    private function process_font_node($node) {
        assert($node->tagName == 'font');
        $text = $node->childNodes->item(1)->nodeValue;
        list ($linesnum, $charnum) = plagiarism_programming_count_line($text);
        return array(
            $linesnum,
            $charnum
        );
    }

    /**
     * Registers the code.
     *
     * @param String $directory
     * @param Number $studentid
     * @param Array $filearray
     * @param object $code
     * @return String
     */
    private function register_code($directory, $studentid, &$filearray, $code) {
        if (!isset($filearray[$studentid])) {
            $filearray[$studentid] = array();
        }

        $header = $code->previousSibling;
        while ($header->nodeType != XML_ELEMENT_NODE || $header->tagName != 'h3') {
            $header = $header->previousSibling;
        }
        $codename = $header->nodeValue;

        // If this code is not recorded yet, record it to disk (avoid holding too much in memory).
        if (!isset($filearray[$studentid][$codename])) {
            $filename = tempnam($directory, str_replace(' ', '_', "$studentid"));
            $filearray[$studentid][$codename] = $filename;
            file_put_contents($filename, htmlspecialchars($code->nodeValue));
        }
        return $codename;
    }

    /**
     * All the code files of each student are concatenated and saved in one file.
     *
     * @param Array $filearray
     * @param Array $recordarray
     * @param String $directory
     */
    private function save_code(&$filearray, &$recordarray, $directory) {
        foreach ($filearray as $studentid => $code) {
            $file = fopen($directory . '/' . $studentid, 'w');
            foreach ($code as $codename => $filename) {
                $htmlcodename = htmlspecialchars($codename);
                fwrite($file, "<hr/><h3>$htmlcodename</h3><hr/>\n");
                $content = file_get_contents($filename);
                $this->mark_similarity($content, $recordarray[$studentid][$codename]);
                fwrite($file, $content);
                unlink($filename);
            }
            fclose($file);
        }
    }

    /**
     * Marks the similarity of two submissions
     *
     * @param String $content
     * @param object $similarities
     */
    private function mark_similarity(&$content, $similarities) {
        $this->merge_similar_portions($similarities);
        $this->split_and_sort($similarities);

        $lines = explode("\n", $content);

        // Mark in the reverse order so that it does not affect the char count if two marks are on the same line.
        foreach ($similarities as $position) {
            $anchor = implode(',', $position['anchor']);
            $studentid = implode(',', $position['student']);
            $color = implode(',', $position['color']);
            $type = $position['type'];
            $line = $lines[$position['line'] - 1];
            $line = substr($line, 0, $position['char'] - 1)."<span sid='$studentid' anchor='$anchor' type='$type' color='$color'></span>".substr($line, $position['char'] - 1);
            $lines[$position['line'] - 1] = $line;
        }
        $content = implode("\n", $lines);
    }

    /**
     * Merges similar portions into one.
     *
     * @param object $similarities
     */
    private function merge_similar_portions(&$similarities) {
        $num = count($similarities);
        for ($i = 0; $i < $num; $i++) {
            if (!isset($similarities[$i])) {
                continue;
            }
            $first = $similarities[$i];
            $first['student'] = array(
                $first['student']
            );
            $first['anchor'] = array(
                $first['anchor']
            );
            $first['color'] = array(
                $first['color']
            );
            for ($j = $i + 1; $j < $num; $j++) {
                if (!isset($similarities[$j])) {
                    continue;
                }
                $second = $similarities[$j];
                if ($first['begin_line'] == $second['begin_line'] && $first['begin_char'] == $second['begin_char'] && $first['end_line'] == $second['end_line'] && $first['end_char'] == $second['end_char']) {
                    unset($similarities[$j]);
                    $first['student'][] = $second['student'];
                    $first['anchor'][] = $second['anchor'];
                    $first['color'][] = $second['color'];
                }
            }
            $similarities[$i] = $first;
        }
    }

    /**
     * Splits and then sorts similarities for further processing.
     *
     * @param object $similarities
     */
    public function split_and_sort(&$similarities) {
        $splitpositions = array();
        foreach ($similarities as $portion) {
            $splitpositions[] = array(
                'line' => $portion['begin_line'],
                'char' => $portion['begin_char'],
                'student' => $portion['student'],
                'anchor' => $portion['anchor'],
                'color' => $portion['color'],
                'type' => 'begin'
            );
            $splitpositions[] = array(
                'line' => $portion['end_line'],
                'char' => $portion['end_char'],
                'student' => $portion['student'],
                'anchor' => $portion['anchor'],
                'color' => $portion['color'],
                'type' => 'end'
            );
        }
        usort($splitpositions, array(
            'jplag_parser',
            'position_sorter'
        ));
        $similarities = $splitpositions;
    }

    /**
     * Sorts two positions.
     *
     * @param object $p1
     * @param object $p2
     * @return number
     */
    public static function position_sorter($p1, $p2) {
        if ($p1['line'] != $p2['line']) {
            return $p2['line'] - $p1['line'];
        } else {
            return $p2['char'] - $p1['char'];
        }
    }
}