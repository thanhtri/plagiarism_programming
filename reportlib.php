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
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

define('CHART_WITH', 800);
define('CHART_HEIGHT', 240);
define('BAR_WIDTH', 20);

/**
 * Create the similarity table in grouping mode, in which each row lists all the similarity rate of a student to all others.
 * Used to create the similarity report
 * @param $list: a list of records of programming_result table (in DESC order) for the selected detector.
 * Not altered by the function. Passed by reference for performance only
 * @param $student_names: associative array id=>name of the students in this assignment. Not altered by the function.
 *        Passed by reference for performance only
 * @param $cmid: course module id of the assignment
 * @return the html_table object
 */
function create_table_grouping_mode(&$list, &$student_names) {

    $similarity_table = array();
    foreach ($list as $pair) {
        // make sure student1 id > student2 id to avoid repetition latter
        $student1 = max($pair->student1_id, $pair->student2_id);
        $student2 = min($pair->student1_id, $pair->student2_id);

        $similarity_table[$student1][$student2] =
            array('rate'=>$pair->similarity,
                  'file'=>$pair->comparison,
                  'id'=>$pair->id,
                  'mark'=>$pair->mark);
        $similarity_table[$student2][$student1] =
            array('rate'=>$pair->similarity,
                  'file'=>$pair->comparison,
                  'id'=>$pair->id,
                  'mark'=>$pair->mark);
    }

    $table = new html_table();
    $table->attributes['class']='plagiarism_programming_result_table generaltable';
    foreach ($similarity_table as $s_id => $similarity_array) {
        $row = new html_table_row();
        // first cell
        $cell = new html_table_cell();
        $cell->text = $student_names[$s_id];
        $row->cells[] = $cell;

        // arrow cell
        $cell = new html_table_cell();
        $cell->text = '&rarr;';
        $row->cells[] = $cell;

        foreach ($similarity_array as $s2_id => $similarity) {
            $cell = new html_table_cell();
            $compare_link = html_writer::link('view_compare.php?id='.$similarity['id'], round($similarity['rate'], 2).'%',
                array('class'=>'compare_link'));
            $cell->text = create_student_link($student_names[$s2_id], $s2_id).'<br/>'.$compare_link;
            $mark = $similarity['mark'];
            $cell->attributes['class'] = ($mark=='Y')?'suspicious':(($mark=='N')?'normal':'');
            $cell->attributes['class'] .= ' similar_pair';
            $cell->attributes['pair'] = $similarity['id'];
            $row->cells[] = $cell;
        }
        $table->data[] = $row;
    }
    return $table;
}

/**
 * Create the similarity table in list mode, in which pairs of students are listed in descending similarity rate
 * Used to create the similarity report
 * @param $list: a list of records of programming_result table in DESC order for the selected detector. Not altered by the function.
 *        Passed by reference for performance only
 * @param $student_names: associative array id=>name of the students in this assignment. Not altered by the function.
 *        Passed by reference for performance only
 * @param $cmid: course module id of the assignment
 * @return the html_table object
 */
function create_table_list_mode(&$list, &$student_names) {

    $table = new html_table();
    $table->attributes['class'] = 'plagiarism_programming_result_table generaltable';
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
        $cell->text = html_writer::link("view_compare.php?id=$pair->id", "$pair->similarity%", array('class'=>'compare_link'));
        $row->cells[] = $cell;

        $mark = $pair->mark;
        $row->attributes['class'] = ($mark=='Y')?'suspicious':(($mark=='N')?'normal':'');

        $table->data[] = $row;
    }
    return $table;
}

/**
 * Create the distribution graph of similarity rate of all the students
 * @param $cmid: the course module id
 * @param $tool: the name of the tool (either JPlag or MOSS)
 * @param $similarity_type: either average (avg) or maximum (max)
 * @return the html code for the graph
 */
