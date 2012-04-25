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
 * View to compare two assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once __DIR__.'/../../config.php';
include_once __DIR__.'/constants.php';

global $OUTPUT, $PAGE, $DB, $USER, $CFG;

// get the result information first
$result_id = required_param('id', PARAM_INT); //id in the programming_result table
$result_record = $DB->get_record('programming_result',array('id'=>$result_id));
$cmid = $result_record->cmid;

// create page context
if (!$course_module = get_coursemodule_from_id('assignment', $cmid)) {
    redirect($CFG->wwwroot, 'Invalid course module id');
}
$course = $DB->get_record('course',array('id'=>$course_module->course));
if (!$course) {
    redirect($CFG-->wwwroot,'Invalid course id');
}
require_login($course, true, $course_module);

// authorisation
$context = get_context_instance(CONTEXT_MODULE,$cmid);
$is_teacher = has_capability('mod/assignment:grade', $context);
if (!$is_teacher) {
    // check if he is allowed to see the assignment
    if (!has_capability('mod/assignment:submit', $context) || // must have submission right to his assignment
        !$DB->get_field('programming_plagiarism','auto_publish',array('courseid'=>$cmid))) { // must have permission to see the report
        redirect($CFG->wwwroot,"You don't have permission to see this page");
    }
    
    if ($result_record->student1_id==$USER->id) {
        $student1 = 'Yours';
        $student2 = 'Another\'s';
    } elseif ($result_record->student2_id==$USER->id) {
        $student1 = 'Another\'s';
        $student2 = 'Yours';
    } else {
        redirect($CFG->wwwroot,"You can only see the report on your work"); // this condition cannot happen unless users fabricate the link
    }
    $student_id = $USER->id;
} else {
    // teacher can see the students' name
    $users = $DB->get_records_list('user','id',array($result_record->student1_id,$result_record->student2_id),'firstname,lastname,idnumber');
    $user1 = $users[$result_record->student1_id];
    $student1 = $user1->firstname.' '.$user1->lastname;
    $user2 = $users[$result_record->student2_id];
    $student2 = $user2->firstname.' '.$user2->lastname;
}

// strip .html
$name_no_ext = substr($result_record->comparison,0,-5);

$title = get_string('comparison_title',PLAGIARISM_PROGRAMMING);
$heading = get_string('comparison',PLAGIARISM_PROGRAMMING);

$PAGE->set_url(me());
$PAGE->set_title($title);
$PAGE->set_heading($heading);
$PAGE->navbar->add($heading);
echo $OUTPUT->header();

$detector =  $result_record->detector;
$detector_class_name = $detector."_tool";
include_once __DIR__.'/'.$detector_class_name.'.php';
$tool = new $detector_class_name();
$report_dir = $tool->get_report_path($cmid);

// the top bar
$content = html_writer::tag('h1', "Similarities between $student1 and $student2 ($result_record->similarity1%)");
$content .= html_writer::empty_tag('br');

$actions = array(
    'Y'=>get_string('mark_suspicious',PLAGIARISM_PROGRAMMING),
    'N'=>  get_string('mark_nonsuspicious',PLAGIARISM_PROGRAMMING)
);
$content .= html_writer::select($actions, 'mark', $result_record->mark,'Action...',array('id'=>'action_menu'));
$content .= html_writer::empty_tag('img',array('src'=>'','id'=>'mark_image','class'=>'programming_result_mark_img'));

echo html_writer::tag('div',$content,array('name'=>'link','frameborder'=>'0','width'=>'40%','class'=>'programming_result_comparison_top_left'));
// the link bar
if ($detector=='jplag') {
    get_top_file_content_jplag($report_dir.'/'.$name_no_ext.'-top.html', $content);
} else {
    get_top_file_content_moss($report_dir.'/'.$name_no_ext.'-top.html', $content);
}
echo html_writer::tag('div',$content,array('class'=>'programming_result_comparison_top_right'));

echo html_writer::tag('div','',array('class'=>'programming_result_comparison_separator'));
// left panel
get_code_file_content($report_dir.'/'.$name_no_ext.'-0.html',$content);
echo html_writer::tag('div',$content,array('class'=>'programming_result_comparison_bottom_left'));

// right panel
get_code_file_content($report_dir.'/'.$name_no_ext.'-1.html',$content);
echo html_writer::tag('div',$content,array('class'=>'programming_result_comparison_bottom_right'));

$PAGE->requires->yui2_lib('selector');
$PAGE->requires->yui2_lib('event');
$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/compare_code.js',
    'strings' => array()
);
$PAGE->requires->js_init_call('M.plagiarism_programming.compare_code.init',array('id'=>$result_id),true,$jsmodule);
echo $OUTPUT->footer();

/** Get content of the header file
 *  @param $filename: full path to the link file
 *  @param $content:  reference to the returned content.
 *         This param will hold the content of the file after the call
 *  @param $show_name: show the name of the student
 */
function get_top_file_content_jplag($filename,&$content,$show_name=true) {
    global $DB;

    // read the file first
    $content = file_get_contents($filename);
    strip_tag_content($content, 'TABLE');

    $pattern = '/<TR><TH><TH>([0-9]*) \([0-9]*\.[0-9]*%\)<TH>([0-9]*) \([0-9]*\.[0-9]*%\)<TH>/';
    preg_match($pattern, $content,$matches);
    $users = $DB->get_records_list('user','id',array($matches[1],$matches[2]),'firstname,lastname,idnumber');
    $user1 = $users[$matches[1]];
    $student1 = $user1->firstname.' '.$user1->lastname;
    $user2 = $users[$matches[2]];
    $student2 = $user2->firstname.' '.$user2->lastname;
    $replaced = $matches[0];
    $replaced = str_replace('<TH><TH>'.$user1->id, '<TH><TH>'.$student1, $replaced);
    $replaced = str_replace('<TH>'.$user2->id, '<TH>'.$student2, $replaced);
    $content = str_replace($matches[0], $replaced, $content);
}

function get_top_file_content_moss($filename,&$content,$show_name=true) {
    global $DB;

    $content = file_get_contents($filename);
    strip_tag_content($content, 'TABLE');
    
    $pattern = '/<TH>([0-9]+)\//';
    preg_match_all($pattern, $content, &$matches); // matches[0] contains the whole pattern, matches[1] contain userids (number in the brackets)
    $user_ids = $matches[1];
    $users = $DB->get_records_list('user','id',$user_ids,'firstname,lastname,idnumber');
    $searched = $matches[0];
    $replace = array();
    $user1 = $users[$user_ids[0]];
    $replace[0] = str_replace($user_ids[0], "$user1->firstname $user1->lastname", $searched[0]);
    $user2 = $users[$user_ids[1]];
    $replace[1] = str_replace($user_ids[1], "$user2->firstname $user2->lastname", $searched[0]);
    $content = str_replace($searched, $replace, $content);
}

function get_code_file_content($filename,&$content) {
    $content = file_get_contents($filename);
    strip_tag_content($content, 'PRE');
}

function strip_tag_content(&$content,$tag) {
    $begin_pos = strpos($content, "<$tag");
    $end_pos = strrpos($content, "</$tag>");
    $content = substr($content,$begin_pos,$end_pos-strlen($content)+strlen($tag)+3);
}