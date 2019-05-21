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
 * Prints the result page.
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot . '/lib/formslib.php');
require_once(dirname(__FILE__) . '/detection_tools.php');

/**
 * Wrapper class.
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class programming_plag_result_form extends moodleform {
    /**
     * @var number $cmid id of course module
     */
    private $cmid;
    /**
     * @var String $detector Either moss or jplag
     */
    private $detector;

    /**
     * Initialize variables.
     * @param number $cmid id of course module
     * @param String $detector Either moss or jplag
     */
    public function __construct($cmid, $detector) {
        $this->cmid = $cmid;
        $this->detector = $detector;
        parent::__construct(null, null, 'get');
    }

    /**
     * Moodle Form definition. Inherited.
     * {@inheritDoc}
     * @see moodleform::definition()
     */
    protected function definition() {
        global $DB, $detectiontools;

        $assignment = $DB->get_record('plagiarism_programming', array(
            'cmid' => $this->cmid
        ));
        $mform = $this->_form;

        // Similarity threshold.
        $mform->addElement('header', 'option_header', get_string('option_header', 'plagiarism_programming'));
        $mform->addElement('text', 'lower_threshold', get_string('threshold', 'plagiarism_programming'));
        $mform->setType('lower_threshold', PARAM_INT);

        // Select the similarity type average or maximal.
        $ratetype = array(
            'max' => 'Maximum similarity',
            'avg' => 'Average similarity'
        );
        $mform->addElement('select', 'rate_type', get_string('similarity_type', 'plagiarism_programming'), $ratetype);

        // Select the tool to display.
        $tools = array();
        foreach ($detectiontools as $tool => $info) {
            if ($assignment->$tool) {
                $tools[$tool] = $info['name'];
            }
        }
        $mform->addElement('select', 'tool', get_string('detection_tool', 'plagiarism_programming'), $tools);

        // Select the mode of display.
        $displaymodes = array(
            'group' => 'Grouping students',
            'table' => 'Ordered table'
        );
        $mform->addElement('select', 'display_mode', get_string('display_mode', 'plagiarism_programming'), $displaymodes);

        // Select the version history.
        $reports = $DB->get_records('plagiarism_programming_rpt', array(
            'cmid' => $this->cmid,
            'detector' => $this->detector
        ), 'time_created DESC');
        $reportselect = array();
        foreach ($reports as $report) {
            $reportselect[$report->version] = date('d M h.i A', $report->time_created);
        }
        $mform->addElement('select', 'version', get_string('version', 'plagiarism_programming'), $reportselect);

        // If having repository, include a checkbox to include repository files or not.
        $fs = get_file_storage();
        $context = context_module::instance($this->cmid);
        $repofiles = $fs->get_area_files($context->id, 'plagiarism_programming', 'codeseeding', $assignment->id, '', false);
        if (! empty($repofiles)) {
            $mform->addElement('advcheckbox', 'include_repository', get_string('include_repository', 'plagiarism_programming'),
                '', array('group' => 0), array(0, 1));
        }

        // Other elements.
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid']);
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'student', $this->_customdata['student_id']);
        $mform->setType('student', PARAM_INT);

        $mform->addElement('submit', 'submitbutton', get_string('submit', 'plagiarism_programming'));

        // Help buttons.
        $mform->addHelpButton('lower_threshold', 'lower_threshold_hlp', 'plagiarism_programming');
        $mform->addHelpButton('rate_type', 'rate_type_hlp', 'plagiarism_programming');
        $mform->addHelpButton('tool', 'tool_hlp', 'plagiarism_programming');
        $mform->addHelpButton('display_mode', 'display_mode_hlp', 'plagiarism_programming');
        $mform->addHelpButton('version', 'version_hlp', 'plagiarism_programming');
    }
}