function create_chart($reportid, $similarity_type) {
    global $DB;

    $select = "reportid=$reportid";
    // similarity depends on similarity type, "greatest" is supported in all Moodle except SQLServer
    $result = ($similarity_type=='avg')?'(similarity1+similarity2)/2':'greatest(similarity1,similarity2)';
    $similarities = $DB->get_fieldset_select('programming_result', $result, $select);

    $histogram = array();
    for ($i=10; $i>=0; $i--) {
        $histogram[$i] = 0;
    }

    foreach ($similarities as $rate) {
        $histogram[intval(floor($rate/10))]++;
    }
    // for 100% similar pairs, they are placed in interval 10, now they are merged with the 9th interval
    $histogram[9]+=$histogram[10];
    unset($histogram[10]);

    $max_student_num = max($histogram);
    if ($max_student_num>0) {
        $length_ratio = intval(floor(CHART_WITH/$max_student_num));
    } else {
        return '';
    }

    $div = '';
    $report_url = new moodle_url(qualified_me());
    foreach ($histogram as $key => $val) {
        $upper = $key*10+10;
        $lower = $key*10;
        $range = $lower.'-'.$upper;
        $pos_y = (9-$key)*(BAR_WIDTH+5).'px'; // 2 is the space between bars
        $width = ($val*$length_ratio).'px';
        // legend of the bar
        $div .= html_writer::tag('div', $range, array('class'=>'legend', 'style'=>"top:$pos_y;width:40px"));
        // the bar itself
        $report_url->remove_params(array('upper_threshold', 'lower_threshold'));
        $report_url->params(array('upper_threshold'=>$upper, 'lower_threshold'=>$lower));
        // number of pairs
        if ($val>0) {
            $div .= html_writer::link($report_url->out(false), '', array('class'=>'bar', 'style'=>"top:$pos_y;width:$width"));
            $left = ($width+5).'px';
            $div .= html_writer::tag('div', $val,
                    array('class'=>'legend', 'style'=>"top:$pos_y;left:$left"));
        }
    }
    $pos_y = (10*(BAR_WIDTH+5)-5).'px';
    $width = CHART_WITH.'px';
    //$div .= html_writer::tag('div', '', array('class'=>'bar', 'style'=>"top:$pos_y;width:$width;height:1px"));
    $pos_y = (CHART_HEIGHT+10).'px';
    $div .= html_writer::tag('div', get_string('pair', 'plagiarism_programming'),
            array('class'=>'legend', 'style'=>"top:$pos_y;left:0px"));
    return "<div class='canvas'>$div</div>";
}

function create_student_name_lookup_table(&$result_table, $is_teacher, &$student_names) {
    global $USER, $DB;

    $student_names = array();
    foreach ($result_table as $pair) {
        $student_names[$pair->student1_id] = "someone's";
        $student_names[$pair->student2_id] = "someone's";
    }

    // find students' name if he is the lecturer
    if ($is_teacher) {
        $ids = array_keys($student_names);
        $students = $DB->get_records_list('user', 'id', $ids, null, 'id,firstname,lastname');
        foreach ($students as $student) {
            $student_names[$student->id] = $student->firstname.' '.$student->lastname;
        }
    } else {    // if user is a student
        $student_names[$USER->id] = 'Yours';
    }
}

function create_student_link($student_name, $student_id) {
    $report_url = me();
    return html_writer::link("$report_url&student=$student_id", $student_name,
        array('class'=>'plagiarism_programming_student_link'));
}

function get_suspicious_works($student_id, $cmid) {
    global $DB;
    // get the latest report version
    $version = $DB->get_field('programming_report', 'max(version)', array('cmid'=>$cmid));
    if ($version===null) {
        return array();
    }

    $ids = $DB->get_fieldset_select('programming_report', 'id', "cmid=$cmid And version=$version");
    if (count($ids)>0) {
        $ids = implode(',', $ids);
        $select = "(student1_id=$student_id OR student2_id=$student_id) AND reportid IN ($ids) AND mark='Y'";
        return $DB->get_records_select('programming_result', $select);
    } else {
        return array();
    }
}

