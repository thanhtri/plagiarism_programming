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

global $PAGE;

require_login();
admin_externalpage_setup('plagiarismprogramming');

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();

$notification = '';
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot);
} elseif (($data = $mform->get_data()) && confirm_sesskey()) {
    // update programming_use variable
    $programming_use = (isset($data->programming_use))?$data->programming_use:0;
    set_config('programming_use', $programming_use, 'plagiarism');
    
    $variables = array('level_enabled','moss_user_id');
    
    $is_error = false;
    include_once dirname(__FILE__).'/jplag/jplag_stub.php';
    $jplag_stub = new jplag_stub();
//    $check_result = $jplag_stub->check_credential($data->jplag_user, $data->jplag_pass);
    $check_result = TRUE;
    if ($check_result === TRUE) {
        $variables[] = 'jplag_user';
        $variables[] = 'jplag_pass';
    } else {
        $is_error = true;
        switch ($check_result) {
            case JPLAG_CREDENTIAL_ERROR:
                $notification = $OUTPUT->notification(get_string('jplag_account_error', 'plagiarism_programming'), 'notifyproblem');
                break;
            case JPLAG_CREDENTIAL_EXPIRED:
                $notification = $OUTPUT->notification(get_string('jplag_account_expired', 'plagiarism_programming'), 'notifyproblem');
                break;
            case WS_CONNECT_ERROR:
                $notification = $OUTPUT->notification(get_string('connection_error', 'plagiarism_programming'), 'notifyproblem');
                break;
        }
    }
    $email = $data->moss_email;
    if (!empty($email)) { // check and extract userid from email
        $pattern = '/\$userid=([0-9]+);/';
        preg_match($pattern, $email, $match);
        if ($match) {
            $data->moss_user_id = $match[1];
        } else {
            $is_error = true;
            $notification = $OUTPUT->notification(get_string('moss_userid_notfound', 'plagiarism_programming'), 'notifyproblem');
        }
    }
    if (!$is_error) {
        foreach ($variables as $field) {
            set_config($field, $data->$field, 'plagiarism_programming');
        }
        $notification = $OUTPUT->notification(get_string('save_config_success', 'plagiarism_programming'), 'notifysuccess');
    }
}

$plagiarism_programming_setting = (array) get_config('plagiarism_programming');
$plagiarismsettings = (array) get_config('plagiarism');
$plagiarism_programming_setting['programming_use'] = $plagiarismsettings['programming_use'];
$plagiarism_programming_setting['level_enabled'] = !isset ($plagiarismsettings['level_enabled'])?'global':$plagiarismsettings['level_enabled'];

$mform->set_data($plagiarism_programming_setting);

echo $OUTPUT->header();

// include the javascript
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
echo $notification;
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
