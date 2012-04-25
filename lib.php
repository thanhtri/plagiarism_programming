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
 * The main entry file of the plugin.
 * Provide the site-wide setting and specific configuration for each assignment
 *
 * @package    plagiarism
 * @subpackage programming
 * @author     thanhtri
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

//get global class
global $CFG;
require_once($CFG->dirroot.'/plagiarism/lib.php');
require_once __DIR__.'/constants.php';
require_once __DIR__.'/detection_tools.php';
require_once __DIR__.'/reportlib.php';

class plagiarism_plugin_programming extends plagiarism_plugin {

    public function get_form_elements_module($mform, $context) {
        global $DB, $PAGE;
        // when updating an assignment, cmid is passed by "update" param
        // when creating an assignment, cmid is passed by "course" param
        $cmid = optional_param('update', 0, PARAM_INT);
        $course_id = optional_param('course', 0, PARAM_INT);
        if (!$this->is_plugin_enabled($cmid,$course_id))
            return;
        
        $mform->addElement('header','programming_header',  get_string('plagiarism_header',PLAGIARISM_PROGRAMMING));

        // Enable or disable plagiarism checking
        $mform->addElement('checkbox','programmingYN',  get_string('programmingYN', PLAGIARISM_PROGRAMMING));

        // Select the language used
        $programmingLanguages = array(''=>'','java'=>'Java','c'=>'C/C++','c#'=>'C#');
        $mform->addElement('select','programmingLanguage',
                get_string('programmingLanguage',PLAGIARISM_PROGRAMMING),$programmingLanguages);

        // The scanning mode
        $mform->addElement('date_selector','scanDate',get_string('scanDate',PLAGIARISM_PROGRAMMING));


        $selectedTools = array();
        $selectedTools[] = &$mform->createElement('checkbox','jplag','', get_string('jplag',PLAGIARISM_PROGRAMMING));
        $selectedTools[] = &$mform->createElement('checkbox','moss','', get_string('moss',PLAGIARISM_PROGRAMMING));
        $mform->addGroup($selectedTools,'detectionTools', get_string('detectionTools',PLAGIARISM_PROGRAMMING));
        $mform->addElement('checkbox','auto_publish', get_string('auto_publish',PLAGIARISM_PROGRAMMING));
        $mform->addElement('checkbox','notification', get_string('notification',PLAGIARISM_PROGRAMMING));
        $mform->addElement('text','notification_text', get_string('notification_text',PLAGIARISM_PROGRAMMING));

        $mform->disabledIf('detectionTools','programmingYN','notchecked');
        $mform->disabledIf('programmingLanguage','programmingYN','notchecked');
        $mform->disabledIf('scanDate','programmingYN','notchecked');
        $mform->disabledIf('auto_publish','programmingYN','notchecked');
        $mform->disabledIf('notification','programmingYN','notchecked');
        $mform->disabledIf('notification_text','programmingYN','notchecked');
        $mform->disabledIf('notification_text','notification','notchecked');

        $mform->addHelpButton('programmingYN','programmingYN_hlp', PLAGIARISM_PROGRAMMING);
        $mform->addHelpButton('programmingLanguage','programmingLanguage_hlp',PLAGIARISM_PROGRAMMING);
        $mform->addHelpButton('scanDate','date_selector_hlp',PLAGIARISM_PROGRAMMING);
        $mform->addHelpButton('auto_publish','auto_publish_hlp',PLAGIARISM_PROGRAMMING);
        $mform->addHelpButton('notification','notification_hlp',PLAGIARISM_PROGRAMMING);

        $assignment_plagiarism_setting = $DB->get_record('programming_plagiarism',array('courseid'=>$cmid));
        if ($assignment_plagiarism_setting) { // update mode, populate the form with current values
            $mform->setDefault('programmingYN',1);
            $mform->setDefault('programmingLanguage',$assignment_plagiarism_setting->language);
            $mform->setDefault('scanDate', $assignment_plagiarism_setting->scandate);
            $mform->setDefault('detectionTools[jplag]', $assignment_plagiarism_setting->jplag);
            $mform->setDefault('detectionTools[moss]',$assignment_plagiarism_setting->moss);
            $mform->setDefault('auto_publish',$assignment_plagiarism_setting->auto_publish);
            $mform->setDefault('notification_text',$assignment_plagiarism_setting->notification);
            if (!empty($assignment_plagiarism_setting->notification)) {
                $mform->setDefault('notification',1);
            }
        }
    }

    public function save_form_elements($data) {
        global $DB;
        $cmid = $data->coursemodule;
        if ($this->is_plugin_enabled($cmid)) {
            if (isset($data->programmingYN)) {
                $assignment_plagiarism_setting = $DB->get_record('programming_plagiarism',array('courseid'=>$cmid));
                $new = false;
                if (!$assignment_plagiarism_setting) {
                    $new = true;
                    $assignment_plagiarism_setting = new stdClass();
                    $assignment_plagiarism_setting->courseid = $data->coursemodule;
                }
                $assignment_plagiarism_setting->scandate = $data->scanDate;
                $assignment_plagiarism_setting->language = $data->programmingLanguage;
                $assignment_plagiarism_setting->jplag = isset($data->detectionTools['jplag']) ? 1 : 0;
                $assignment_plagiarism_setting->moss = isset($data->detectionTools['moss']) ? 1 : 0;
                $assignment_plagiarism_setting->auto_publish = isset($data->auto_publish) ? 1 : 0;
                if (isset($data->notification)) {
                    $assignment_plagiarism_setting->notification=$data->notification_text;
                } else {
                    $assignment_plagiarism_setting->notification=NULL;
                }

                if ($new) {
                    $assignment_plagiarism_setting->status = 'pending';
                    $DB->insert_record('programming_plagiarism',$assignment_plagiarism_setting);
                } else {
                    $DB->update_record('programming_plagiarism',$assignment_plagiarism_setting);
                }

            } else {
                $DB->delete_records('programming_plagiarism',array('courseid'=>$data->coursemodule));
            }
        }
    }

