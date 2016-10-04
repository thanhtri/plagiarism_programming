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
 * Define the interface for entry points that the client of each engine must implement
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

interface plagiarism_tool {

    /**
     * Submit result: submit all the code to the plagiarism detection service
     * @param $inputdir the directory containing all the extracted code.
     *        Each immediate subdirectory is the submission of one student
     * @param stdClass $assignment the record object of assignment config
     * @param $params containing the information of the assignment (name, context id...)
     */
    public function submit_assignment($inputdir, $assignment, $params);

    /**
     * Check the status of the scanning after submit. If the scanning is finised, download the result and return finished
     * @param $assignment_param containing the information of the assignment
     * @param $tool_param containing the information of the configuration for that tool of the assignment
     */
    public function check_status($assignment_param, $tool_param);

    /**
     * Display the link to the report. This function return html <a> tag of the link
     * @param type $param parameter of the assignment
     */
    public function display_link($param);

    public function download_result($assignment_param, $jplag_param);

    public function parse_result($assignment, $moss_info);

    public function get_name();
}
