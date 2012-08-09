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
 * Implement the automatic similarity scanning according to the specified dates in {plagiarism_programming_date}.
 * This script is called periodically, by moodle's cron script
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once(__DIR__.'/utils.php');
require_once(__DIR__.'/scan_assignment.php');
require_once(__DIR__.'/detection_tools.php');

// select the assignments needed to be scanned
global $DB, $CFG, $detection_tools;

create_temporary_dir();

$current_time = time();
$settngids = $DB->get_fieldset_select('plagiarism_programming_date', 'settingid', "finished=0 AND scan_date<=$current_time");
$settngids = array_unique($settngids);

echo "Start sending submissions to plagiarism tools\n";
foreach ($settngids as $setting_id) {
    $assignment_config = $DB->get_record('plagiarism_programming', array('id'=>$setting_id));
    
    // check whether the assignment is already deleted or not (for safety)
    $assignment_ctx = get_context_instance(CONTEXT_MODULE, $assignment_config->cmid, IGNORE_MISSING);
    if (!$assignment_ctx) {
        delete_assignment_scanning_config($assignment_config->cmid);
        continue;
    }

    if (!scanning_in_progress($assignment_config)) { // reset the scanning
        foreach ($detection_tools as $toolname => $toolinfo) {
            if ($assignment_config->$toolname) {
                $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
                $tool_status->status = 'pending';
                $tool_status->message = '';
                $tool_status->error_detail = '';
                $DB->update_record('plagiarism_programming_'.$toolname, $tool_status);
            }
        }
    }
    // do not wait for result, the next cron script will check the status and download the result
    scan_assignment($assignment_config, false);

    $all_tools_finished = true;

    // check if the scanning has been done to mark the date as finished
    foreach ($detection_tools as $toolname => $toolinfo) {
        if ($assignment_config->$toolname) {
            $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
            if ($tool_status->status!='finished' && $tool_status->status!='error') {
                $all_tools_finished = false;
                break;
            }
        }
    }
    if ($all_tools_finished) {
        $scan_dates = $DB->get_records_select('plagiarism_programming_date',
            "settingid=$assignment_config->id AND finished=0 AND scan_date<$current_time", null, 'scan_date ASC');
        $scan_date = array_shift($scan_dates);
        $scan_date->finished=1;
        $DB->update_record('plagiarism_programming_date', $scan_date);
    }

}
echo "Finished sending submissions to plagiarism tools\n";

function scanning_in_progress($assignment_config) {
    global $DB, $detection_tools;
    foreach ($detection_tools as $toolname => $toolinfo) {
        if ($assignment_config->$toolname) {
            $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
            if ($tool_status->status!='pending' && $tool_status->status!='finished' && $tool_status->status!='error') {
                return true;
            }
        }
    }
    return false;
}