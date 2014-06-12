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

global $CFG, $PAGE, $OUTPUT;
require_once(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/programming/plagiarism_form.php');

require_login();
admin_externalpage_setup('plagiarismprogramming');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();

$notification = '';
if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot);
} else if (($data = $mform->get_data()) && confirm_sesskey()) {
    // update programming_use variable
    $programming_use = (isset($data->programming_use))?$data->programming_use:0;
    set_config('programming_use', $programming_use, 'plagiarism');

    $variables = array('level_enabled', 'moss_user_id', 'jplag_user', 'jplag_pass', 'moss_user_id');

    $email = $data->moss_email;
    if ($email) {
        $pattern = '/\$userid=([0-9]+);/';
        preg_match($pattern, $email, $match);
        $data->moss_user_id = $match[1];
    }
    foreach ($variables as $field) {
        set_config($field, $data->$field, 'plagiarism_programming');
    }
    $notification = $OUTPUT->notification(get_string('save_config_success', 'plagiarism_programming'), 'notifysuccess');
}

$plagiarism_programming_setting = (array) get_config('plagiarism_programming');
$plagiarismsettings = (array) get_config('plagiarism');
if (isset($plagiarismsettings['programming_use'])) {
    $plagiarism_programming_setting['programming_use'] = $plagiarismsettings['programming_use'];
}

$mform->set_data($plagiarism_programming_setting);

echo $OUTPUT->header();

// include the javascript
$jsmodule = array(
    'name' => 'plagiarism_programming',
    'fullpath' => '/plagiarism/programming/coursesetting/course_selection.js',
    'requires' => array('panel', 'io'),
    'strings' => array(
        array('course_select', 'plagiarism_programming'),
        array('by_name', 'plagiarism_programming'),
        array('search', 'plagiarism_programming'),
        array('search_by_category', 'plagiarism_programming'),
    )
);
$PAGE->requires->js_init_call('M.plagiarism_programming.select_course.init', null, true, $jsmodule);

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
echo $notification;
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
