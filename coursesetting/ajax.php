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
 * @package plagiarism programming
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/course_selection_form.php');

define('AJAX_SCRIPT', true);
global $PAGE, $USER;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");
require_sesskey();

$task = optional_param('task', 'getcourse', PARAM_TEXT);

if ($task == 'getcourse') {
    $page = optional_param('page', 1, PARAM_INT);
    $category = optional_param('category', 0, PARAM_INT);
    $name = optional_param('name', '', PARAM_TEXT);
    plagiarism_programming_search_courses($page, $category, $name);
} else if ($task == 'setenabledlevel') {
    $level = optional_param('level', 'global', PARAM_TEXT);
    plagiarism_programming_enable_level($level);
} else if ($task == 'enablecourse') {
    $id = required_param('id', PARAM_INT);
    plagiarism_programming_enable_course($id);
} else if ($task == 'disablecourse') {
    $id = required_param('id', PARAM_INT);
    plagiarism_programming_disable_course($id);
} else if ($task == 'getcategory') {
    $categorytree = plagiarism_programming_get_course_categories();
    $options = plagiarism_programming_create_course_category_select($categorytree, 0);
    $options = array(
        '' => 'All'
    ) + $options;
    echo html_writer::select($options, 'category_select');
}

function plagiarism_programming_search_courses($page, $category = 0, $name = '') {
    ob_start();
    $form = new course_selection_form($page, $category, $name);
    $form->display();
    $html = ob_get_clean();
    echo $html;
}

function plagiarism_programming_enable_level($level) {
    // Just two values global and course are accepted.
    $level = ($level == 'global') ? 'global' : 'course';
    set_config('level_enabled', $level, 'plagiarism_programming');
}

function plagiarism_programming_enable_course($id) {
    global $DB;
    $course = $DB->get_record('plagiarism_programming_cours', array(
        'course' => $id
    ));
    if (! $course) {
        $courseenabled = new stdClass();
        $courseenabled->course = $id;
        $DB->insert_record('plagiarism_programming_cours', $courseenabled);
        echo json_encode(array(
            'status' => 'OK'
        ));
    }
}

function plagiarism_programming_disable_course($id) {
    global $DB;
    $DB->delete_records('plagiarism_programming_cours', array(
        'course' => $id
    ));
    echo json_encode(array(
        'status' => 'OK'
    ));
}

/**
 *
 * @return array the category tree with each node has name (category name) and subcat (array of category nodes)
 */
function plagiarism_programming_get_course_categories() {
    global $DB;
    $categoryobjects = $DB->get_records('course_categories', null, 'id ASC');

    $categorytree = array();
    $categoryarray = array();
    foreach ($categoryobjects as $category) {
        $categoryobject = new stdClass();
        $categoryobject->name = $category->name;
        $categoryobject->subcat = array();
        $categoryarray[$category->id] = $categoryobject;
        if ($category->parent) {
            $categoryarray[$category->parent]->subcat[$category->id] = $categoryobject;
        } else {
            $categorytree[$category->id] = $categoryobject;
        }
    }
    return $categorytree;
}

/**
 *
 * @param array $category_tree
 *            the category tree returned by plagiarism_programming_get_course_categories()
 * @param string $content
 *            the select html makup
 * @param int $level
 *            level of the tree, used for recursion
 */
function plagiarism_programming_create_course_category_select($categorytree, $level) {
    $prefix = str_repeat('&nbsp;', $level * 4);
    $options = array();
    foreach ($categorytree as $categoryid => $category) {
        $options[$categoryid] = $prefix . $category->name;
        $options += plagiarism_programming_create_course_category_select($category->subcat, $level + 1);
    }
    return $options;
}