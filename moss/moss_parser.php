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

class moss_parser {
    
    private $cmid;
    private $filename;
    
    public function __construct($cmid) {
        $this->cmid = $cmid;
        $tool = new moss_tool();
        $this->filename = $tool->get_report_path($cmid).'/index.html';
        assert(is_file($this->filename));
    }
    
    public function parse() {
        global $DB;
        
        // delete the old records (in case this tool is run several times on one assignment)
        $DB->delete_records('programming_result',array('cmid'=>$this->cmid,'detector'=>'moss'));
        
        $content = file_get_contents($this->filename);
        // this pattern extract the link
        $pattern = '/<A HREF=\"(match[0-9]*\.html)\">([0-9]*)\/\s\(([0-9]*)%\)<\/A>/';
        preg_match_all($pattern, $content, $matches);
        $filenames = $matches[1];
        $studentids = $matches[2];
        $similarity = $matches[3];
        $num = count($filenames);

        $record = new stdClass();
        $record->detector = 'moss';
        $record->cmid = $this->cmid;
        for ($i=0; $i<$num; $i+=2) {
            $record->student1_id = $studentids[$i];
            $record->student2_id = $studentids[$i+1];
            $record->similarity1 = $similarity[$i];
            $record->similarity2 = $similarity[$i+1];
            $record->comparison = $filenames[$i];
            $DB->insert_record('programming_result',$record);
        }
        
        $this->get_similar_parts();
    }
    
    public function get_similar_parts() {
        global $DB;
        $pairs = $DB->get_records('programming_result',array('cmid'=>$this->cmid,'detector'=>'moss'));
        $path = dirname($this->filename);

        $similarity_array = array();
        $file_array = array();

        foreach ($pairs as $pair) {
            $file = $pair->comparison;
            $file_0 = $path.'/'.substr($file,0,-5).'-0.html';
            $file_1 = $path.'/'.substr($file,0,-5).'-1.html';
            $file = $path.'/'.$file;
            
            $this->parse_similar_parts($pair->student1_id,$pair->student2_id,$file_0,$similarity_array,$file_array);
            $this->parse_similar_parts($pair->student2_id,$pair->student1_id,$file_1,$similarity_array,$file_array);
            
            // TODO: uncomment to delete these files after debugging
            //unlink($file);
            //unlink($file_0);
            //unlink($file_1);
        }
        $this->save_similarity($similarity_array);
    }

    private function parse_similar_parts($student_id,$other_student_id,$filename,&$similarity_array,&$file_array) {
        
        if ($student_id==17 && $other_student_id==34) {
            echo 'Student';
        }
        
        if (!isset($similarity_array[$student_id])) {
            $similarity_array[$student_id] = array();
        }
        
        /* Since the whole code (every file) is encapsulated in only one pre tag (not like JPlag in many)
         * we can save it immediately
         * Another difference is that each similarity block in MOSS always span the whole lines (it never starts and end at the middle of a line)
         * Therefore, we don't need to use dom parser here
         */

        $this->save_code_file($filename, $student_id);
        $comparison_file = fopen($filename, 'r');
        // We extract the pre block first, bypassing lines not ending with <PRE>
        $line = '';
        do {
            $line = trim(fgets($comparison_file));
        } while (substr($line, -5)!='<PRE>');
        
        // file to save the code (only the code inside pre tag)
        $code_file_name = dirname($filename).'/'.$student_id;
        
        // pattern to extract the start of similarity block
        
        $line_no = 1;
        $line=trim(fgets($comparison_file));
        while (substr($line,-6)!='</PRE>') {
            
            $start_block=$this->is_start_block($line);
            if ($start_block) { // start block
                $anchor = $start_block[0];
                $color = $start_block[1];
                assert(trim(fgets($comparison_file))==''); // one blank line after the start block
                
                // seek the end block line
                $num_line = 0;
                $line = fgets($comparison_file);
                while (!$this->is_end_block($line)) {
                    $num_line++;
                    $line = fgets($comparison_file);
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
        fclose($comparison_file);
    }
    
    private function save_code_file($filename,$student_id) {
        static $file_array = array();
        if (isset($file_array[$student_id])) {
            return;
        }
        // if not saved!
        $comparison_file = fopen($filename, 'r');
        $code_file = fopen(dirname($this->filename).'/'.$student_id, 'w');
        
        // skip all the html header lines
        do {
            $line = rtrim(fgets($comparison_file));
        } while (substr($line, -5)!='<PRE>');
        
        // write the code lines to the code file
        $line = rtrim(fgets($comparison_file));
        while (substr($line, -6)!='</PRE>' || $line===FALSE) {
            if ($this->is_start_block($line)) { // start of a block, skip this line
                fgets($comparison_file); // skip another blank line
            } elseif ($this->is_end_block($line)) {
                fwrite($code_file, substr($line, 7)); //skip the </FONT> tag
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

    private function is_start_block($line) {
        static $pattern = '/<A NAME=\"([0-9]+)\"><\/A><FONT color = #([0-9A-F]+)>/';
        $match_num = preg_match($pattern, $line, $match);
        if ($match_num>0) {
            return array($match[1],$match[2]);
        } else {
            return FALSE;
        }
    }
    
    private function is_end_block($line) {
        return substr($line, 0, 7)=='</FONT>';
    }
    
    private function save_similarity(&$similarity_array) {
        $directory = dirname($this->filename);
        
        foreach ($similarity_array as $student_id=>$similar_blocks) {
            $this->merge_and_sort_blocks($similar_blocks);
            
            $filename = $directory.'/'.$student_id;
            
            $content = file_get_contents($filename);
            $this->mark_similarities($content, $similar_blocks);
            file_put_contents($filename, $content);
        }
    }
    
    private function merge_and_sort_blocks(&$similarities) {
        $num = count($similarities);
        
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
        usort($merged_array, array('moss_parser','position_compare'));
        $similarities = $merged_array;
    }
    
    static function position_compare($p1,$p2) {
        return $p2['begin_line'] - $p1['begin_line'];
    }
    
    private function mark_similarities(&$content,&$blocks) {
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
