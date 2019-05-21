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
 * Functions to generate the report.
 *
 * Provide the site-wide setting and specific configuration for each assignment.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

require_once($CFG->dirroot . '/user/lib.php');

define('CHART_WIDTH', 800);
define('CHART_HEIGHT', 240);
define('BAR_WIDTH', 20);

/**
 * Create the similarity table in grouping mode, in which each row lists all the similarity rate of a student to all others.
 * Used to create the similarity report
 *
 * @param Array $list A list of records of plagiarism_programming_reslt table (in DESC order) for the selected detector.
 *            Not altered by the function. Passed by reference for performance only.
 * @param Array $studentnames Associative array id=>name of the students in this assignment. Not altered by the function.
 *            Passed by reference for performance only.
 * @return $table The html_table object
 */
function plagiarism_programming_create_table_grouping_mode(&$list, &$studentnames) {
    $similaritytable = array();
    foreach ($list as $pair) {
        // Make sure student1 id > student2 id to avoid repetition latter.
        $student1 = max($pair->student1_id, $pair->student2_id);
        $student2 = min($pair->student1_id, $pair->student2_id);

        if (is_numeric($student1)) {
            $similaritytable[$student1][$student2] = array(
                'rate' => $pair->similarity,
                'file' => $pair->comparison,
                'id' => $pair->id,
                'mark' => $pair->mark
            );
        }

        if (is_numeric($student2)) { // Only add a line for student2 if it is a real student.
            $similaritytable[$student2][$student1] = array(
                'rate' => $pair->similarity,
                'file' => $pair->comparison,
                'id' => $pair->id,
                'mark' => $pair->mark
            );
        }
    }

    $table = new html_table();
    $table->attributes['class'] = 'plagiarism_programming_result_table generaltable';
    foreach ($similaritytable as $studentid => $similarityarray) {

        $row = new html_table_row();
        // First cell.
        $cell = new html_table_cell();
        $cell->text = $studentnames[$studentid];
        $row->cells[] = $cell;

        // Arrow cell.
        $cell = new html_table_cell();
        $cell->text = '&rarr;';
        $row->cells[] = $cell;

        foreach ($similarityarray as $stud2id => $similarity) {
            $cell = new html_table_cell();
            $comparelink = html_writer::link('view_compare.php?id=' . $similarity['id'], round($similarity['rate'], 2) . '%', array(
                'class' => 'compare_link'
            ));
            $cell->text = plagiarism_programming_create_student_link($studentnames[$stud2id], $stud2id). '<br/>' . $comparelink;
            $mark = $similarity['mark'];
            $cell->attributes['class'] = ($mark == 'Y') ? 'suspicious' : (($mark == 'N') ? 'normal' : '');
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
 *
 * @param Object $list A list of records of plagiarism_programming_reslt table in DESC order for the selected detector.
 *            Not altered by the function.
 *            Passed by reference for performance only
 * @param Array $studentnames Associative array id=>name of the students in this assignment. Not altered by the function.
 *            Passed by reference for performance only
 * @param Number $anchor ID of anchor student. If anchor is specified, the anchored student will always appear on the left.
 * @return $table The html_table object.
 */
function plagiarism_programming_create_table_list_mode(&$list, &$studentnames, $anchor = null) {
    $table = new html_table();
    $table->attributes['class'] = 'plagiarism_programming_result_table generaltable';
    $rownum = 1;
    foreach ($list as $pair) {

        $student1 = $studentnames[$pair->student1_id];
        $student2 = $studentnames[$pair->student2_id];
        if ($anchor && $anchor != $pair->student1_id) {
            $temp = $student1;
            $student1 = $student2;
            $student2 = $temp;
            unset($temp);
        }

        $row = new html_table_row();

        $cell = new html_table_cell($rownum ++);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = plagiarism_programming_create_student_link($student1, $pair->student1_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = plagiarism_programming_create_student_link($student2, $pair->student2_id);
        $row->cells[] = $cell;

        $cell = new html_table_cell();
        $cell->text = html_writer::link("view_compare.php?id=$pair->id", round($pair->similarity, 2) . '%', array(
            'class' => 'compare_link'
        ));
        $row->cells[] = $cell;

        $mark = $pair->mark;
        $row->attributes['class'] = ($mark == 'Y') ? 'suspicious' : (($mark == 'N') ? 'normal' : '');

        $table->data[] = $row;
    }
    return $table;
}

/**
 * Create the distribution graph of similarity rate of all the students
 * @param Number $reportid ID of the report.
 * @param String $similaritytype Either average (avg) or maximum (max)
 * @return String The html code for the graph
 */
function plagiarism_programming_create_chart($reportid, $similaritytype) {
    global $DB;

    $select = "reportid=$reportid";
    // Similarity depends on similarity type, "greatest" is supported in all Moodle except SQLServer.
    $result = ($similaritytype == 'avg') ? '(similarity1+similarity2)/2' : 'greatest(similarity1,similarity2)';
    $similarities = $DB->get_fieldset_select('plagiarism_programming_reslt', $result, $select);

    $histogram = array();
    for ($i = 10; $i >= 0; $i --) {
        $histogram[$i] = 0;
    }

    foreach ($similarities as $rate) {
        $histogram[intval(floor($rate / 10))] ++;
    }
    // For 100% similar pairs, they are placed in interval 10, now they are merged with the 9th interval.
    $histogram[9] += $histogram[10];
    unset($histogram[10]);

    $maxstudentnum = max($histogram);
    if ($maxstudentnum > 0) {
        $lengthratio = intval(floor(CHART_WIDTH / $maxstudentnum));
    } else {
        return '';
    }

    $div = '';
    $reporturl = new moodle_url(qualified_me());

    foreach ($histogram as $key => $val) {
        $upper = $key * 10 + 10;
        $lower = $key * 10;
        $range = $lower . '-' . $upper;
        $posy = (9 - $key) * (BAR_WIDTH + 5) . 'px'; // 2 is the space between bars.
        $width = ($val * $lengthratio) . 'px';
        // Legend of the bar.
        $div .= html_writer::tag('div', $range, array(
            'class' => 'legend',
            'style' => "top:$posy;width:40px"
        ));
        // The bar itself.
        $reporturl->remove_params(array(
            'upper_threshold',
            'lower_threshold'
        ));
        $reporturl->params(array(
            'upper_threshold' => $upper,
            'lower_threshold' => $lower
        ));

        // Number of pairs.
        $left = "0px";
        if ($val > 0) {
            $div .= html_writer::link($reporturl->out(false), '', array(
                'class' => 'bar',
                'style' => "top:$posy;width:$width"
            ));
            $left = (rtrim($width, "px") + 5) . 'px';

            $div .= html_writer::tag('div', $val, array(
                'class' => 'legend',
                'style' => "top:$posy;left:$left"
            ));
        }
    }

    $posy = (10 * (BAR_WIDTH + 5) - 5) . 'px';
    $width = CHART_WIDTH . 'px';
    $posy = (CHART_HEIGHT + 10) . 'px';
    $div .= html_writer::tag('div', get_string('pair', 'plagiarism_programming'), array(
        'class' => 'legend',
        'style' => "top:$posy;left:0px"
    ));
    return "<div class='canvas'>$div</div>";
}

/**
 * Create HTML output of the lookup table in the top left section
 * @param Object $resulttable
 * @param Boolean $isteacher
 * @param Array $studentnames
 * @param Number $courseid
 */
function plagiarism_programming_create_student_lookup_table(&$resulttable, $isteacher, &$studentnames, $courseid) {
    global $USER, $DB;

    $studentnames = array();
    if ($isteacher) {
        foreach ($resulttable as $pair) {
            $studentnames[$pair->student1_id] = $pair->student1_id;
            $studentnames[$pair->student2_id] = $pair->student2_id;
        }
    } else {
        foreach ($resulttable as $pair) {
            $studentnames[$pair->student1_id] = "someone's";
            $studentnames[$pair->student2_id] = "someone's";
        }
    }

    // Find students' name if he is the lecturer.
    if ($isteacher) {
        // Gets all the users of the course with standard settings.
        $students = user_get_participants($courseid, 0, 0, 0, 0, - 1, '');
        foreach ($students as $student) {
            $studentnames[$student->id] = fullname($student);
        }
    } else { // If user is a student.
        $studentnames[$USER->id] = 'Yours';
    }
}

/**
 * Create link to detailed view of one student.
 * @param String $studentname
 * @param Number $studentid
 * @return string
 */
function plagiarism_programming_create_student_link($studentname, $studentid) {
    $reporturl = me();
    return html_writer::link("$reporturl&student=$studentid", $studentname, array(
        'class' => 'plagiarism_programming_student_link'
    ));
}

/**
 * Returns all submissions marked as suspicious
 * @param Number $studentid
 * @param Number $cmid course module id
 * @return array|array
 */
function plagiarism_programming_get_suspicious_works($studentid, $cmid) {
    global $DB;
    // Get the latest report version.
    $version = $DB->get_field('plagiarism_programming_rpt', 'max(version)', array(
        'cmid' => $cmid
    ));
    if ($version === null) {
        return array();
    }

    $ids = $DB->get_fieldset_select('plagiarism_programming_rpt', 'id', "cmid=$cmid AND version=$version");
    if (! empty($ids)) {
        list ($insql, $params) = $DB->get_in_or_equal($ids);
        $select = "reportid $insql AND (student1_id=? OR student2_id=?) AND mark=?";
        $params[] = $studentid;
        $params[] = $studentid;
        $params[] = 'Y';
        return $DB->get_records_select('plagiarism_programming_reslt', $select, $params);
    } else {
        return array();
    }
}

/**
 * Returns the similarity info.
 * @param Number $cmid Course Module ID
 * @param Number $studid
 * @return array|mixed[][]|string[][]|NULL[][]
 */
function plagiarism_programming_get_students_similarity_info($cmid, $studid = null) {
    global $DB, $detectiontools;

    // Get the enabled plugins.
    $setting = $DB->get_record('plagiarism_programming', array(
        'cmid' => $cmid
    ));
    // Get the latest report version.
    $reports = array();
    foreach ($detectiontools as $toolname => $toolinfo) {
        if ($setting->$toolname) {
            $report = plagiarism_programming_get_latest_report($cmid, $toolname);
            if ($report) {
                $reports[$report->id] = new stdClass();
                $reports[$report->id]->detector = $toolname;
            }
        }
    }

    if (count($reports) == 0) {
        return array();
    }

    list ($insql, $params) = $DB->get_in_or_equal(array_keys($reports));
    $sql = "SELECT id, student1_id, student2_id, (similarity1+similarity2)/2 as similarity, mark, reportid
              FROM {plagiarism_programming_reslt}
             WHERE reportid $insql ";
    if ($studid !== null) {
        $sql .= " AND (student1_id=? OR student2_id=?)";
        $params[] = $studid;
        $params[] = $studid;
    }

    $records = $DB->get_records_sql($sql, $params);
    $students = array();
    foreach ($records as $rec) {
        foreach (array(
            'student1_id',
            'student2_id'
        ) as $studid) {
            if (isset($students[$rec->$studid])) {
                $max = max($students[$rec->$studid]['max'], $rec->similarity);
                $mark = ($rec->mark == 'Y') ? 'Y' : $students[$rec->$studid]['mark'];
                $detector = ($rec->similarity == $max) ? $reports[$rec->reportid]->detector : $students[$rec->$studid]['detector'];
                $students[$rec->$studid] = array(
                    'max' => $max,
                    'mark' => $mark,
                    'detector' => $detector
                );
            } else {
                $students[$rec->$studid] = array(
                    'max' => $rec->similarity,
                    'mark' => $rec->mark,
                    'detector' => $reports[$rec->reportid]->detector
                );
            }
        }
    }
    return $students;
}

/**
 * Returns the URL of with optional filters.
 * @param Number $cmid Course Module ID
 * @param Number $studentid
 * @param String $detector Moss or JPlag
 * @param Number $threshold Minimum similarity to show.
 * @return string
 */
function plagiarism_programming_get_report_link($cmid, $studentid = null, $detector = null, $threshold = null) {
    global $CFG;
    $link = "$CFG->wwwroot/plagiarism/programming/view.php?cmid=$cmid";
    if ($studentid) {
        $link .= "&student=$studentid";
    }
    if ($detector) {
        $link .= "&detector=$detector";
    }
    if ($threshold !== null) {
        $link .= "&lower_threshold=$threshold";
    }
    return $link;
}

/**
 * Get the next version of the report for the specified assignment with the detector
 *
 * @param number $cmid
 *            the course module id of the assignment. If null, it will return the root directory of all the report
 * @param number $detector
 *            the version of report. If null, it will return the directory of the latest report of this assignment
 * @return $report The report record having the latest version.
 */
function plagiarism_programming_get_latest_report($cmid, $detector) {
    global $DB;
    $version = $DB->get_field('plagiarism_programming_rpt', 'max(version)', array(
        'cmid' => $cmid,
        'detector' => $detector
    ));
    if ($version !== false) {
        $report = $DB->get_record('plagiarism_programming_rpt', array(
            'cmid' => $cmid,
            'version' => $version,
            'detector' => $detector
        ));
        return $report;
    } else {
        return null;
    }
}

/**
 * Create the new report version
 *
 * @param number $cmid
 *            the course module id of the assignment. If null, it will return the root directory of all the report
 * @param number $detector
 *            the version of report. If null, it will return the directory of the latest report of this assignment
 * @return $report The report record created.
 */
function plagiarism_programming_create_new_report($cmid, $detector) {
    global $DB;
    // Create a new version of the report.
    $latestreport = plagiarism_programming_get_latest_report($cmid, $detector);
    if ($latestreport) {
        $version = $latestreport->version + 1;
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

/**
 * Save the similarity of one pair.
 * @param Object $pairresult
 */
function plagiarism_programming_save_similarity_pair($pairresult) {
    global $DB;
    if (! ctype_digit($pairresult->student1_id)) {
        $pairresult->additional_codefile_name = $pairresult->student1_id;
        $pairresult->student1_id = 0;
    } else if (! ctype_digit($pairresult->student2_id)) {
        $pairresult->additional_codefile_name = $pairresult->student2_id;
        $pairresult->student2_id = 0;
    } else {
        $pairresult->additional_codefile_name = null;
    }
    $DB->insert_record('plagiarism_programming_reslt', $pairresult);
}

/**
 * Transforms a similarity pair (adds student id's?)
 * @param Object $similarpairs
 * @return $similarpairs
 */
function plagiarism_programming_transform_similarity_pair($similarpairs) {
    if (! is_array($similarpairs)) { // Only one object is passed.
        $pairs = array(
            $similarpairs
        );
    } else {
        $pairs = $similarpairs;
    }
    foreach ($pairs as $pair) {
        if ($pair && ! empty($pair->additional_codefile_name)) {
            if ($pair->student1_id == 0) {
                $pair->student1_id = $pair->additional_codefile_name;
            } else if ($pair->student2_id == 0) {
                $pair->student2_id = $pair->additional_codefile_name;
            }
        }
    }
    // Objects are passed by reference and we only modify the object.
    return $similarpairs;
}

/**
 * Returns the history of one student.
 * @param Object $result
 * @param string $timesort
 * @return array
 */
function plagiarism_programming_get_student_similarity_history($result, $timesort = 'desc') {
    global $DB;
    $report = $DB->get_record('plagiarism_programming_rpt', array(
        'id' => $result->reportid
    ));
    $params = array();

    $sql = "SELECT result.*, time_created
              FROM {plagiarism_programming_rpt} report
              JOIN {plagiarism_programming_reslt} result
                ON (report.id = result.reportid)
             WHERE report.cmid=:cmid AND report.detector=:detector ";
    if ($result->additional_codefile_name === null) {
        $sql .= " AND result.additional_codefile_name IS NULL ";
    } else {
        $sql .= " AND result.additional_codefile_name = :addtional_name ";
        $params['addtional_name'] = $result->additional_codefile_name;
    }
    $sql .= " AND ((result.student1_id=:student1_id1 AND result.student2_id=:student2_id1)
              OR  (result.student1_id=:student2_id2 AND result.student2_id=:student1_id2))
        ORDER BY time_created $timesort";

    $params += array(
        'cmid' => $report->cmid,
        'detector' => $report->detector,
        'student1_id1' => $result->student1_id,
        'student1_id2' => $result->student1_id,
        'student2_id1' => $result->student2_id,
        'student2_id2' => $result->student2_id
    );
    $pairs = $DB->get_records_sql($sql, $params);
    return $pairs;
}

/**
 * Deletes the config of an activity.
 * @param Number $cmid Course Module ID
 */
function plagiarism_programming_delete_config($cmid) {
    global $DB;

    $setting = $DB->get_record('plagiarism_programming', array(
        'cmid' => $cmid
    ));
    $reportids = $DB->get_records_menu('plagiarism_programming', array(
        'cmid' => $cmid
    ), '', 'id,cmid');
    if ($setting) {
        $DB->delete_records('plagiarism_programming_date', array(
            'settingid' => $setting->id
        ));
        $DB->delete_records('plagiarism_programming_jplag', array(
            'settingid' => $setting->id
        ));
        $DB->delete_records('plagiarism_programming_moss', array(
            'settingid' => $setting->id
        ));
        if (count($reportids) > 0) {
            list ($insql, $params) = $DB->get_in_or_equal(array_keys($reportids));
            $DB->delete_records('plagiarism_programming_rpt', array(
                'cmid' => $setting->cmid
            ));
            $DB->delete_records_select('plagiarism_programming_reslt', "reportid $insql", $params);
        }
        $DB->delete_records('plagiarism_programming', array(
            'id' => $setting->id
        ));
    }
}
