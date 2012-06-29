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
 * Page to compare two assignment, when the user click on the similarity percentage
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/reportlib.php');

global $OUTPUT, $PAGE, $DB, $USER, $CFG;

//----------------------------------------Processing of parameters----------------------------------------------//
$result_id = required_param('id', PARAM_INT); //id in the plagiarism_programming_reslt table
$anchor = optional_param('anchor', -1, PARAM_INT);
$result_record = $DB->get_record('plagiarism_programming_reslt', array('id'=>$result_id));
$report_rec = $DB->get_record('plagiarism_programming_rpt', array('id'=>$result_record->reportid));

// get the report directory
$cmid = $report_rec->cmid;
$detector = $report_rec->detector;
require_once(__DIR__.'/'.$detector.'_tool.php');
if ($detector=='jplag') {
    $directory = jplag_tool::get_report_path($report_rec);
} else {
    $directory = moss_tool::get_report_path($report_rec);
}
//-------------------------------------end parameter processing--------------------------------------------------//

// create page context
if (!$course_module = get_coursemodule_from_id('assignment', $cmid)) {
    redirect($CFG->wwwroot, 'Invalid course module id');
}
$course = $DB->get_record('course', array('id'=>$course_module->course));
if (!$course) {
    redirect($CFG->wwwroot, 'Invalid course id');
}
require_login($course, true, $course_module);

//------------------------------------- authorisation: only teacher can see the names -----------------------------//
$context = get_context_instance(CONTEXT_MODULE, $cmid);
$is_teacher = has_capability('mod/assignment:grade', $context);
if (!$is_teacher) {
    // check if he is allowed to see the assignment
    if (!has_capability('mod/assignment:submit', $context) || // must have submission right to his assignment
        !$DB->get_field('plagiarism_programming', 'auto_publish', array('cmid'=>$cmid))) { // or permission to see the report
        redirect($CFG->wwwroot, "You don't have permission to see this page");
    }

    if ($result_record->student1_id==$USER->id) {
        $student1 = 'yours';
        $student2 = 'another\'s';
    } else if ($result_record->student2_id==$USER->id) {
        $student1 = 'another\'s';
        $student2 = 'yours';
    } else {
        // this condition cannot happen unless users fabricate the link
        redirect($CFG->wwwroot, "You can only see the report on your work");
    }
} else {
    // teacher can see the students' name
    $users = $DB->get_records_list('user', 'id', array($result_record->student1_id, $result_record->student2_id),
        'firstname,lastname,idnumber');
    $user1 = $users[$result_record->student1_id];
    $student1 = $user1->firstname.' '.$user1->lastname;
    $user2 = $users[$result_record->student2_id];
    $student2 = $user2->firstname.' '.$user2->lastname;
}
//---------------------------------end autorisation--------------------------------------------------------------------//

$title = get_string('comparison_title', 'plagiarism_programming');
$heading = get_string('comparison', 'plagiarism_programming');

$PAGE->set_url(me());
$PAGE->set_title($title);
$PAGE->set_heading($heading);
$PAGE->navbar->add($heading);
echo $OUTPUT->header();

// the top bar
$average_similarity = ($result_record->similarity1+$result_record->similarity2)/2;
$content = html_writer::tag('div', "Similarities between $student1 and $student2 "
    ."($average_similarity%)", array('class'=>'compare_header'));

$result1 = reconstruct_file($result_record->student1_id, $result_record->student2_id, $directory);
$result2 = reconstruct_file($result_record->student2_id, $result_record->student1_id, $directory);
$table = construct_similarity_summary_table($result1['list'], $student1, $result_record->similarity1,
                                            $result2['list'], $student2, $result_record->similarity2);
echo html_writer::tag('div', $content."<div class='simiarity_table_holder'>$table</div>",
        array('name'=>'link', 'frameborder'=>'0', 'width'=>'40%',
        'class'=>'programming_result_comparison_top_left'));

$content='';
if ($is_teacher) { // only teachers can mark the suspicious pairs, so add the select box
    $actions = array(
        'Y'=>get_string('mark_suspicious', 'plagiarism_programming'),
        'N'=>get_string('mark_nonsuspicious', 'plagiarism_programming')
    );
    $content .= html_writer::label(get_string('mark_select_title', 'plagiarism_programming'), 'action_menu').' ';
    $content .= html_writer::select($actions, 'mark', $result_record->mark, 'Action...', array('id'=>'action_menu'));
}
$img_src = '';
if ($result_record->mark=='Y') {
    $img_src = 'pix/suspicious.png';
} else if ($result_record->mark=='N') {
    $img_src = 'pix/normal.png';
}
$content .= html_writer::empty_tag('img', array('src'=>$img_src, 'id'=>'mark_image', 'class'=>'programming_result_mark_img'));

// select the report history
$similarity_history = get_student_similarity_history($result_record->student1_id, $result_record->student2_id, $cmid, $detector);
$report_select = array();
foreach ($similarity_history as $pair) {
    $report_select[$pair->id] = date('d M h.i A', $pair->time_created);
}
$content .= '<br/><br/>';
$content .= html_writer::label(get_string('version'), 'report_version').' ';
$content .= html_writer::select($report_select, 'report_version', $result_record->id, null, array('id'=>'report_version'));

echo html_writer::tag('div', "<div>$content</div>", array('class'=>'programming_result_comparison_top_right'));