    public function cron() {
        include 'programming_cron.php';
    }

    public function get_links($linkarray) {
        global $DB, $CFG;
        
        static $students;
        
        if (!$this->is_plugin_enabled($linkarray['cmid']))
            return;

        if ($students==null) {
            $students = get_suspicious_students_in_assignment($linkarray['cmid']);
        }
        // check if programming plagiarism is used and the scanning has been carried out
        $setting = $DB->get_record('programming_plagiarism', array('courseid'=>$linkarray['cmid']));
        if (!$setting) // not turned on
            return;
        $link = get_report_link($linkarray['cmid'], $linkarray['userid']); 
        $output = html_writer::tag('a', 'Report',array('href'=>$link));
        if (isset($students[$linkarray['userid']])) {
            $output .= ' '.html_writer::tag('span', get_string('suspicious',PLAGIARISM_PROGRAMMING), array('class'=>'programming_result_warning'));
        }
        return $output;
    }

    public function print_disclosure($cmid) {
        global $OUTPUT,$DB, $USER, $CFG, $PAGE, $detection_tools;
        $setting = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));

        // the plugin is enabled for this course ?
        if (!$this->is_plugin_enabled($cmid))
            return;
        
        if (!$setting) // plagiarism scanning turned off
            return;

        $content = format_text($setting->notification, FORMAT_MOODLE);

        // if plagiarism report available, display link to report
        $context = get_context_instance(CONTEXT_MODULE, $cmid);
        if (has_capability('mod/assignment:grade', $context, $USER->id)) {
            $check = array();
            foreach ($detection_tools as $tool=>$tool_info) {
                // if the tool is selected
                if (!$setting->$tool)
                    continue;

                $toolname = $tool_info['name'];
                $scanning_info = $DB->get_record('programming_'.$tool, array('settingid'=>$setting->id));
                if (!$scanning_info)
                    $info = 'not started';
                else switch ($scanning_info->status) {
                    case NULL: case 'pending': $info='not started'; break;
                    case 'uploading': $info='uploading'; break;
                    case 'scanning': $info='scanning'; break;
                    case 'downloading': $info='downloading'; break;
                    case 'done': $info='scanning finished'; break;
                    case 'finished':
                        include_once $tool_info['code_file'];
                        $class_name = $tool_info['class_name'];
                        $tool_class = new $class_name();
                        $info = $tool_class->display_link($setting);
                        break;
                    case 'error':
                        $info = "Error: $scanning_info->message";
                        break;
                }
                $info_tag=html_writer::tag('span', $info, array('id'=>$tool.'_status'));
                $content .= html_writer::tag('div', "$toolname: $info_tag",array('class'=>'text_to_html'));
                $content .= html_writer::tag('div', '', array('id'=>$tool.'_tool','class'=>'yui-skin-sam'));
                $needChecking = ($scanning_info &&
                        $scanning_info->status!='pending'  &&
                        $scanning_info->status!='finished' &&
                        $scanning_info->status!='error');
                $check[$tool] = $needChecking;
            }
            
            // write the rescan button
            $button_label = ($scanning_info && $scanning_info->status=='finished')?
                    get_string('rescanning',PLAGIARISM_PROGRAMMING):
                    get_string('start_scanning',PLAGIARISM_PROGRAMMING);
            $content .= html_writer::empty_tag('input',
                    array('type' => 'button',
                          'id' => 'plagiarism_programming_scan',
                          'value' => $button_label));

            $PAGE->requires->yui2_lib('progressbar');
            $PAGE->requires->yui2_lib('json');

            // include the javascript
            $jsmodule = array(
                'name' => 'plagiarism_programming',
                'fullpath' => '/plagiarism/programming/scanning.js',
                'strings' => array()
            );

            $PAGE->requires->js_init_call('M.plagiarism_programming.initialise',
                    array('cmid'=>$setting->courseid,'lasttime'=>$setting->starttime,'checkprogress'=>$check),true,$jsmodule);

        }
        
        // if this is a student
        if (has_capability('mod/assignment:submit', $context, $USER->id)) {
            if (count(get_suspicious_works($USER->id, $cmid))>0) {
                $warning = get_string('high_similarity_warning',PLAGIARISM_PROGRAMMING);
                $content .= html_writer::tag('span', $warning, array('class'=>'programming_result_warning'));
            }
        }

        if (!empty($content)) {
            echo $OUTPUT->box_start('generalbox boxaligncenter', 'plagiarism_info');
            echo $content;
            echo $OUTPUT->box_end();
        }
    }

    public function update_status($course, $cm) {

    }
    
    /** If the plugin is enabled or not (at Moodle level or at course level)
     * @param $cmid: the course module id (can provide the course id instead)
     * @param $course_id: the course id
     * @return true: if the plugin is enabled
     *         false:if the plugin is not enabled
     */
    public function is_plugin_enabled($cmid,$course_id=null) {
        global $DB;
        
        $settings = (array) get_config('plagiarism');
        if (!$settings['programming_use']) {
            return false;
        }
        if (!$course_id) {
            $course_module = get_coursemodule_from_id('assignment', $cmid);
            $course_id = ($course_module)?$course_module->course:0;
        }
        $plagiarism_programming_setting = (array) get_config(PLAGIARISM_PROGRAMMING);
        $enabled = $plagiarism_programming_setting['level_enabled']=='global' || 
            ($DB->get_record('programming_course_enabled',array('course'=>$course_id))!=false);
        return $enabled;
    }
}
