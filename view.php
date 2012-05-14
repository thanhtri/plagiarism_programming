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
 * Report viewing page
 * Provide the site-wide setting and specific configuration for each assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once __DIR__.'/../../config.php';
include_once __DIR__.'/programming_plag_result_form.php';
include_once __DIR__.'/reportlib.php';

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

$cmid = optional_param('cmid', null, PARAM_INT);
$student_id = optional_param('student', -1, PARAM_INT);
$lower_threshold = optional_param('lower_threshold', 20, PARAM_FLOAT); // rate will be above this similarity
$upper_threshold = optional_param('upper_threshold', 100, PARAM_FLOAT);
$tool = optional_param('tool', 'jplag', PARAM_TEXT);
$rate_type = optional_param('rate_type', 'avg', PARAM_TEXT);
$display_mode = optional_param('display_mode', 'group', PARAM_TEXT); //either table (similar to JPlag style)  or group (similar to MOSS style)

// if the user is a student (does not have grade capability), he can only see the report on his assignment if allowed
$context = get_context_instance(CONTEXT_MODULE,$cmid);
$is_teacher = has_capability('mod/assignment:grade', $context);
if (!$is_teacher) {
    // check if he is allowed to see the assignment
    if (!has_capability('mod/assignment:submit', $context) ||
            !$DB->get_field('programming_plagiarism','auto_publish',array('courseid'=>$cmid))) {
        redirect($CFG->wwwroot,"You don't have permission to see this page");
    }
    $student_id = $USER->id;
}

if ($student_id > 0) {
    $display_mode = 'table';
}

if (!$course_module = get_coursemodule_from_id('assignment', $cmid)) {
    redirect($CFG->wwwroot, 'Invalid course module id');
}
$course = $DB->get_record('course',array('id'=>$course_module->course));
if (!$course) {
    redirect($CFG-->wwwroot,'Invalid course id');
}
require_login($course, true, $course_module);

$PAGE->set_url(new moodle_url('/plagiarism/programming/view.php',array('cmid'=>$cmid)));
assert($cmid!=null);

$select = "cmid=$cmid AND similarity1>=$lower_threshold AND similarity1<$upper_threshold AND detector='$tool'";
if ($student_id>0) {
    $select .= " AND (student1_id=$student_id OR student2_id=$student_id)";
}
$result = $DB->get_records_select('programming_result',$select,null,'similarity1 DESC');

create_student_name_lookup_table($result, $is_teacher, $student_names); // this will create the array id=>name in $student_names

if ($display_mode=='group') {
    $table = create_table_grouping_mode($result, $student_names,$cmid);
} else {
    $table = create_table_list_mode($result, $student_names,$cmid);
}

$header = get_string('result','plagiarism_programming');
$PAGE->set_title('Similarity result report');
$PAGE->set_heading($header);
$PAGE->navbar->add($header);
echo $OUTPUT->header();

$filter_forms = new programming_plag_result_form();
$filter_forms->set_data(array('cmid'=>$cmid,
    'student'=>$student_id,
    'lower_threshold'=>$lower_threshold,
    'tool'=>$tool,'rate_type'=>$rate_type,
    'display_mode'=>$display_mode));
$filter_forms->display();

echo html_writer::tag('div',get_string('chart_legend','plagiarism_programming'));
echo html_writer::tag('div', create_chart($cmid,$tool,$rate_type),array('class'=>'programming_result_chart'));
echo html_writer::tag('div',  html_writer::table($table), array('class'=>'programming_result_table'));
//echo html_writer::table($table);
echo $OUTPUT->footer();