function get_students_similarity_info($cmid, $student_id=null) {
    global $DB;
    // get the latest report version
    $version = $DB->get_field('programming_report', 'max(version)', array('cmid'=>$cmid));
    if ($version==null) { // no report yet
        return array();
    }
    $reports = $DB->get_records('programming_report', array('cmid'=>$cmid, 'version'=>$version));

    if (count($reports)==0) {
        return array();
    }
    $ids = implode(',', array_keys($reports));
    $sql = 'Select id,student1_id,student2_id,(similarity1+similarity2)/2 as similarity,mark,reportid '.
        "FROM {programming_result} Where reportid IN ($ids)";
    if ($student_id!==null) {
        $sql .= " And (student1_id=$student_id OR student2_id=$student_id)";
    }

    $records = $DB->get_records_sql($sql);
    $students = array();
    foreach ($records as $rec) {
        foreach (array('student1_id', 'student2_id') as $student_id) {
            if (isset($students[$rec->$student_id])) {
                $max = max($students[$rec->$student_id]['max'], $rec->similarity);
                $mark = ($rec->mark=='Y')?'Y':$students[$rec->$student_id]['mark'];
                $detector = ($rec->similarity==$max)?$reports[$rec->reportid]->detector:$students[$rec->$student_id]['detector'];
                $students[$rec->$student_id] = array('max'=>$max, 'mark'=>$mark, 'detector'=>$detector);
            } else {
                $students[$rec->$student_id] = array('max'=>$rec->similarity, 'mark'=>$rec->mark,
                    'detector'=>$reports[$rec->reportid]->detector);
            }
        }
    }
    return $students;
}

function get_report_link($cmid, $student_id=null, $detector=null, $threshold=null) {
    global $CFG;
    $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$cmid";
    if ($student_id) {
        $link .= "&student=$student_id";
    }
    if ($detector) {
        $link .= "&detector=$detector";
    }
    if ($threshold!==null) {
        $link .= "&lower_threshold=$threshold";
    }
    return $link;
}

/**
 * Get the next version of the report for the specified assignment with the detector
 * @param number $cmid the course module id of the assignment. If null, it will return the root directory of all the report
 * @param number $detector the version of report. If null, it will return the directory of the latest report of this assignment
 * @return the report record having the latest version
 */
function get_latest_report($cmid, $detector) {
    global $DB;
    $version = $DB->get_field('programming_report', 'max(version)', array('cmid'=>$cmid, 'detector'=>$detector));
    if ($version!==false) {
        $report = $DB->get_record('programming_report', array('cmid'=>$cmid, 'version'=>$version, 'detector'=>$detector));
        return $report;
    } else {
        return null;
    }
}

/**
 * Create the next version of the report
 * @param number $cmid the course module id of the assignment. If null, it will return the root directory of all the report
 * @param number $detector the version of report. If null, it will return the directory of the latest report of this assignment
 * @return the report record created
 */
function create_next_report($cmid, $detector) {
    global $DB;
    // create a new version of the report
    $latest_report = get_latest_report($cmid, $detector);
    if ($latest_report) {
        $version = $latest_report->version+1;
    } else {
        $version = 1;
    }
    $report = new stdClass();
    $report->cmid = $cmid;
    $report->time_created = time();
    $report->version = $version;
    $report->detector = $detector;
    $report->id = $DB->insert_record('programming_report', $report);
    return $report;
}

function get_student_similarity_history($student1_id, $student2_id, $cmid, $detector, $time_sort='desc') {
    global $DB;
    $sql = "Select result.*, time_created From {programming_report} report, {programming_result} result ".
        " Where report.cmid=$cmid And report.detector='$detector' And report.id = result.reportid And ".
        " ((student1_id=$student1_id And student2_id=$student2_id) Or".
        " (student1_id=$student2_id And student2_id=$student1_id)) Order By time_created ".$time_sort;
    $pairs = $DB->get_records_sql($sql);
    return $pairs;
}