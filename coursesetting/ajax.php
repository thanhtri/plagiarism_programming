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
 * Ajax calls for selecting courses having the plugin enabled
 *
 * @package    plagiarism programming
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once dirname(__FILE__).'/../../../config.php';
include_once dirname(__FILE__).'/../constants.php';

$task = optional_param('task', 'getcourse', PARAM_TEXT);

if ($task=='getcourse') {
    $page = optional_param('page', 0, PARAM_INT);
    get_all_courses($page);
} elseif ($task=='setenabledlevel') {
    $level = optional_param('level', 'global', PARAM_TEXT);
    enable_level($level);
} elseif ($task=='enablecourse') {
    $id = required_param('id', PARAM_INT);
    enable_code_plagiarism_checking_for_course($id);
} elseif ($task=='disablecourse') {
    $id = required_param('id', PARAM_INT);
    disable_code_plagiarism_seting_for_course($id);
}


function get_all_courses($page) {
    global $DB;
    $sql = 'Select course.id, course.fullname, course.idnumber, course.shortname, enabled_course.course is_enabled '.
        'From {course} course LEFT JOIN {programming_course_enabled} enabled_course On course.id=enabled_course.course '.
        'Where course.category=1';
    $courses = $DB->get_records_sql($sql);
    $result = array();
    foreach ($courses as $course) {
        $result[] = array(
            'id'=>$course->id,
            'name'=>$course->fullname,
            'shortname'=>$course->shortname,
            'code'=>$course->idnumber,
            'enabled' => $course->is_enabled? 1:0
        );
    }
    echo json_encode($result);
}

function enable_level($level) {
    global $DB;
    // just two values global and course are accepted
    $level = ($level=='global')?'global':'course';
    set_config('level_enabled', $level, PLAGIARISM_PROGRAMMING);
}

function enable_code_plagiarism_checking_for_course($id) {
    global $DB;
    $course = $DB->get_record('programming_course_enabled',array('course'=>$id));
    if (!$course) {
        $course_enabled = new stdClass();
        $course_enabled->course = $id;
        $DB->insert_record('programming_course_enabled',$course_enabled);
        echo json_encode(array('status'=>'OK'));
    }
}

function disable_code_plagiarism_seting_for_course($id) {
    global $DB;
    $DB->delete_records('programming_course_enabled',array('course'=>$id));
    echo json_encode(array('status'=>'OK'));
}