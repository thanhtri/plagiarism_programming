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
 * @param $list: a list of records of plagiarism_programming_reslt table (in DESC order) for the selected detector.
 * Not altered by the function. Passed by reference for performance only
 * @param $student_names: associative array id=>name of the students in this assignment. Not altered by the function.
 *        Passed by reference for performance only
 * @param $cmid: course module id of the assignment
 * @return the html_table object
 */
function plagiarism_programming_create_table_grouping_mode(&$list, &$student_names) {

    $similarity_table = array();
    foreach ($list as $pair) {
        // make sure student1 id > student2 id to avoid repetition latter
        $student1 = max($pair->student1_id, $pair->student2_id);
        $student2 = min($pair->student1_id, $pair->student2_id);

        if (is_numeric($student1)) {
            $similarity_table[$student1][$student2] =
                array('rate'=>$pair->similarity,
                      'file'=>$pair->comparison,
                      'id'=>$pair->id,
                      'mark'=>$pair->mark);
        }

        if (is_numeric($student2)) { // only add a line for student2 if it is a real student
            $similarity_table[$student2][$student1] =
                array('rate'=>$pair->similarity,
                      'file'=>$pair->comparison,
                      'id'=>$pair->id,
                      'mark'=>$pair->mark);
        }
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
            $cell->text = plagiarism_programming_create_student_link($student_names[$s2_id], $s2_id).'<br/>'.$compare_link;
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
 * @param $list: a list of records of plagiarism_programming_reslt table in DESC order for the selected detector. Not altered by the function.
 *        Passed by reference for performance only
 * @param $student_names: associative array id=>name of the students in this assignment. Not altered by the function.
 *        Passed by reference for performance only
 * @param $anchor: if anchor is specified, the anchored student will always appear on the left
 * @return the html_table object
 */
function plagiarism_programming_create_table_list_mode(&$list, &$student_names, $anchor=null) {

    $table = new html_table();
    $table->attributes['class'] = 'plagiarism_programming_result_table generaltable';
    $rownum = 1;
    foreach ($list as $pair) {

        $student1 = $student_names[$pair->student1_id];
        $student2 = $student_names[$pair->student2_id];
        if ($anchor && $anchor!=$pair->student1_id) {
            $temp = $student1;
            $student1 = $student2;
            $student2 = $temp;
            unset($temp);
        }

        $row = new html_table_row();

        $cell = new html_table_cell($rownum++);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = plagiarism_programming_create_student_link($student1, $pair->student1_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = plagiarism_programming_create_student_link($student2, $pair->student2_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = html_writer::link("view_compare.php?id=$pair->id", round($pair->similarity, 2).'%', array('class'=>'compare_link'));
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
function plagiarism_programming_create_chart($reportid, $similarity_type) {
    global $DB;

    $select = "reportid=$reportid";
    // similarity depends on similarity type, "greatest" is supported in all Moodle except SQLServer
    $result = ($similarity_type=='avg')?'(similarity1+similarity2)/2':'greatest(similarity1,similarity2)';
    $similarities = $DB->get_fieldset_select('plagiarism_programming_reslt', $result, $select);

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

/**
 * Create HTML output of the lookup table in the top left section
 */
function plagiarism_programming_create_student_lookup_table(&$result_table, $is_teacher, &$student_names) {
    global $USER, $DB;

    $student_names = array();
    if ($is_teacher) {
        foreach ($result_table as $pair) {
            $student_names[$pair->student1_id] = $pair->student1_id;
            $student_names[$pair->student2_id] = $pair->student2_id;
        }
    } else {
        foreach ($result_table as $pair) {
            $student_names[$pair->student1_id] = "someone's";
            $student_names[$pair->student2_id] = "someone's";
        }
    }

    // find students' name if he is the lecturer
    if ($is_teacher) {
        $ids = array_keys($student_names);
        $students = $DB->get_records_list('user', 'id', $ids, null, 'id,firstname,lastname');
        foreach ($students as $student) {
            $student_names[$student->id] = fullname($student);
        }
    } else {    // if user is a student
        $student_names[$USER->id] = 'Yours';
    }
}

function plagiarism_programming_create_student_link($student_name, $student_id) {
    $report_url = me();
    return html_writer::link("$report_url&student=$student_id", $student_name,
        array('class'=>'plagiarism_programming_student_link'));
}

function plagiarism_programming_get_suspicious_works($student_id, $cmid) {
    global $DB;
    // get the latest report version
    $version = $DB->get_field('plagiarism_programming_rpt', 'max(version)', array('cmid'=>$cmid));
    if ($version===null) {
        return array();
    }

    $ids = $DB->get_fieldset_select('plagiarism_programming_rpt', 'id', "cmid=$cmid And version=$version");
    if (count($ids)>0) {
        $ids = implode(',', $ids);
        $select = "(student1_id=$student_id OR student2_id=$student_id) AND reportid IN ($ids) AND mark='Y'";
        return $DB->get_records_select('plagiarism_programming_reslt', $select);
    } else {
        return array();
    }
}

function plagiarism_programming_get_students_similarity_info($cmid, $student_id=null) {
    global $DB, $detection_tools;

    // get the enabled plugins
    $setting = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
    // get the latest report version
    $reports = array();
    foreach ($detection_tools as $toolname => $toolinfo) {
        if ($setting->$toolname) {
            $report = plagiarism_programming_get_latest_report($cmid, $toolname);
            if ($report) {
                $reports[$report->id] = new stdClass();
                $reports[$report->id]->detector = $toolname;
            }
        }
    }

    if (count($reports)==0) {
        return array();
    }
    $ids = implode(',', array_keys($reports));
    list($insql, $params) = $DB->get_in_or_equal($ids);
    $sql = 'Select id,student1_id,student2_id,(similarity1+similarity2)/2 as similarity,mark,reportid '.
        "FROM {plagiarism_programming_reslt} Where reportid $insql";
    if ($student_id!==null) {
        $sql .= " And (student1_id=:student1id OR student2_id=:student2id)";
    }

    $params['student1id'] = $student_id;
    $params['student2id'] = $student_id;
    $records = $DB->get_records_sql($sql, $params);
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

function plagiarism_programming_get_report_link($cmid, $student_id=null, $detector=null, $threshold=null) {
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
function plagiarism_programming_get_latest_report($cmid, $detector) {
    global $DB;
    $version = $DB->get_field('plagiarism_programming_rpt', 'max(version)', array('cmid'=>$cmid, 'detector'=>$detector));
    if ($version!==false) {
        $report = $DB->get_record('plagiarism_programming_rpt', array('cmid'=>$cmid, 'version'=>$version, 'detector'=>$detector));
        return $report;
    } else {
        return null;
    }
}

/**
 * Create the new report version
 * @param number $cmid the course module id of the assignment. If null, it will return the root directory of all the report
 * @param number $detector the version of report. If null, it will return the directory of the latest report of this assignment
 * @return the report record created
 */
function plagiarism_programming_create_new_report($cmid, $detector) {
    global $DB;
    // create a new version of the report
    $latest_report = plagiarism_programming_get_latest_report($cmid, $detector);
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
    $report->id = $DB->insert_record('plagiarism_programming_rpt', $report);
    return $report;
}

function plagiarism_programming_save_similarity_pair($pair_result) {
    global $DB;
    if (!ctype_digit($pair_result->student1_id)) {
        $pair_result->additional_codefile_name = $pair_result->student1_id;
        $pair_result->student1_id = 0;
    } else if (!ctype_digit($pair_result->student2_id)) {
        $pair_result->additional_codefile_name = $pair_result->student2_id;
        $pair_result->student2_id = 0;
    } else {
        $pair_result->additional_codefile_name = null;
    }
    $DB->insert_record('plagiarism_programming_reslt', $pair_result);
}

function plagiarism_programming_transform_similarity_pair($similar_pairs) {
    if (!is_array($similar_pairs)) { // only one object is passed
        $pairs = array($similar_pairs);
    } else {
        $pairs = $similar_pairs;
    }
    foreach ($pairs as $pair) {
        if ($pair && !empty($pair->additional_codefile_name)) {
            if ($pair->student1_id==0) {
                $pair->student1_id = $pair->additional_codefile_name;
            } else if ($pair->student2_id==0) {
                $pair->student2_id = $pair->additional_codefile_name;
            }
        }
    }
    // objects are passed by reference and we only modify the object
    return $similar_pairs;
}

function plagiarism_programming_get_student_similarity_history($result, $time_sort='desc') {
    global $DB;
    $report = $DB->get_record('plagiarism_programming_rpt', array('id'=>$result->reportid));
    $params = array();

    $sql = "SELECT result.*, time_created
              FROM {plagiarism_programming_rpt} report
              JOIN {plagiarism_programming_reslt} result
                ON (report.id = result.reportid)
             WHERE report.cmid=:cmid AND report.detector=:detector ";
    if ($result->additional_codefile_name===NULL) {
        $sql .= " AND result.additional_codefile_name IS NULL ";
    } else {
        $sql .= " AND result.additional_codefile_name = :addtional_name ";
        $params['addtional_name'] = $result->additional_codefile_name;
    }
    $sql .= " AND ((result.student1_id=:student1_id1 AND result.student2_id=:student2_id1)
              OR  (result.student1_id=:student2_id2 AND result.student2_id=:student1_id2))
        ORDER BY time_created $time_sort";

    $params += array('cmid'     => $report->cmid,
                      'detector' => $report->detector,
                      'student1_id1'   => $result->student1_id,
                      'student1_id2'   => $result->student1_id,
                      'student2_id1'   => $result->student2_id,
                      'student2_id2'   => $result->student2_id);
    $pairs = $DB->get_records_sql($sql, $params);
    return $pairs;
}


function plagiarism_programming_delete_config($cmid) {
    global $DB;

    $setting = $DB->get_record('plagiarism_programming', array('cmid'=>$cmid));
    $report_ids = $DB->get_records_menu('plagiarism_programming', array('cmid'=>$cmid), '', 'id,cmid');
    if ($setting) {
        $DB->delete_records('plagiarism_programming_date', array('settingid'=>$setting->id));
        $DB->delete_records('plagiarism_programming_jplag', array('settingid'=>$setting->id));
        $DB->delete_records('plagiarism_programming_moss', array('settingid'=>$setting->id));
        if (count($report_ids)>0) {
            $in_clause = implode(',', array_keys($report_ids));
            $DB->delete_records('plagiarism_programming_rpt', array('cmid'=>$setting->cmid));
            $DB->delete_records_select('plagiarism_programming_reslt', "reportid IN ($in_clause)");
        }
        $DB->delete_records('plagiarism_programming', array('id'=>$setting->id));
    }
}
