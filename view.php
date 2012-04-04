<?php
include_once dirname(__FILE__).'/../../config.php';
include_once dirname(__FILE__).'/constants.php';
include_once dirname(__FILE__).'/programming_plag_result_form.php';
include_once dirname(__FILE__).'/report_display.php';

global $DB, $PAGE, $OUTPUT, $CFG;

$cmid = optional_param('cmid', null, PARAM_INT);
$student_id = optional_param('student', NULL, PARAM_INT);
$filter_threshold = optional_param('filter_threshold', 20, PARAM_FLOAT); // rate will be above this similarity
$tool = optional_param('tool', 'jplag', PARAM_TEXT);
$rate_type = optional_param('rate_type', 'avg', PARAM_TEXT);
$display_mode = optional_param('display_mode', 'group', PARAM_TEXT); //either table (similar to JPlag style)  or group (similar to MOSS style)

if (!$course_module = get_coursemodule_from_id('assignment', $cmid)) {
    redirect($CFG->wwwroot, 'Invalid id');
}
$course = $DB->get_record('course',array('id'=>$course_module->course));
if (!$course) {
    redirect($CFG-->wwwroot,'Invalid id');
}

$PAGE->set_url(new moodle_url('/plagiarism/programming/view.php',array('cmid'=>$cmid)));
require_login($course, true, $course_module);
assert($cmid!=null);

$select = "cmid=$cmid AND similarity1>=$filter_threshold AND detector='$tool'";
if ($student_id) {
    $select .= " AND (student1_id=$student_id OR student2_id=$student_id)";
}
$result = $DB->get_records_select('programming_result',$select,null,'similarity1 DESC');

$similarity_table = array();
$student_names = array();

foreach ($result as $pair) {
    // make sure student1 id > student2 id to avoid repetition latter
    $student1 = max($pair->student1_id,$pair->student2_id);
    $student2 = min($pair->student1_id,$pair->student2_id);
    
    $similarity_table[$student1][$student2] = array('rate'=>$pair->similarity1,'file'=>$pair->comparison);
    $similarity_table[$student2][$student1] = array('rate'=>$pair->similarity1,'file'=>$pair->comparison);
    $student_names[$student1] = $student1;
    $student_names[$student2] = $student2;
}

// replace the students' id with real name if it's the lecturer
$context = get_context_instance(CONTEXT_MODULE,$cmid);
if (has_capability('mod/assignment:grade', $context)) {
    $ids = array_keys($student_names);
    $students = $DB->get_records_list('user','id',$ids,null,'id,firstname,lastname');
    foreach ($students as $student) {
        $student_names[$student->id] = $student->firstname.' '.$student->lastname;
    }
}

if ($display_mode=='group') {
    $table = create_table_grouping_mode($similarity_table, $student_names,$cmid);
} else {
    $table = create_table_list_mode($result, $student_names,$cmid);
}



$header = get_string('result',PLAGIARISM_PROGRAMMING);
$PAGE->set_title('JPlag report');
$PAGE->set_heading($header);
$PAGE->navbar->add($header);
echo $OUTPUT->header();

$filter_forms = new programming_plag_result_form(array('cmid'=>$cmid,
    'student_id'=>$student_id,'filter_threshold'=>$filter_threshold,'tool'=>$tool,'rate_type'=>$rate_type,'display_mode'=>$display_mode));
$filter_forms->display();

echo html_writer::tag('div', create_chart($result),array('class'=>'programming_result_chart'));

echo html_writer::table($table);
echo $OUTPUT->footer();
