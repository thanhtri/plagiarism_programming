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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/reportlib.php');
require_login();

global $OUTPUT, $PAGE, $DB, $USER, $CFG;

// Processing of parameters.
$resultid = required_param('id', PARAM_INT); // ID in the plagiarism_programming_reslt table.
$anchor = optional_param('anchor', -1, PARAM_INT);
$resultrecord = $DB->get_record('plagiarism_programming_reslt', array(
    'id' => $resultid
));
$reportrec = $DB->get_record('plagiarism_programming_rpt', array(
    'id' => $resultrecord->reportid
));

$resultrecord = plagiarism_programming_transform_similarity_pair($resultrecord);

// Get the report directory.
$cmid = $reportrec->cmid;
$detector = $reportrec->detector;

require_once(__DIR__ . '/' . $detector . '_tool.php');

if ($detector == 'jplag') {
    $directory = jplag_tool::get_report_path($reportrec);
} else {
    $directory = moss_tool::get_report_path($reportrec);
}

// Create page context.
if (!$coursemodule = $DB->get_record('course_modules', array(
    'id' => $cmid
))) {
    redirect($CFG->wwwroot, 'Invalid course module id');
}
$course = $DB->get_record('course', array(
    'id' => $coursemodule->course
));
if (!$course) {
    redirect($CFG->wwwroot, 'Invalid course id');
}
require_login($course, true, $coursemodule);

// Authorisation: only teacher can see the names.
$context = context_module::instance($cmid);
$isteacher = require_capability('mod/assignment:grade', $context);

/* Students are not allowed to see any other code.
if (!$isteacher) {
    // Check if he is allowed to see the assignment.
    if (!has_capability('mod/assignment:submit', $context) || // Must have submission right to his assignment.
    !$DB->get_field('plagiarism_programming', 'auto_publish', array(
        'cmid' => $cmid
    ))) { // And permission to see the report.
        redirect($CFG->wwwroot, "You don't have permission to see this page");
    }

    if ($resultrecord->student1_id == $USER->id) {
        $student1 = get_string('yours', 'plagiarism_programming');
        $student2 = get_string('another', 'plagiarism_programming');
    } else if ($resultrecord->student2_id == $USER->id) {
        $student1 = get_string('yours', 'plagiarism_programming');
        $student2 = get_string('another', 'plagiarism_programming');
    } else {
        // This condition cannot happen unless users fabricate the link.
        redirect($CFG->wwwroot, "You can only see the report on your work");
    }
} else {

}
*/

// Teacher can see the students' name.
$users = $DB->get_records_list('user', 'id', array(
    $resultrecord->student1_id,
    $resultrecord->student2_id
), 'firstname,lastname,idnumber');
$student1 = isset($users[$resultrecord->student1_id]) ? fullname($users[$resultrecord->student1_id]) : $resultrecord->student1_id;
$student2 = isset($users[$resultrecord->student2_id]) ? fullname($users[$resultrecord->student2_id]) : $resultrecord->student2_id;

// End of authorization.

$title = get_string('comparison_title', 'plagiarism_programming');
$heading = get_string('comparison', 'plagiarism_programming');

$PAGE->set_pagelayout('base'); // Don't want the blocks to save space.
$PAGE->set_url(me());
$PAGE->set_title($title);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('similarity_report', 'plagiarism_programming'), "view.php?cmid=$cmid&tool=$reportrec->detector");
$PAGE->navbar->add($heading);
echo $OUTPUT->header();

// The top bar.
$averagesimilarity = ($resultrecord->similarity1 + $resultrecord->similarity2) / 2;
$content = html_writer::tag('div', get_string('and', '', array(
    'one' => $student1,
    'two' => $student2
)) . "($averagesimilarity%)", array(
    'class' => 'compare_header'
));

$result1 = plagiarism_programming_reconstruct_file($resultrecord->student1_id, $resultrecord->student2_id, $directory);
$result2 = plagiarism_programming_reconstruct_file($resultrecord->student2_id, $resultrecord->student1_id, $directory);
$sumarydata = plagiarism_programming_get_summary_data($result1['list'], $student1, $resultrecord->similarity1,
    $result2['list'], $student2, $resultrecord->similarity2);
