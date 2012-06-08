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
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/lib/formslib.php');
require_once(dirname(__FILE__).'/detection_tools.php');

class programming_plag_result_form extends moodleform {

    private $cmid;
    private $detector;
    public function __construct($cmid, $detector) {
        $this->cmid = $cmid;
        $this->detector = $detector;
        parent::__construct(null, null, 'get');
    }

    protected function definition() {
        global $DB, $detection_tools;

        $mform = $this->_form;

        // similarity threshold
        $mform->addElement('header', 'option_header', get_string('option_header', 'plagiarism_programming'));
        $mform->addElement('text', 'lower_threshold', get_string('threshold', 'plagiarism_programming'));

        // select the similarity type average or maximal
        $rate_type = array('max'=>'Maximum similarity', 'avg'=>'Average similarity');
        $mform->addElement('select', 'rate_type', get_string('similarity_type', 'plagiarism_programming'), $rate_type);

        // select the tool to display
        $tools = array();
        foreach ($detection_tools as $tool => $info) {
            $tools[$tool] = $info['name'];
        }
        $mform->addElement('select', 'tool', get_string('detection_tool', 'plagiarism_programming'), $tools);

        // select the mode of display
        $display_modes = array('group'=>'Grouping students', 'table'=>'Ordered table');
        $mform->addElement('select', 'display_mode', get_string('display_mode', 'plagiarism_programming'), $display_modes);

        // select the version history
        $reports = $DB->get_records('programming_report', array('cmid'=>$this->cmid, 'detector'=>$this->detector), 'time_created DESC');
        $report_select = array();
        foreach ($reports as $report) {
            $report_select[$report->version] = date('d M h.i A', $report->time_created);
        }
        $mform->addElement('select', 'version', get_string('version', 'plagiarism_programming'), $report_select);

        // other elements
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->addElement('hidden', 'student', $this->_customdata['student_id']);

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'plagiarism_programming'));

        // help button
        $mform->addHelpButton('lower_threshold', 'lower_threshold_hlp', 'plagiarism_programming');
        $mform->addHelpButton('rate_type', 'rate_type_hlp', 'plagiarism_programming');
        $mform->addHelpButton('tool', 'tool_hlp', 'plagiarism_programming');
        $mform->addHelpButton('display_mode', 'display_mode_hlp', 'plagiarism_programming');
        $mform->addHelpButton('version', 'version_hlp', 'plagiarism_programming');
    }

}
