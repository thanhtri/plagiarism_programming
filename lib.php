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
    die('Direct access to this script is forbidden.');
}

//get global class
global $CFG;
require_once($CFG->dirroot.'/plagiarism/lib.php');
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
        $settings = get_config('plagiarism_programming');
        
        $mform->addElement('header','programming_header',  get_string('plagiarism_header','plagiarism_programming'));
        
        // Enable or disable plagiarism checking
        $mform->addElement('checkbox','programmingYN',  get_string('programmingYN', 'plagiarism_programming'));

        // Select the language used
        $programming_languages = array('java'=>'Java','c'=>'C/C++','c#'=>'C#');
        $mform->addElement('select','programming_language',
                get_string('programming_language','plagiarism_programming'),$programming_languages);

        // The scanning mode
        $mform->addElement('date_selector','scan_date',get_string('scan_date','plagiarism_programming'));

        $selectedTools = array();
        $warning_style = array('class'=>'programming_result_warning');
        if (empty($settings->jplag_user) || empty($settings->jplag_pass)) {
            $mform->addElement('html',html_writer::tag('div',get_string('jplag_credential_missing','plagiarism_programming'),$warning_style));
            $missing_credential = html_writer::tag('div',get_string('credential_missing_instruction','plagiarism_programming'),$warning_style);
        }
        if (empty($settings->moss_user_id)) {
            $mform->addElement('html',html_writer::tag('div',get_string('moss_credential_missing','plagiarism_programming'),$warning_style));
            $missing_credential = html_writer::tag('div',get_string('credential_missing_instruction','plagiarism_programming'),$warning_style);
        }
        if (isset($missing_credential)) {
            $mform->addElement('html',$missing_credential);
        }
        $selectedTools[] = &$mform->createElement('checkbox','jplag','',get_string('jplag','plagiarism_programming'));
        $selectedTools[] = &$mform->createElement('checkbox','moss','',get_string('moss','plagiarism_programming'));

        $mform->addGroup($selectedTools,'detection_tools',get_string('detection_tools','plagiarism_programming'));
        $mform->addElement('checkbox','auto_publish', get_string('auto_publish','plagiarism_programming'));
        $mform->addElement('text','notification_text', get_string('notification_text','plagiarism_programming'));

        $mform->disabledIf('detection_tools','programmingYN','notchecked');
        $mform->disabledIf('programming_language','programmingYN','notchecked');
        $mform->disabledIf('scan_date','programmingYN','notchecked');
        $mform->disabledIf('auto_publish','programmingYN','notchecked');
        $mform->disabledIf('notification','programmingYN','notchecked');
        $mform->disabledIf('notification_text','programmingYN','notchecked');
        $mform->disabledIf('notification_text','notification','notchecked');

        $mform->addHelpButton('programmingYN','programmingYN_hlp', 'plagiarism_programming');
        $mform->addHelpButton('programming_language','programmingLanguage_hlp','plagiarism_programming');
        $mform->addHelpButton('scan_date','date_selector_hlp','plagiarism_programming');
        $mform->addHelpButton('auto_publish','auto_publish_hlp','plagiarism_programming');
        $mform->addHelpButton('notification_text','notification_hlp','plagiarism_programming');

        $assignment_plagiarism_setting = $DB->get_record('programming_plagiarism',array('courseid'=>$cmid));
        if ($assignment_plagiarism_setting) { // update mode, populate the form with current values
            $mform->setDefault('programmingYN',1);
            $mform->setDefault('programming_language',$assignment_plagiarism_setting->language);
            $mform->setDefault('scanDate', $assignment_plagiarism_setting->scandate);
            $mform->setDefault('detection_tools[jplag]', $assignment_plagiarism_setting->jplag);
            $mform->setDefault('detection_tools[moss]',$assignment_plagiarism_setting->moss);
            $mform->setDefault('auto_publish',$assignment_plagiarism_setting->auto_publish);
            $mform->setDefault('notification_text',$assignment_plagiarism_setting->notification);
        } else {
            $mform->setDefault('programming_language','java');
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
                $assignment_plagiarism_setting->scandate = $data->scan_date;
                $assignment_plagiarism_setting->language = $data->programming_language;
                $assignment_plagiarism_setting->jplag = isset($data->detection_tools['jplag']) ? 1 : 0;
                $assignment_plagiarism_setting->moss = isset($data->detection_tools['moss']) ? 1 : 0;
                $assignment_plagiarism_setting->auto_publish = isset($data->auto_publish) ? 1 : 0;
                $assignment_plagiarism_setting->notification=$data->notification_text;

                if ($new) {
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
        $output = ' '.html_writer::tag('a', 'Report',array('href'=>$link));
        if (isset($students[$linkarray['userid']])) {
            $output .= ' '.html_writer::tag('span', get_string('suspicious','plagiarism_programming'), array('class'=>'programming_result_warning'));
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
        $already_scanned = false;
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
                $already_scanned |= ($scanning_info && ($scanning_info->status=='finished'||$scanning_info->status!='error'));
            }
            
            $button_disabled = false;
            // check at least one detector is selected
            if (!$setting->moss && !$setting->jplag) {
                $content .= html_writer::tag('div',get_string('no_tool_selected','plagiarism_programming'),array('class'=>'programming_result_warning'));
                $button_disabled = true;
            }
            // check at least two assignments submitted
            $fs = get_file_storage();
            $file_records = $fs->get_area_files($context->id, 'mod_assignment', 'submission', false, 'userid', false);
            if (count($file_records)<2) {
                $content .= html_writer::tag('div',get_string('not_enough_submission','plagiarism_programming'));
                $button_disabled = true;
            }
            // write the rescan button
            $button_label = ($already_scanned)?
                    get_string('rescanning','plagiarism_programming'):
                    get_string('start_scanning','plagiarism_programming');
            $button_attr = array('type' => 'button',
                  'id' => 'plagiarism_programming_scan',
                  'value' => $button_label);
            if ($button_disabled)
                $button_attr['disabled'] = 'disabled';
            $content .= html_writer::empty_tag('input',$button_attr);

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
                $warning = get_string('high_similarity_warning','plagiarism_programming');
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
        $plagiarism_programming_setting = (array) get_config('plagiarism_programming');
        $enabled = $plagiarism_programming_setting['level_enabled']=='global' || 
            ($DB->get_record('programming_course_enabled',array('course'=>$course_id))!=false);
        return $enabled;
    }
}
