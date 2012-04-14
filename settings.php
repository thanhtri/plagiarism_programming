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
 * Provide the site-wide setting
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once dirname(__FILE__).'/../../config.php';
require_once $CFG->libdir.'/adminlib.php';
require_once $CFG->libdir.'/plagiarismlib.php';
require_once $CFG->dirroot.'/plagiarism/programming/plagiarism_form.php';
require_once dirname(__FILE__).'/constants.php';

global $PAGE;

require_login();
admin_externalpage_setup('plagiarismprogramming');

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();

if ($mform->is_cancelled()) {
    redirect('');
}

if (($data = $mform->get_data()) && confirm_sesskey()) {
    // update programming_use variable
    $programming_use = (isset($data->programming_use))?$data->programming_use:0;
    set_config('programming_use', $programming_use, 'plagiarism');
    $variables = array('level_enabled');
    
    if (isset($data->jplag_modify_account)) { // change the user name and password
        
        $jplag_stub = new jplag_stub();
        if ($jplag_stub->check_credential($data->jplag_user, $data->jplag_pass)) {
            $variables[] = 'jplag_user';
            $variables[] = 'jplag_pass';
        } else {
            // TODO: inform error
        }
    }
    foreach ($variables as $field) {
        set_config($field, $data->$field, PLAGIARISM_PROGRAMMING);
    }
    notify(get_string('savedconfigsuccess', PLAGIARISM_PROGRAMMING), 'notifysuccess');
}

$plagiarism_programming_setting = (array) get_config('plagiarism_programming');
$plagiarismsettings = (array)get_config('plagiarism');
$plagiarism_programming_setting['programming_use'] = $plagiarismsettings['programming_use'];

// clear the password for security
if (!empty($plagiarism_programming_setting['jplag_pass']))
    $plagiarism_programming_setting['jplag_pass'] = "************";

$mform->set_data($plagiarism_programming_setting);

echo $OUTPUT->header();

$PAGE->requires->yui2_lib('yahoo-dom-event');
$PAGE->requires->yui2_lib('dragdrop');
$PAGE->requires->yui2_lib('container');
$PAGE->requires->yui2_lib('element');
$PAGE->requires->yui2_lib('json');

$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/course_selection.js'
);
$PAGE->requires->js_init_call('M.plagiarism_programming.select_course.init',null,true,$jsmodule);

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
// include the javascript