// separator
echo html_writer::tag('div', '', array('class'=>'programming_result_comparison_separator'));
// left panel
echo html_writer::tag('div', $result1['content'], array('class'=>'programming_result_comparison_bottom_left'));

// right panel
echo html_writer::tag('div', $result2['content'], array('class'=>'programming_result_comparison_bottom_right'));

//----- name lookup table for javascript--------
$result_select = "reportid=$report_rec->id ".
    "AND (student1_id=$result_record->student1_id OR student1_id=$result_record->student2_id ".
    "OR student2_id=$result_record->student1_id OR student2_id=$result_record->student2_id)";
$result = $DB->get_records_select('plagiarism_programming_reslt', $result_select);

$all_names = null;
create_student_name_lookup_table($result, $is_teacher, $all_names);

//----------id lookup table for javascript-----------------------
$result_id_table = array();
foreach ($result as $pair) {
    $std1 = max($pair->student1_id, $pair->student2_id);
    $std2 = min($pair->student1_id, $pair->student2_id);
    $result_id_table[$std1][$std2] = $pair->id;
}
$result_info = array('id'=>$result_id, 'mark'=>$result_record->mark, 'student1'=>$result_record->student1_id,
    'student2'=>$result_record->student2_id);

$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/compare_code.js',
    'requires' => array('base', 'overlay', 'node', 'json', 'io-base'),
    'strings' => array(
        array('show_similarity_to_others', 'plagiarism_programming'),
        array('history_char', 'plagiarism_programming'),
        array('date', 'moodle')
     )
);
$PAGE->requires->js_init_call('M.plagiarism_programming.compare_code.init',
    array($result_info, $all_names, $result_id_table, $anchor), true, $jsmodule);
echo $OUTPUT->footer();

function construct_similarity_summary_table($list1, $student1, $rate1, $list2, $student2, $rate2) {
    // header
    $rows = '<table>';
    $rows .="<thead><tr><th></th><th>$student1 ($rate1%)</th><th>$student2 ($rate2%)</th></tr></thead>";
    $rows .= '<tbody>';
    foreach ($list1 as $anchor => $portion) {
        $line1 = $portion['line'];
        $file1 = $portion['file'];
        $line2 = $list2[$anchor]['line'];
        $file2 = $list2[$anchor]['file'];

        $color = $portion['color'];
        $rows .= "<tr><td bgcolor='#$color'></td><td><a style='color:#$color' class='similarity_link' href='sim_$anchor'>"
            ."$file1 ($line1)</a></td><td><a style='color:#$color' class='similarity_link' "
            ."href='sim_$anchor'>$file2 ($line2)</a></td></tr>";
    }
    $rows .= '</tbody></table>';
    return $rows;
}

function reconstruct_file($student_id, $other_student_id, $dir) {
    $code_file = $dir.'/'.$student_id;

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $content = file_get_contents($code_file);
    @$dom->loadHTML('<pre>'.$content.'</pre>');

    $pre = $dom->childNodes->item(1)->firstChild->firstChild; // root->html->body->pre

    $line_no = 1;
    $current_file = '';
    $portion_list = array();
    $node = $pre->firstChild;
    while ($node!=null) {
        if ($node->nodeType==XML_TEXT_NODE) {
            $line_no += substr_count($node->nodeValue, "\n");  // count the line
        } else if ($node->tagName=='h3') {
            $current_file = $node->nodeValue;
            $line_no = 1;
        } else if ($node->tagName=='span' && $node->getAttribute('type')=='begin') {
            $sid = explode(',', $node->getAttribute('sid'));
            $key = array_search($other_student_id, $sid);
            if ($key!==false) { // matching portion
                $anchors = explode(',', $node->getAttribute('anchor'));
                $anchor = $anchors[$key];
                $colors = explode(',',  $node->getAttribute('color'));
                $color = $colors[$key];

                $font = $dom->createElement('font');
                $font->setAttribute('color', $color);
                $font->setAttribute('class', 'sim_'.$anchor);
                $font = $node->parentNode->insertBefore($font, $node);
                $sibling = $node->nextSibling;
                $start_line = $line_no;
                while (!end_span_node($sibling, $other_student_id)) {
                    if ($sibling->nodeType==XML_TEXT_NODE) {
                        $line_no += substr_count($sibling->nodeValue, "\n");
                    }
                    $next_sibling = $sibling->nextSibling;
                    $font->appendChild($sibling);
                    $sibling = $next_sibling;
                }
                if (count($sid)==1) { // remove the mark if this portion has in common with only one student
                    $node->parentNode->removeChild($node);
                    $sibling->parentNode->removeChild($sibling);
                } else { // if not, move the marks within the font
                    $font->insertBefore($node, $font->firstChild);
                    $font->appendChild($sibling);
                }
                $node = $font;

                $portion_list[$anchor] = array('file'=>$current_file, 'line'=>"$start_line-$line_no", 'color'=>$color);
            }
        }
        $node = $node->nextSibling;
    }
    return array('list'=>$portion_list, 'content'=>$dom->saveHTML());
}

function end_span_node($node, $student_id) {
    if ($node->nodeType==XML_ELEMENT_NODE &&
           $node->tagName=='span' &&
           $node->getAttribute('type')=='end') {
        $end_sid = explode(',', $node->getAttribute('sid'));
        return in_array($student_id, $end_sid);
    } else {
        return false;
    }
}
