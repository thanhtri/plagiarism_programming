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
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

include_once $CFG->dirroot.'/lib/formslib.php';
include_once dirname(__FILE__).'/constants.php';
include_once dirname(__FILE__).'/detection_tools.php';

class programming_plag_result_form extends moodleform {

    public function __construct() {
        parent::__construct(null,null,'get');
    }
    
    protected function definition() {
        global $detection_tools;
        
        $mform = $this->_form;
        
        // similarity threshold
        $mform->addElement('header','option_header',  get_string('option_header',PLAGIARISM_PROGRAMMING));
        $mform->addElement('text','lower_threshold', get_string('threshold',PLAGIARISM_PROGRAMMING));
        
        // select the similarity type average or maximal
        $rate_type = array('max'=>'Maximum similarity','avg'=>'Average similarity');
        $mform->addElement('select','rate_type',get_string('similarity_type',PLAGIARISM_PROGRAMMING),$rate_type);
        
        // select the tool to display
        $tools = array();
        foreach ($detection_tools as $tool=>$info) {
            $tools[$tool] = $info['name'];
        }
        $mform->addElement('select','tool',get_string('detectors',PLAGIARISM_PROGRAMMING),$tools);
        
        // select the mode of display
        $display_modes = array('group'=>'Grouping students','table'=>'Ordered table');
        $mform->addElement('select','display_mode',  get_string('display_mode',PLAGIARISM_PROGRAMMING),$display_modes);
        
        // other elements
        $mform->addElement('hidden','cmid',$this->_customdata['cmid']);
        if ($this->_customdata['student_id']) {
            $mform->addElement('hidden','student_id',$this->_customdata['student_id']);
        }
        
        $mform->addElement('submit','submitbutton',get_string('submit',PLAGIARISM_PROGRAMMING));
    }

}

?>
