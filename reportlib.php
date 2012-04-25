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
 * Functions to generate the report
 * Provide the site-wide setting and specific configuration for each assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define ('CHART_WITH',800);
define ('CHART_HEIGHT',10);
define('BAR_WIDTH', 20);
function create_table_grouping_mode(&$list,&$student_names,$cmid) {
    global $CFG;
    
    $similarity_table = array();
    foreach ($list as $pair) {
        // make sure student1 id > student2 id to avoid repetition latter
        $student1 = max($pair->student1_id,$pair->student2_id);
        $student2 = min($pair->student1_id,$pair->student2_id);

        $similarity_table[$student1][$student2] = array('rate'=>$pair->similarity1,'file'=>$pair->comparison,'id'=>$pair->id,'mark'=>$pair->mark);
        $similarity_table[$student2][$student1] = array('rate'=>$pair->similarity1,'file'=>$pair->comparison,'id'=>$pair->id,'mark'=>$pair->mark);
    }
    
    $table = new html_table();
    $table->attributes['class']='plagiarism_programming_result_table';
    foreach ($similarity_table as $s_id=>$similarity_array) {
        $row = new html_table_row();
        // first cell
        $cell = new html_table_cell();
        $cell->text = $student_names[$s_id];
        $row->cells[] = $cell;
        
        // arrow cell
        $cell = new html_table_cell();
        $cell->text = '&rarr;';
        $row->cells[] = $cell;
        
        foreach ($similarity_array as $s2_id=>$similarity) {
            $cell = new html_table_cell();
            $compare_link = html_writer::tag('a', $similarity['rate'].'%',
                array('href'=>'view_compare.php?id='.$similarity['id']));
            $cell->text = create_student_link($student_names[$s2_id], $s2_id).'<br/>'.$compare_link;
            $mark = $similarity['mark'];
            $cell->attributes['class'] = ($mark=='Y')?'suspicious':(($mark=='N')?'normal':'');
            $row->cells[] = $cell;
        }
        $table->data[] = $row;
    }
    return $table;
}

function create_table_list_mode(&$list,&$student_names,$cmid) {
    global $CFG;
    
    $table = new html_table();
    $table->attributes['class'] = 'plagiarism_programming_result_table';
    $rownum = 1;
    foreach ($list as $pair) {
        $row = new html_table_row();

        $cell = new html_table_cell($rownum++);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = create_student_link($student_names[$pair->student1_id], $pair->student1_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = create_student_link($student_names[$pair->student2_id], $pair->student2_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = html_writer::tag('a', $pair->similarity1.'%',
                array('href'=>'view_compare.php?id='.$pair->id));
        $row->cells[] = $cell;
        
        $mark = $pair->mark;
        $row->attributes['class'] = ($mark=='Y')?'suspicious':(($mark=='N')?'normal':'');

        $table->data[] = $row;
    }
    return $table;
}

function create_chart($cmid,$tool,$similarity_type) {
    global $DB;
    
    $select = "cmid=$cmid AND detector='$tool'";
    $similarities = $DB->get_fieldset_select('programming_result','similarity1',$select);
    $thickness = 100;

    $histogram = array();
    for ($i=9;$i>=0;$i--) {
        $histogram[$i] = 0;
    }

    foreach ($similarities as $rate) {
        $histogram[intval(floor($rate/10))]++;
    }
    
    $max_student_num = max($histogram);
    if ($max_student_num>0) {
        $length_ratio = intval(floor(CHART_WITH/$max_student_num));
    } else {
        return '';
    }
    
    $div = '';
    $report_url = new moodle_url(qualified_me());
    foreach ($histogram as $key=>$val) {
        $upper = $key*10+10;
        $lower = $key*10;
        $range = ''.$lower.'-'.$upper;
        $pos_y = (9-$key)*(BAR_WIDTH+5).'px'; // 2 is the space between bars
        $width = max($val*$length_ratio,1).'px';
        // legend of the bar
        $div .= html_writer::tag('div',$range,array('class'=>'legend','style'=>"top:$pos_y;width:40px"));
        // the bar itself
        $report_url->remove_params(array('upper_threshold','lower_threshold'));
        $report_url->params(array('upper_threshold'=>$upper,'lower_threshold'=>$lower));
        $div .= html_writer::tag('a','',array('class'=>'bar','style'=>"top:$pos_y;width:$width",
            'href'=>$report_url->out(false)));
        // number of pairs
        if ($val>0) {
            $left = ($width+50).'px';
            $div .= html_writer::tag('div', $val,
                    array('class'=>'legend','style'=>"top:$pos_y;width:40px;left:$left"));
        }
    }
    return $div;
}

function create_student_link($student_name,$student_id) {
    $report_url = me();
    return html_writer::tag('a', $student_name,
                array('href'=>$report_url."&student=$student_id",'class'=>'plagiarism_programming_student_link'));
}

function get_suspicious_works($student_id,$cmid) {
    global $DB;
    $select = "(student1_id=$student_id OR student2_id=$student_id) AND cmid=$cmid AND mark='Y'";
    return $DB->get_records_select('programming_result',$select);
}

function get_suspicious_students_in_assignment($cmid) {
    global $DB;
    $sql = "Select id,student1_id,student2_id FROM {programming_result} Where cmid=$cmid AND mark='Y'";
    $records = $DB->get_records_sql($sql);
    $students = array();
    foreach ($records as $rec) {
        $students[$rec->student1_id] = $rec->student1_id;
        $students[$rec->student2_id] = $rec->student2_id;
    }
    return $students;
}

function get_report_link($cmid,$student_id=null) {
    global $CFG;
    $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$cmid";
    if ($student_id) {
        $link .= "&student=$student_id";
    }
    return $link;
}