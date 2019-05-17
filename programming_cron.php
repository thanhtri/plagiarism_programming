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
 * @package plagiarism
 * @subpackage programming
 * @author thanhtri
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once(__DIR__ . '/utils.php');
require_once(__DIR__ . '/scan_assignment.php');
require_once(__DIR__ . '/detection_tools.php');

// Select the assignments needed to be scanned.
global $DB, $CFG, $detectiontools;

plagiarism_programming_create_temp_dir();

$currenttime = time();
$settngids = $DB->get_fieldset_select('plagiarism_programming_date', 'settingid', "finished=0 AND scan_date<=$currenttime");
$settngids = array_unique($settngids);

echo "Start sending submissions to plagiarism tools\n";
foreach ($settngids as $settingid) {
    $assignmentconfig = $DB->get_record('plagiarism_programming', array(
        'id' => $settingid
    ));

    // Check whether the assignment is already deleted or not (for safety).
    $assignmentctx = context_module::instance($assignmentconfig->cmid, IGNORE_MISSING);
    if (! $assignmentctx) {
        plagiarism_programming_delete_config($assignmentconfig->cmid);
        continue;
    }

    if (! scanning_in_progress($assignmentconfig)) { // Reset the scanning.
        foreach ($detectiontools as $toolname => $toolinfo) {
            if ($assignmentconfig->$toolname) {
                $toolstatus = $DB->get_record('plagiarism_programming_' . $toolname, array(
                    'settingid' => $assignmentconfig->id
                ));
                $toolstatus->status = 'pending';
                $toolstatus->message = '';
                $toolstatus->error_detail = '';
                $DB->update_record('plagiarism_programming_' . $toolname, $toolstatus);
            }
        }
    }

    // Do not wait for result, the next cron script will check the status and download the result.
    // Send an email when scanning complete.
    plagiarism_programming_scan_assignment($assignmentconfig, false, true);

    $alltoolsfinished = true;

    // Check if the scanning has been done to mark the date as finished.
    foreach ($detectiontools as $toolname => $toolinfo) {
        if ($assignmentconfig->$toolname) {
            $toolstatus = $DB->get_record('plagiarism_programming_' . $toolname, array(
                'settingid' => $assignmentconfig->id
            ));
            if ($toolstatus->status != 'finished' && $toolstatus->status != 'error') {
                $alltoolsfinished = false;
                break;
            }
        }
    }

    if ($alltoolsfinished) {
        $scandates = $DB->get_records_select('plagiarism_programming_date',
            "settingid=$assignmentconfig->id AND finished=0 AND scan_date<$currenttime", null, 'scan_date ASC');
        $scandate = array_shift($scandates);
        $scandate->finished = 1;
        $DB->update_record('plagiarism_programming_date', $scandate);
    }
}
echo "Finished sending submissions to plagiarism tools\n";

function scanning_in_progress($assignmentconfig) {
    global $DB, $detectiontools;
    foreach ($detectiontools as $toolname => $toolinfo) {
        if ($assignmentconfig->$toolname) {
            $toolstatus = $DB->get_record('plagiarism_programming_' . $toolname, array(
                'settingid' => $assignmentconfig->id
            ));
            if ($toolstatus->status != 'pending' && $toolstatus->status != 'finished' && $toolstatus->status != 'error') {
                return true;
            }
        }
    }
    return false;
}