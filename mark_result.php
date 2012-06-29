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
 * Respond to ajax call related to the plagiarism report. Parameters are
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);

require_once(__DIR__.'/../../config.php');
require_once(__DIR__.'/reportlib.php');
global $DB;

$result_id = required_param('id', PARAM_INT);
$task = required_param('task', PARAM_TEXT);
$result_record = $DB->get_record('plagiarism_programming_result', array('id'=>$result_id));
$report_record = $DB->get_record('plagiarism_programming_report', array('id'=>$result_record->reportid));
$context = get_context_instance(CONTEXT_MODULE, $report_record->cmid);

// only teachers can mark the pairs
has_capability('mod/assignment:grade', $context) || die('KO');

if ($task=='mark') {
    $action = required_param('action', PARAM_ALPHA);
    assert($action=='Y' || $action=='N' || $action=='');
    $result_record->mark = $action;
    $DB->update_record('plagiarism_programming_result', $result_record);
    echo 'OK';
} else if ($task=='get_history') {
    $rate_type = optional_param('rate_type', 'avg', PARAM_TEXT);
    $similarity_history = get_student_similarity_history($result_record->student1_id, $result_record->student2_id,
        $report_record->cmid, $report_record->detector, 'asc');
    $history = array();
    if ($rate_type=='avg') {
        foreach ($similarity_history as $pair) {
            $history[$pair->id] = array('time'=>$pair->time_created,'similarity'=>($pair->similarity1+$pair->similarity2)/2,
                'time_text'=>date('d M', $pair->time_created));
        }
    } else {
        foreach ($similarity_history as $pair) {
            $history[$pair->id] = array('time'=>$pair->time_created,'similarity'=>max($pair->similarity1, $pair->similarity2));
        }
    }
    echo json_encode($history);
}