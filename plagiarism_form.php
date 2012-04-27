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

require_once($CFG->dirroot.'/lib/formslib.php');
require_once dirname(__FILE__).'/constants.php';

class plagiarism_setup_form extends moodleform {

    function definition () {
        global $CFG;

        $mform =& $this->_form;
        $choices = array('No','Yes');
        $mform->addElement('html', get_string('programmingexplain', PLAGIARISM_PROGRAMMING));
        // if the plugin is used
        $mform->addElement('checkbox', 'programming_use', get_string('use_programming', PLAGIARISM_PROGRAMMING));
        // enable the plugin at the course level
        $enable_level = array();
        $enable_level[] = &MoodleQuickForm::createElement('radio','level_enabled','',get_string('enable_global',PLAGIARISM_PROGRAMMING),'global',array('class'=>'plagiarism_programming_enable_level'));
        $enable_level[] = &MoodleQuickForm::createElement('radio','level_enabled','',get_string('enable_course',PLAGIARISM_PROGRAMMING),'course',array('class'=>'plagiarism_programming_enable_level'));
        $mform->addGroup($enable_level,'level_enabled','   ',array('  '),false);

        $mform->addElement('header','jplag_config',get_string('jplag',PLAGIARISM_PROGRAMMING));
		$mform->addElement('checkbox','jplag_modify_account', get_string('jplag_modify_account',PLAGIARISM_PROGRAMMING));
		
		$mform->addElement('text','jplag_user',get_string('jplag_username',PLAGIARISM_PROGRAMMING));
		$mform->addElement('password','jplag_pass',get_string('jplag_password',PLAGIARISM_PROGRAMMING));
		
		$mform->disabledIf('jplag_user', 'jplag_modify_account', 'notchecked');
		$mform->disabledIf('jplag_pass', 'jplag_modify_account', 'notchecked');
        
        $mform->addElement('header','moss_config',get_string('moss',PLAGIARISM_PROGRAMMING));
        $mform->addElement('text','moss_user_id', get_string('moss_id',PLAGIARISM_PROGRAMMING));

        $this->add_action_buttons(true);
    }
}