// Placeholder for summary table, content will be rendered via javascript.
echo html_writer::tag('div', $content . "<div class='simiarity_table_holder'></div>", array(
    'name' => 'link',
    'frameborder' => '0',
    'width' => '40%',
    'class' => 'programming_result_comparison_top_left'
));

$content = '';
// If the user has the capability, add the box to mark as suspicious or normal.
if (has_capability('plagiarism/programming:markpairs', $context)) {
    $actions = array(
        'Y' => get_string('mark_suspicious', 'plagiarism_programming'),
        'N' => get_string('mark_nonsuspicious', 'plagiarism_programming')
    );
    $content .= html_writer::label(get_string('mark_select_title', 'plagiarism_programming'), 'action_menu') . ' ';
    $content .= html_writer::select($actions, 'mark', $resultrecord->mark, get_string('choosedots'), array(
        'id' => 'action_menu'
    ));
} else {
    $content .= html_writer::tag('div', get_string('caperror_markpairs', 'plagiarism_programming'));
}

$imgsrc = '';
if ($resultrecord->mark == 'Y') {
    $imgsrc = 'pix/suspicious.png';
} else if ($resultrecord->mark == 'N') {
    $imgsrc = 'pix/normal.png';
}
$content .= html_writer::empty_tag('img', array(
    'src' => $imgsrc,
    'id' => 'mark_image',
    'class' => 'programming_result_mark_img'
));

// Select the report history.
$similarityhistory = plagiarism_programming_get_student_similarity_history($resultrecord);
$reportselect = array();

global $USER;
// Get user's preferred language to transform time string.
setlocale(LC_TIME, $USER->lang);
foreach ($similarityhistory as $pair) {
    $reportselect[$pair->id] = strftime("%c", $pair->time_created);
}
$content .= '<br/><br/>';
$content .= html_writer::label(get_string('version'), 'report_version') . ' ';
$content .= html_writer::select($reportselect, 'report_version', $resultrecord->id, null, array(
    'id' => 'report_version'
));

echo html_writer::tag('div', "<div>$content</div>", array(
    'class' => 'programming_result_comparison_top_right'
));

// Separator.
echo html_writer::tag('div', '', array(
    'class' => 'programming_result_comparison_separator'
));
// Left panel.
echo html_writer::tag('div', $result1['content'], array(
    'class' => 'programming_result_comparison_bottom_left'
));

// Right panel.
echo html_writer::tag('div', $result2['content'], array(
    'class' => 'programming_result_comparison_bottom_right'
));

// Name lookup table for javascript.
$resultselect = "reportid=$reportrec->id "
    ."AND (student1_id='$resultrecord->student1_id' OR student1_id='$resultrecord->student2_id' "
    ."OR student2_id='$resultrecord->student1_id' OR student2_id='$resultrecord->student2_id')";
$result = $DB->get_records_select('plagiarism_programming_reslt', $resultselect);

$allnames = null;
plagiarism_programming_create_student_lookup_table($result, $isteacher, $allnames, $course->id);

// ID lookup table for javascript.
$resultidtable = array();
foreach ($result as $pair) {
    $std1 = max($pair->student1_id, $pair->student2_id);
    $std2 = min($pair->student1_id, $pair->student2_id);
    $resultidtable[$std1][$std2] = $pair->id;
}
$resultinfo = array(
    'id' => $resultid,
    'mark' => $resultrecord->mark,
    'student1' => $resultrecord->student1_id,
    'student2' => $resultrecord->student2_id
);

$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/compare_code.js',
    'requires' => array(
        'base',
        'overlay',
        'node',
        'json',
        'io',
        'datatable',
        'datatable-scroll'
    ),
    'strings' => array(
        array(
            'show_similarity_to_others',
            'plagiarism_programming'
        ),
        array(
            'history_char',
            'plagiarism_programming'
        ),
        array(
            'date',
            'moodle'
        )
    )
);
$PAGE->requires->js_init_call('M.plagiarism_programming.compare_code.init', array(
    $resultinfo,
    $allnames,
    $sumarydata,
    $resultidtable,
    $anchor
), true, $jsmodule);
echo $OUTPUT->footer();

/**
 * Returns the summary data.
 * @param Array $list1
 * @param String $student1
 * @param String $rate1
 * @param Array $list2
 * @param String $student2
 * @param String $rate2
 * @return string[][][]
 */
