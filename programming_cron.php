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
 * Initiate the plagiarism scanning for all assignments of which the
 * scanning date already passed
 * Called by the cron script
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once dirname(__FILE__).'/utils.php';
include_once dirname(__FILE__).'/scan_assignment.php';

// select the assignments needed to be scanned
global $DB, $CFG;

create_temporary_dir();

$assignments_to_scan = $DB->get_records_select('programming_plagiarism','scandate <='.time()." AND status!='finished'");

echo "Start sending submissions to plagiarism tools\n";
foreach ($assignments_to_scan as $assignment) {
    batch_scan($assignment);
}
echo "Finished sending submissions to plagiarism tools\n";