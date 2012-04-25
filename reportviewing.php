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
 * Filter the JPlag report for viewing
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include __DIR__.'/../../config.php';
include __DIR__.'/reportlib.php';
include __DIR__.'/jplag_tool.php';
global $OUTPUT;

// extract the link component first
$components = analyse_report_link();
$cmid = $components['cmid'];
$file = $components['file'];

// get the path of the report
$jplag_tool = new jplag_tool();
$filename = $jplag_tool->get_report_path($cmid).DIRECTORY_SEPARATOR.$file;

$file_handle = fopen($filename, 'r');
$content = fread($file_handle, filesize($filename));
if (substr($file,-4)=='html') {	// an html file
    modify_content($content, $cmid, $file);
} elseif (substr($file, -3)=='gif') {
    header('Content-Type: image/gif');
}
echo $content;

function modify_content(&$content,$cmid,$filename) {
    global $CFG,$USER;
    $context = get_context_instance(CONTEXT_MODULE,$cmid);
    if (has_capability('mod/assignment:grade', $context, $USER->id)) { // teacher
        if ($filename=='index.html') {
            replace_userid_with_studentid_and_name($content);
        } elseif (substr($filename, -9)=='link.html') {
            replace_userid_with_student_id_and_name_link($content);
        } elseif (substr($filename, -8)=='top.html') {
            replace_userid_with_student_id_and_name_top($content);
        }
    } else { // student
        if ($filename=='index.html') {
            drop_other_students($content);
        } elseif (substr($filename, -9)=='link.html') {
            replace_your_userid_link($content);
        } elseif (substr($filename, -8)=='top.html') {
            replace_your_userid_top($content);
        }
    }
}

function replace_userid_with_studentid_and_name(&$content) {
    global $USER, $DB;

    // replace the left handside
    $matches_left = '/<TD BGCOLOR=#.{6}>([0-9]*)<\/TD>/';
    preg_match_all($matches_left,$content,$matches);
    $find = $matches[0];
    $user_ids = $matches[1];
    $users = $DB->get_records_list('user','id',$user_ids,'firstname,lastname,idnumber');
    $num = count($user_ids);
    foreach ($find as $key=>$replaced_string) {
        $user = $users[$user_ids[$key]];
        $replace[$key] = str_replace($user_ids[$key], $user->firstname.' '.$user->lastname, $replaced_string);
    }
    $content = str_replace($find, $replace, $content);

    // replace the right handside
    $matches_right = '/<A HREF="match[0-9]+.html">([0-9]+)<\/A>/';
    preg_match_all($matches_right, $content, $matches);
    $find = $matches[0];
    $user_ids = $matches[1];
    $users = $DB->get_records_list('user','id',$user_ids,'firstname,lastname,idnumber');

    $replace = array();
    foreach ($find as $key=>$replaced_string) {
        $user = $users[$user_ids[$key]];
        $replace[$key] = str_replace($user_ids[$key], $user->firstname.' '.$user->lastname, $replaced_string);
    }
    $content = str_replace($find, $replace, $content);
}

function drop_other_students(&$content) {
    global $USER;
    $userid = $USER->id;
    $pattern = '/<TR><TD BGCOLOR=#.{6}>([0-9]+)<\/TD>.*?<A HREF="match[0-9]+.html">([0-9]+)<\/A>.*?<\/TR>/';
    preg_match_all($pattern, $content, $matches);
    $replaced = array();
    $matches = $matches[0];
    foreach ($matches as $key=>$val) {
        if (strpos($val, ">$userid<")===FALSE) {
            $replaced[$key] = '';
        } else {
            $replaced[$key] = str_replace(">$userid<", '>Yours<', $val);
        }
    }
    $content = str_replace($matches, $replaced, $content);
}

function replace_userid_with_student_id_and_name_link(&$content) {
    global $DB;
    $pattern = '/<H3 ALIGN="center">Matches for ([0-9]*) & ([0-9]*)<\/H3>/';
    preg_match($pattern, $content, $matches);
    $users = $DB->get_records_list('user','id',array($matches[1],$matches[2]),'firstname,lastname,idnumber');
    $user1 = $users[$matches[1]];
    $student1 = $user1->firstname.' '.$user1->lastname;
    $user2 = $users[$matches[2]];
    $student2 = $user2->firstname.' '.$user2->lastname;
    $replaced = "<H3 ALIGN=\"center\">Matches for $student1 & $student2</H3>";
    $content = str_replace($matches[0], $replaced, $content);
}

function replace_userid_with_student_id_and_name_top(&$content) {
    global $DB;
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

function replace_your_userid_link(&$content) {
    global $USER;
    $pattern = '/<H3 ALIGN="center">Matches for ([0-9]*) & ([0-9]*)<\/H3>/';
    preg_match($pattern, $content, $matches);
    $id1 = $matches[1];
    $id2 = $matches[2];
    if ($USER->id==$matches[1]) {
        $replaced = "<H3 ALIGN=\"center\">Matches for yours & Student#$id2</H3>";
    }
    if ($USER->id==$matches[2]) {
        $replaced = "<H3 ALIGN=\"center\">Matches for Student#$id1 & yours</H3>";
    }
    $content = str_replace($matches[0], $replaced, $content);
}

function replace_your_userid_top(&$content) {
    global $USER;
    $pattern = '/<TR><TH><TH>([0-9]*) \([0-9]*\.[0-9]*%\)<TH>([0-9]*) \([0-9]*\.[0-9]*%\)<TH>/';
    preg_match($pattern, $content, $matches);
    $replaced = $matches[0];
    if ($USER->id==$matches[1]) {
        $replaced = str_replace('<TH><TH>'.$USER->id, '<TH><TH>Yours', $replaced);
    } else {
        $replaced = str_replace('<TH>'.$USER->id, '<TH>Yours', $replaced);
    }

    $content = str_replace($matches[0], $replaced, $content);
}