function plagiarism_programming_get_summary_data($list1, $student1, $rate1, $list2, $student2, $rate2) {
    // Header.
    $data = array();
    foreach ($list1 as $anchor => $portion) {
        $line1 = $portion['line'];
        $file1 = $portion['file'];
        $line2 = $list2[$anchor]['line'];
        $file2 = $list2[$anchor]['file'];

        $color = $portion['color'];

        $data[] = array(
            'color' => "#$color",
            'student1' => "<a style='color:#$color' class='similarity_link' href='sim_$anchor'>$file1 ($line1)</a>",
            'student2' => "<a style='color:#$color' class='similarity_link' href='sim_$anchor'>$file2 ($line2)</a>"
        );
    }
    $columns = array(
        array(
            'key' => 'color',
            'label' => ' '
        ),
        array(
            'key' => 'student1',
            'label' => "$student1 ($rate1%)"
        ),
        array(
            'key' => 'student2',
            'label' => "$student2 ($rate2%)"
        )
    );
    return array(
        'columns' => $columns,
        'data' => $data
    );
}
/**
 * REconstructs a file, I guess.
 * @param Number $studentid
 * @param Number $otherstudentid
 * @param String $dir
 * @return string[]|string[][][]|mixed[][][]
 */
function plagiarism_programming_reconstruct_file($studentid, $otherstudentid, $dir) {
    $codefile = $dir . '/' . $studentid;

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $content = file_get_contents($codefile);
    @$dom->loadHTML('<pre>' . $content . '</pre>');

    $pre = $dom->childNodes->item(1)->firstChild->firstChild; // Path: root->html->body->pre.

    $linenumber = 1;
    $currentfile = '';
    $portionlist = array();
    $node = $pre->firstChild;
    while ($node != null) {
        if ($node->nodeType == XML_TEXT_NODE) {
            $linenumber += substr_count($node->nodeValue, "\n"); // Count the line.
        } else if ($node->tagName == 'h3') {
            $currentfile = $node->nodeValue;
            $linenumber = 1;
        } else if ($node->tagName == 'span' && $node->getAttribute('type') == 'begin') {
            $sid = explode(',', $node->getAttribute('sid'));
            $key = array_search($otherstudentid, $sid);
            if ($key !== false) { // Matching portion.
                $anchors = explode(',', $node->getAttribute('anchor'));
                $anchor = $anchors[$key];
                $colors = explode(',', $node->getAttribute('color'));
                $color = $colors[$key];

                $font = $dom->createElement('font');
                $font->setAttribute('color', $color);
                $font->setAttribute('class', 'sim_' . $anchor);
                $font = $node->parentNode->insertBefore($font, $node);
                $sibling = $node->nextSibling;
                $startline = $linenumber;
                while (!plagiarism_programming_end_span_node($sibling, $otherstudentid)) {
                    if ($sibling->nodeType == XML_TEXT_NODE) {
                        $linenumber += substr_count($sibling->nodeValue, "\n");
                    }
                    $nextsibling = $sibling->nextSibling;
                    $font->appendChild($sibling);
                    $sibling = $nextsibling;
                }
                if (count($sid) == 1) { // Remove the mark if this portion has in common with only one student.
                    $node->parentNode->removeChild($node);
                    $sibling->parentNode->removeChild($sibling);
                } else { // If not, move the marks within the font.
                    $font->insertBefore($node, $font->firstChild);
                    $font->appendChild($sibling);
                }
                $node = $font;

                $portionlist[$anchor] = array(
                    'file' => $currentfile,
                    'line' => "$startline-$linenumber",
                    'color' => $color
                );
            }
        }
        $node = $node->nextSibling;
    }
    return array(
        'list' => $portionlist,
        'content' => $dom->saveHTML()
    );
}

/**
 * Some YUI things.
 * @param Object $node YUI Node
 * @param Number $studentid
 * @return boolean
 */
function plagiarism_programming_end_span_node($node, $studentid) {
    if ($node->nodeType == XML_ELEMENT_NODE && $node->tagName == 'span' && $node->getAttribute('type') == 'end') {
        $endsid = explode(',', $node->getAttribute('sid'));
        return in_array($studentid, $endsid);
    } else {
        return false;
    }
}
