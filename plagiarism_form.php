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
 * Build the form used for site-wide configuration
 * This form is assessible by Site Administration -> Plugins -> Plagiarism Prevention -> Programming Assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->dirroot.'/lib/formslib.php');

class plagiarism_setup_form extends moodleform {

    protected function definition () {

        $mform = &$this->_form;

        $mform->addElement('html', get_string('programmingexplain', 'plagiarism_programming'));
        // if the plugin is used
        $mform->addElement('checkbox', 'programming_use', get_string('use_programming', 'plagiarism_programming'));
        // enable the plugin at the course level
        $enable_level = array();
        $enable_level[] = $mform->createElement('radio', 'level_enabled', '', get_string('enable_global', 'plagiarism_programming'),
            'global', array('class' => 'plagiarism_programming_enable_level'));
        $enable_level[] = $mform->createElement('radio', 'level_enabled', '', get_string('enable_course', 'plagiarism_programming'),
            'course', array('class' => 'plagiarism_programming_enable_level'));
        $mform->addGroup($enable_level, 'level_enabled', '   ', array('  '), false);
        $mform->setDefault('level_enabled', 'global');

        $mform->addElement('html', html_writer::tag('div', get_string('account_instruction', 'plagiarism_programming')));

        $mform->addElement('header', 'moss_config', get_string('moss', 'plagiarism_programming'));
        $moss_link = html_writer::link('http://theory.stanford.edu/~aiken/moss/', ' http://theory.stanford.edu/~aiken/moss/');
        $mform->addElement('html',
                html_writer::tag('div', get_string('moss_account_instruction', 'plagiarism_programming').$moss_link));
        $mform->addElement('html', html_writer::tag('div', get_string('moss_id_help', 'plagiarism_programming')));
        $mform->addElement('text', 'moss_user_id', get_string('moss_id', 'plagiarism_programming'));
        $mform->setType('moss_user_id', PARAM_TEXT);

        $mform->addElement('html', html_writer::tag('div', get_string('moss_id_help_2', 'plagiarism_programming')));
        $mform->addElement('textarea', 'moss_email', '', 'wrap="virtual" rows="20" cols="80"');

        $this->add_action_buttons(true);
    }

    public function validation($data, $files) {
        global $CFG;
        $errors = parent::validation($data, $files);

        $empty_user = empty($data['jplag_user']);
        $empty_pass = empty($data['jplag_pass']);
        if (!$empty_user && $empty_pass) { //missing username
            $errors['jplag_pass'] = get_string('password_missing', 'plagiarism_programming');
        } else if (!$empty_pass && $empty_user) { // missing password
            $errors['jplag_user'] = get_string('username_missing', 'plagiarism_programming');
        } else if (!$empty_user && !$empty_pass) {  // check if the user changed his username and password
            $proxyhost = isset($CFG->proxyhost)?$CFG->proxyhost:'';
            $proxyport = isset($CFG->proxyport)?$CFG->proxyport:'';
            $proxyuser = isset($CFG->proxyuser)?$CFG->proxyuser:'';
            $proxypass = isset($CFG->proxypassword)?$CFG->proxypassword:'';
            $pass = $data['jplag_pass'];
            $user = $data['jplag_user'];
            $old_setting = get_config('plagiarism_programming');
            if (!(isset($old_setting->jplag_user) && isset($old_setting->jplag_pass)) ||
                    $user != $old_setting->jplag_user || $pass!=$old_setting->jplag_pass) {
                // change credential, recheck username and password
                include_once(__DIR__.'/jplag/jplag_stub.php');
                $jplag_stub = new jplag_stub($data['jplag_user'], $data['jplag_pass'],
                    $proxyhost, $proxyport, $proxyuser, $proxypass);
                $check_result = $jplag_stub->check_credential();
                if ($check_result !==true) {
                    $errors['jplag_user'] = $check_result['message'];
                }
            }
        }

        if (!empty($data['moss_email'])) {
            $pattern = '/\$userid=([0-9]+);/';
            preg_match($pattern, $data['moss_email'], $match);
            if (!$match) {
                $errors['moss_email'] = get_string('moss_userid_notfound', 'plagiarism_programming');
            }
        }

        return $errors;
    }
}