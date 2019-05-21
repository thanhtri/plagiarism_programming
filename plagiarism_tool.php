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
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

interface plagiarism_tool {

    /**
     * Submit all the code to the plagiarism detection service.
     * Interface for jplag or moss. See moss_tool.php or jplag_tool.php.
     * @param String $inputdir the directory containing all the extracted code.
     * @param Object $assignment
     * @param Object $params containing the information of the assignment (name, context id...)
     */
    public function submit_assignment($inputdir, $assignment, $params);

    /**
     * Interface for jplag or moss. See moss_tool.php or jplag_tool.php.
     * @param Object $assignmentparam containing the information of the assignment
     * @param Object $toolparam containing the information of the configuration for that tool of the assignment
     */
    public function check_status($assignmentparam, $toolparam);

    /**
     * Display the link to the report.
     * Interface for jplag or moss. See moss_tool.php or jplag_tool.php.
     * @param Object $param
     */
    public function display_link($param);

    /**
     * Interface for downloading the results. Is used by moss and jplag.
     * @param Object $assignmentparam
     * @param Object $jplagparam
     */
    public function download_result($assignmentparam, $jplagparam);

    /**
     * Interface for parsing the results. Is used by moss and jplag.
     * @param Object $assignment
     * @param Object $mossinfo
     */
    public function parse_result($assignment, $mossinfo);

    /**
     * Interface to get the name of the service used. Jplag or Moss.
     */
    public function get_name();
}
