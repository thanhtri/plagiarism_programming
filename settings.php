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

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/programming/plagiarism_form.php');

require_login();
admin_externalpage_setup('plagiarismprogramming');

$context = get_context_instance(CONTEXT_SYSTEM);

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();

if ($mform->is_cancelled()) {
    redirect('');
}

echo $OUTPUT->header();

if (($data = $mform->get_data()) && confirm_sesskey()) {
    if (!isset($data->programming_use)) {
        $data->programming_use = 0;
    }
    $variables = array('programming_use');
    if ($data->jplag_modify_account) { // change the user name and password
        // TODO: test username and password valid ??
        $variables[] = 'jplag_user';
        $variables[] = 'jplag_pass';
    }
    foreach ($data as $field=>$value) {
        if (in_array($field, $variables)) {
            $tiiconfigfield = $DB->get_record('config_plugins', array('name'=>$field, 'plugin'=>'plagiarism'));
            if ($tiiconfigfield) {
                $tiiconfigfield->value = $value;
                if (! $DB->update_record('config_plugins', $tiiconfigfield)) {
                    error("errorupdating");
                }
            } else {
                $tiiconfigfield = new stdClass();
                $tiiconfigfield->value = $value;
                $tiiconfigfield->plugin = 'plagiarism';
                $tiiconfigfield->name = $field;
                if (! $DB->insert_record('config_plugins', $tiiconfigfield)) {
                    error("errorinserting");
                }
            }
        }
    }
    notify(get_string('savedconfigsuccess', 'plagiarism_new'), 'notifysuccess');
}
$plagiarismsettings = (array)get_config('plagiarism');
$plagiarismsettings['jplag_pass'] = "************"; // clear the password for security
$mform->set_data($plagiarismsettings);

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
