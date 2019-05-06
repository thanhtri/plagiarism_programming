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
 * Report viewing page, when the user click to see the report
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/programming_plag_result_form.php');
require_once(__DIR__.'/reportlib.php');

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

$cmid = required_param('cmid', PARAM_INT);
$student_id = optional_param('student', null, PARAM_TEXT);
$lower_threshold = optional_param('lower_threshold', 20, PARAM_FLOAT); // rate will be above this similarity
$upper_threshold = optional_param('upper_threshold', 100, PARAM_FLOAT);
$tool = optional_param('tool', '', PARAM_TEXT);
$report_version = optional_param('version', -1, PARAM_INT);
$rate_type = optional_param('rate_type', 'avg', PARAM_TEXT);
$include_repository = optional_param('include_repository', 1, PARAM_INT);

// display_mode is either table (similar to JPlag style)  or group (similar to MOSS style)
$display_mode = optional_param('display_mode', 'group', PARAM_TEXT);

if (!$course_module = $DB->get_record('course_modules', array('id'=>$cmid))) {
    redirect($CFG->wwwroot, 'Invalid course module id');
}
$course = $DB->get_record('course', array('id'=>$course_module->course));
if (!$course) {
    redirect($CFG->wwwroot, 'Invalid course id');
}

require_login($course, true, $course_module);

// if the user is a student (does not have grade capability), he can only see the report on his assignment if allowed
$context = context_module::instance($cmid);
$is_teacher = has_capability('mod/assignment:grade', $context);
if (!$is_teacher) {
    // check if he is allowed to see the assignment
    if (!has_capability('mod/assignment:submit', $context) ||
            !$DB->get_field('plagiarism_programming', 'auto_publish', array('cmid'=>$cmid))) {
        redirect($CFG->wwwroot, get_string('permission_denied', 'plagiarism_programming'));
    }
    $student_id = $USER->id;
}

if ($student_id != null) {
    $display_mode = 'table';
}

$PAGE->set_url(new moodle_url('/plagiarism/programming/view.php', array('cmid'=>$cmid)));

// verify the version
if (!empty($tool) && $report_version>0) {
    $report = $DB->get_record('plagiarism_programming_rpt', array('cmid'=>$cmid, 'detector'=>$tool, 'version'=>$report_version));
} else if (empty($tool)) { //if tool empty, assume report version empty
    $report = plagiarism_programming_get_latest_report($cmid, 'jplag');
    if ($report) {
        $tool = 'jplag';
    } else {
        $report = plagiarism_programming_get_latest_report($cmid, 'moss');
        $tool = 'moss';
    }
} else if ($report_version<=0) {
    $report = plagiarism_programming_get_latest_report($cmid, $tool);
}

if (!$report) { // at this point, we don't have any report available
    redirect("$CFG->wwwroot/mod/assignment/view.php?id=".$cmid, get_string('report_not_available', 'plagiarism_programming'));
}
// construct the query based on filtering criteria
if ($rate_type=='max') {
    $similarity = 'greatest(similarity1,similarity2)';
} else {
    $similarity = '(similarity1+similarity2)/2';
}

$select = "SELECT result.*,
                  $similarity AS similarity
             FROM {plagiarism_programming_reslt} result
            WHERE reportid=:report_id AND $similarity>=:lower_threshold AND $similarity<=:upper_threshold";
$params = array(
    'report_id' => $report->id,
    'lower_threshold' => $lower_threshold,
    'upper_threshold' => $upper_threshold,
);

if ($student_id != null) { // filter by student_id
    if (ctype_digit($student_id)) {
        $select .= " AND (student1_id=:student1_id OR student2_id=:student2_id)";
        $params['student1_id'] = $params['student2_id'] = $student_id;
    } else {
        $select .= " AND additional_codefile_name = :student_id ";
        $params['student_id'] = $student_id;
    }
}
if (!$include_repository) {
    $select .= " AND additional_codefile_name IS NULL ";
}
$select .= ' ORDER BY similarity DESC';
$result = $DB->get_records_sql($select, $params);
$result = plagiarism_programming_transform_similarity_pair($result);

$student_names = null;

// TODO: Get course id of course module
plagiarism_programming_create_student_lookup_table($result, $is_teacher, $student_names, $course_module->course); // this will create the array id=>name in $student_names

if ($display_mode=='group') {
    $table = plagiarism_programming_create_table_grouping_mode($result, $student_names);
} else {
    $table = plagiarism_programming_create_table_list_mode($result, $student_names, $student_id);
}

$header = get_string('result', 'plagiarism_programming');
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->navbar->add($header);
echo $OUTPUT->header();

$filter_forms = new programming_plag_result_form($cmid, $tool);
$filter_forms->set_data(array('cmid'=>$cmid,
    'student'=>$student_id,
    'lower_threshold'=>$lower_threshold,
    'tool'=>$tool,
    'rate_type'=>$rate_type,
    'display_mode'=>$display_mode,
    'include_repository'=>$include_repository));
$filter_forms->display();

echo html_writer::tag('div', get_string('chart_legend', 'plagiarism_programming'));
echo html_writer::tag('div', plagiarism_programming_create_chart($report->id, $rate_type), array('class'=>'programming_result_chart'));
echo html_writer::tag('div', html_writer::table($table), array('class'=>'programming_result_table'));

$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/view_report.js',
    'requires' => array('base', 'overlay', 'node', 'json', 'io-base'),
    'strings' => array(
        array('date', 'moodle'),
        array('similarity_history', 'plagiarism_programming')
     )
);
$PAGE->requires->js_init_call('M.plagiarism_programming.view_report.init', array(), false, $jsmodule);

echo $OUTPUT->footer();
