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

        $courses = $this->course_search($totalnum);
        $mform = $this->_form;

        foreach ($courses as $course) {
            $elementname = "course_$course->id";
            $mform->addElement('checkbox', $elementname, '', $course->fullname.' '.$course->idnumber);
            if ($course->is_enabled) {
                $mform->setDefault($elementname, 1);
            }
        }

        if ($this->category > 0 || !empty($this->name)) {
            return;
        }

        $isfirstpage = $this->page > 1;
        $backlink = '';
        if ($isfirstpage) {
            $backlink = html_writer::link('', get_string('back'), array('class' => 'changepagelink', 'page' => $this->page - 1));
        }

        $nextlink = '';
        $islastpage = ($this->page * PAGE_SIZE) >= $totalnum;
        $page = $this->page;
        if (!$islastpage) {
            $nextlink = html_writer::link('', get_string('next'), array('class' => 'changepagelink', 'page' => $this->page + 1));
        }
        $mform->addElement('html', html_writer::tag('div', $backlink.' '.$nextlink, array('style' => 'text-align:center')));
    }

    private function course_search(&$totalrecord) {
        global $CFG, $DB;

        $sql = 'SELECT course.id, course.fullname, course.idnumber, course.shortname, enabled_course.course is_enabled
                  FROM {course} course
             LEFT JOIN {plagiarism_programming_cours} enabled_course
                    ON (course.id=enabled_course.course) ';
        $where = ' category>0 ';

        if ($this->category > 0) {
            require_once($CFG->dirroot.'/lib/coursecatlib.php');
            $categorylist = coursecat::get($this->category, null, false)->get_children();
            $categoryids = array($this->category);
            foreach ($categorylist as $category) {
                $categoryids[] = $category->id;
            }
            $idlist = implode(',', $categoryids);
            $where .= " AND category IN ($idlist)";
        }

        if (!empty($this->name)) {
            $where .= " AND (fullname Like '%$this->name%' Or idnumber Like '%$this->name%')";
        }
        $sql .= " WHERE $where ORDER BY fullname ASC ";
        if ($this->category == 0 && empty($this->name)) { // Only limit with ordinary browsing, not in search mode.
            $limitfrom = ($this->page - 1) * PAGE_SIZE;
            $courses = $DB->get_records_sql($sql, null, $limitfrom, PAGE_SIZE);
            $totalrecord = $DB->count_records_select('course', $where);
        } else {
            $courses = $DB->get_records_sql($sql);
        }

        return $courses;
    }
}
