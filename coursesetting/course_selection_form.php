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
 * Build the form used for site-wide configuration
 * This form is assessible by Site Administration -> Plugins -> Plagiarism Prevention -> Programming Assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Access to internal script forbidden');

require_once($CFG->dirroot.'/lib/formslib.php');
define('PAGE_SIZE', 12);

class course_selection_form extends moodleform {

    private $page;
    private $category;
    private $name;

    public function __construct($page=1, $category=0, $name='') {
        $this->page = $page;
        $this->category = $category;
        $this->name = $name;
        parent::__construct();
    }

    protected function definition() {
        global $DB;

        $courses = $this->course_search($total_num);
        $mform = $this->_form;

        foreach ($courses as $course) {
            $element_name = "course_$course->id";
            $mform->addElement('checkbox', $element_name, '', $course->fullname.' '.$course->idnumber);
            if ($course->is_enabled) {
                $mform->setDefault($element_name, 1);
            }
        }

        if ($this->category > 0 || !empty($this->name)) {
            return;
        }

        $is_first_page = $this->page > 1;
        $back_link = '';
        if ($is_first_page) {
            $back_link = html_writer::link('', get_string('back'), array('class'=>'changepagelink', 'page'=>  $this->page-1));
        }

        $next_link = '';
        $is_last_page = ($this->page*PAGE_SIZE)>=$total_num;
        $page = $this->page;
        if (!$is_last_page) {
            $next_link = html_writer::link('', get_string('next'), array('class'=>'changepagelink', 'page'=>  $this->page+1));
        }
        $mform->addElement('html', html_writer::tag('div', $back_link.' '.$next_link, array('style'=>'text-align:center')));
    }

    private function course_search(&$total_record) {
        global $DB;

        $sql = 'SELECT course.id, course.fullname, course.idnumber, course.shortname, enabled_course.course is_enabled
                  FROM {course} course
             LEFT JOIN {plagiarism_programming_cours} enabled_course
                    ON (course.id=enabled_course.course) ';
        $where = ' category>0 ';

        if ($this->category > 0) {
            $category_list = get_categories($this->category, null, false);
            $category_ids = array($this->category);
            foreach ($category_list as $category) {
                $category_ids[]=$category->id;
            }
            $id_list = implode(',', $category_ids);
            $where .= " AND category IN ($id_list)";
        }

        if (!empty($this->name)) {
            $where .= " AND (fullname Like '%$this->name%' Or idnumber Like '%$this->name%')";
        }
        $sql .= " WHERE $where ORDER BY fullname ASC ";
        if ($this->category == 0 && empty($this->name)) { // only limit with ordinary browsing, not in search mode
            $limit_from = ($this->page-1)*PAGE_SIZE;
            $courses = $DB->get_records_sql($sql, null, $limit_from, PAGE_SIZE);
            $total_record = $DB->count_records_select('course', $where);
        } else {
            $courses = $DB->get_records_sql($sql);
        }

        return $courses;
    }
}
