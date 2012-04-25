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
 * Respond to ajax call when marking an assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
include __DIR__.'/../../config.php';
global $DB;

$result_id = required_param('id', PARAM_INT);
$action = required_param('action', PARAM_ALPHA);
assert($action=='Y' || $action=='N' || $action=='');

$result_record = $DB->get_record('programming_result',array('id'=>$result_id));
$context = get_context_instance(CONTEXT_MODULE,$result_record->cmid);

if (has_capability('mod/assignment:grade', $context)) { // only teachers can mark the pair
    $result_record->mark = $action;
    $DB->update_record('programming_result',$result_record);
    echo 'OK';
} else {
    echo 'KO';
}