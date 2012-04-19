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
 * Transform the MOSS report
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include dirname(__FILE__).'/../../config.php';
global $DB;
$link_component = substr($_SERVER['PATH_INFO'], 1); // strip the first slash
$components = explode('/', $link_component);

// first component is the moss id
$moss_id = $components[0];

// select the link from the database
$moss_record = $DB->get_record('programming_moss',array('id'=>$moss_id));
$result_link = $moss_record->resultlink;
if (substr($result_link,-1)!='/') {
    $result_link .= '/';
}
$result_link .= $components[1];

$role = check_capability($moss_record->settingid);
if (!$role) {
	echo "Sorry, you don't have enough permission to see this page.";
	exit();
}

$content = file_get_contents($result_link);
$valid = true;
if (substr($result_link,-1)=='/' || substr($result_link, -10)=='index.html') {
	moss_replace_link_to_local($content,$moss_record,$components);
	if ($role=='teacher') {
		replace_file_path_with_student_name($content);
	} else {
		replace_your_userid_index($content);
	}
} elseif (substr($result_link, -8)=='top.html') {
	moss_replace_link_to_local($content,$moss_record,$components);
	$valid = replace_file_path_with_student_name($content,$role=='teacher');
} elseif (in_array(substr($result_link, -7),array('-0.html','-1.html'))) {
	replace_file_path_with_student_name($content,$role=='teacher');
} else {
}
if (!$valid) {
	exit();
}
echo $content;

function moss_replace_link_to_local(&$content,$moss_record,$components) {
global $CFG, $DB;
// replace the links
$search = $moss_record->resultlink;
if (substr($search,-1)!='/') {
	$search .= '/';
}
$content = str_replace($search, '', $content);
}

function replace_file_path_with_student_name(&$content,$teachermode=true) {
global $CFG, $DB, $USER;
// replace the file name with student name
$search = '/'.str_replace('/', '\\/', $CFG->dataroot).'\/temp\/plagiarism_programming\/moss[0-9]*\/([0-9]*)\//';
preg_match_all($search,$content,$matches);
$user_ids = $matches[1];
if ($teachermode) {
	$users = $DB->get_records_list('user','id',$user_ids,'firstname,lastname,idnumber');
}

foreach ($user_ids as $key=>$val) {
	if ($teachermode) {
		$user = $users[$val];
		$replace[$key] = $user->firstname.' '.$user->lastname;
	} else {
		if ($val == $USER->id) {
			$replace[$key] = 'Yours';
			$file_belonged = true;
		} else {
			$replace[$key] = 'Student #'.$val;
		}
	}
}
$content = str_replace($matches[0], $replace, $content);
return $teachermode || $file_belonged;
}

function replace_your_userid_index(&$content) {
global $CFG,$USER;
$root_pattern = str_replace('/', '\\/', $CFG->dataroot);
$search = '/<TR><TD>.*?('.$root_pattern.'\/temp\/plagiarism_programming\/moss[0-9]*\/([0-9]*)\/) .*?('.$root_pattern.'\/temp\/plagiarism_programming\/moss[0-9]*\/([0-9]*)\/) .*?<TD ALIGN=right>[0-9]+/s';
preg_match_all($search,$content,$matches);
$user_ids1 = $matches[2];
$user_ids2 = $matches[4];
$numline = count($user_ids1);
for ($i=0;$i<$numline;$i++) {
	if ($USER->id==$user_ids1[$i]) {
		$find[]= $matches[1][$i];
		$replace[]= 'Yours';
		$find[]= $matches[3][$i];
		$replace[]= 'Student #'.$user_ids2[$i];
	} elseif ($USER->id==$user_ids2[$i]) {
		$find[]= $matches[1][$i];
		$replace[]= 'Student #'.$user_ids2[$i];
		$find[]= $matches[3][$i];
		$replace[]= 'Yours';
	} else { // drop the whole line
		$line_to_drop[] = $matches[0][$i];
	}
}
$content = str_replace($line_to_drop, '', $content);
$content = str_replace($find, $replace, $content);
}

// check the ability to see the assignment
function check_capability($settingid) {
global $DB;
$setting = $DB->get_record('programming_plagiarism',array('id'=>$settingid));
if ($setting) {
	include_once $CFG->dirroot.'/lib/accesslib.php';
	$cmid = $setting->courseid;
	$context = get_context_instance(CONTEXT_MODULE,$cmid);
	if (has_capability('mod/assignment:grade', $context, $USER->id)) { // teacher
		return 'teacher';
	} elseif (has_capability('mod/assignment:view', $context)) {
		return 'student';
	} else {
		return FALSE;
	}
} else {
	return FALSE;
}
}

