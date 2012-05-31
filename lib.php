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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


//get global class
global $CFG;
require_once($CFG->dirroot.'/plagiarism/lib.php');
require_once(__DIR__.'/detection_tools.php');
require_once(__DIR__.'/reportlib.php');

class plagiarism_plugin_programming extends plagiarism_plugin {

    /**
     * Define the configuration block of plagiarism detection in assignment setting form.
     * This method will be called by mod_assignment_mod_form class, in its definition method
     * @param MoodleQuickForm $mform the assignment form
     * @param stdClass $context the context record object
     */
    public function get_form_elements_module($mform, $context) {
        global $DB, $PAGE;
        // when updating an assignment, cmid is passed by "update" param
        // when creating an assignment, cmid does not exist, but course id is provided via "course" param
        $cmid = optional_param('update', 0, PARAM_INT);
        $course_id = optional_param('course', 0, PARAM_INT);
        if (!$this->is_plugin_enabled($cmid, $course_id)) {
            return;
        }

        $plagiarism_config = null;
        if ($cmid) {
            $plagiarism_config = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
        }

        $settings = get_config('plagiarism_programming');

        $mform->addElement('header', 'programming_header',  get_string('plagiarism_header', 'plagiarism_programming'));

        // Enable or disable plagiarism checking
        $enable_checking = array();
        $enable_checking[] = $mform->createElement('radio', 'programmingYN', '', get_string('disable'), 0);
        $enable_checking[] = $mform->createElement('radio', 'programmingYN', '', get_string('enable'), 1);
        $mform->addGroup($enable_checking, 'similarity_checking',
            get_string('programmingYN', 'plagiarism_programming'), array(' '), false);

        // Select the language used
        $programming_languages = array(
            'java' => 'Java',
            'c' => 'C/C++',
            'c#' => 'C#',
            'scheme'=>'Scheme',
            'text' => 'Plain text',
            'python' => 'Python',
            'vb' => 'Visual Basic',
            'js' => 'Javascript',
            'pascal' => 'Pascal',
            'lisp' => 'Lisp',
            'perl' => 'Perl',
            'prolog' => 'Prolog',
            'plsql' => 'Pl-SQL',
            'mathlab' => 'MathLab',
            'mips' => 'MPIS Assembly',
            'a8086' => '8086 Assembly',
            'fortran' => 'Fortran'
        );
        $mform->addElement('select', 'programming_language',
            get_string('programming_language', 'plagiarism_programming'), $programming_languages);

        $warning_style = array('class' => 'programming_result_warning');
        $mform->addElement('hidden', 'for_disabled', 1);
        if (empty($settings->jplag_user) || empty($settings->jplag_pass)) {
            $mform->addElement('html', html_writer::tag('div', get_string('jplag_credential_missing', 'plagiarism_programming'),
                $warning_style));
        }
        if (empty($settings->moss_user_id)) {
            $mform->addElement('html', html_writer::tag('div', get_string('moss_credential_missing', 'plagiarism_programming'),
                $warning_style));
            $mform->disabledIf('detection_tools[moss]', 'for_disabled', 'eq', 1);
        }
        if (empty($settings->jplag_user) || empty($settings->jplag_pass) || empty($settings->moss_user_id)) {
            $mform->addElement('html', html_writer::tag('div', get_string('credential_missing_instruction',
                    'plagiarism_programming'), $warning_style));
        }

        $selected_tools = array();
        $selected_tools[] = &$mform->createElement('checkbox', 'jplag', '', get_string('jplag', 'plagiarism_programming'));
        $selected_tools[] = &$mform->createElement('checkbox', 'moss', '', get_string('moss', 'plagiarism_programming'));
        $mform->addGroup($selected_tools, 'detection_tools', get_string('detection_tools', 'plagiarism_programming'));

        $this->setup_multiple_scandate($mform, $plagiarism_config);

        $mform->addElement('checkbox', 'auto_publish', get_string('auto_publish', 'plagiarism_programming'));
        $mform->addElement('checkbox', 'notification', get_string('notification', 'plagiarism_programming'));
        $mform->addElement('textarea', 'notification_text', get_string('notification_text', 'plagiarism_programming'),
            'wrap="virtual" rows="4" cols="50"');

        $mform->disabledIf('detection_tools', 'programmingYN', 'eq', 0);
        $mform->disabledIf('programming_language', 'programmingYN', 'eq', 0);
        $mform->disabledIf('auto_publish', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification_text', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification_text', 'notification', 'notchecked');

        // disable tool if it doesn't support the selected language
        include_once(__DIR__.'/jplag_tool.php');
        include_once(__DIR__.'/moss_tool.php');

        $jplag_support = jplag_tool::get_supported_language();
        $moss_support = moss_tool::get_supported_laguage();
        foreach ($programming_languages as $code => $name) {
            if (!isset($jplag_support[$code])) {
                $mform->disabledIf('detection_tools[jplag]', 'programming_language', 'eq', $code);
            }
            if (!isset($moss_support[$code])) {
                $mform->disabledIf('detection_tools[moss]', 'programming_language', 'eq', $code);
            }
        }

        $mform->addHelpButton('similarity_checking', 'programmingYN_hlp', 'plagiarism_programming');
        $mform->addHelpButton('programming_language', 'programmingLanguage_hlp', 'plagiarism_programming');
        $mform->addHelpButton('auto_publish', 'auto_publish_hlp', 'plagiarism_programming');
        $mform->addHelpButton('notification', 'notification_hlp', 'plagiarism_programming');
        $mform->addHelpButton('notification_text', 'notification_text_hlp', 'plagiarism_programming');

        if ($plagiarism_config) { // update mode, populate the form with current values
            $mform->setDefault('programmingYN', 1);
            $mform->setDefault('programming_language', $plagiarism_config->language);
            $mform->setDefault('detection_tools[jplag]', $plagiarism_config->jplag);
            $mform->setDefault('detection_tools[moss]', $plagiarism_config->moss);
            $mform->setDefault('auto_publish', $plagiarism_config->auto_publish);
            $mform->setDefault('notification', $plagiarism_config->notification);
            $mform->setDefault('notification_text', $plagiarism_config->notification_text);
        }
        if (!$plagiarism_config || empty($plagiarism_config->notification_text)) {
            $mform->setDefault('programming_language', 'java');
            $mform->setDefault('notification_text',  get_string('notification_text_default', 'plagiarism_programming'));
        }

        // include the javascript for doing some minor interface adjustment to improve user experience
        $js_module = array(
            'name' => 'plagiarism_programming',
            'fullpath' => '/plagiarism/programming/assignment_setting.js',
            'strings' => array()
        );
        $PAGE->requires->js_init_call('M.plagiarism_programming.assignment_setting.init', null, true, $js_module);
    }

    /**
     * Save the form into db
     * @param stdClass $data the data object retrieved from the form
     * @return void
     */
    public function save_form_elements($data) {
        global $DB, $detection_tools;
        $cmid = $data->coursemodule;
        if (!$this->is_plugin_enabled($cmid)) {
            return;
        }

        if ($data->programmingYN) { // the plugin is enabled for this assignment
            $setting = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
            $new = false;
            if (!$setting) {
                $new = true;
                $setting = new stdClass();
                $setting->courseid = $data->coursemodule;
            }

            $setting->language = $data->programming_language;
            $setting->jplag = isset($data->detection_tools['jplag']) ? 1 : 0;
            $setting->moss = isset($data->detection_tools['moss']) ? 1 : 0;
            $setting->auto_publish = isset($data->auto_publish) ? 1 : 0;
            if (isset($data->notification)) {
                $setting->notification = 1;
                $setting->notification_text = $data->notification_text;
            } else {
                $setting->notification = 0;
                $setting->notification_text = '';
            }

            if ($new) {
                $setting->id = $DB->insert_record('programming_plagiarism', $setting);
            } else {
                $DB->update_record('programming_plagiarism', $setting);
            }

            $date_num = $data->submit_date_num;
            $DB->delete_records('programming_scan_date', array('settingid'=>$setting->id, 'finished'=>0));

            for ($i=0; $i<$date_num; $i++) {
                $element_name = "scan_date[$i]";
                if (isset($data->$element_name) && isset($data->$element_name) && $data->$element_name > 0) {
                    $scan_date_obj = new stdClass();
                    $scan_date_obj->scan_date = $data->$element_name;
                    $scan_date_obj->finished = 0;
                    $scan_date_obj->settingid = $setting->id;

                    $DB->insert_record('programming_scan_date', $scan_date_obj);
                }
            }
            foreach ($detection_tools as $toolname => $info) {
                if ($setting->$toolname && !$DB->get_record('programming_'.$toolname, array('settingid'=>$setting->id))) {
                    $jplag_rec = new stdClass();
                    $jplag_rec->settingid = $setting->id;
                    $jplag_rec->status = 'pending';
                    $DB->insert_record('programming_'.$toolname, $jplag_rec);
                }
            }

        } else { // plugin not enabled, delete the records if there are
            $settings = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
            $DB->delete_records('programming_scan_date', array('settingid'=>$settings->id));
            $DB->delete_records('programming_jplag', array('settingid'=>$settings->id));
            $DB->delete_records('programming_moss', array('settingid'=>$settings->id));
            $DB->delete_records('programming_plagiarism', array('id'=>$settings->id));
        }
    }

    /**
     * Called by the cron process
     */
    public function cron() {
        include('programming_cron.php');
    }

    /**
     * Display similarity information beside each submission
     * @param array $linkarray contain relevant information
     */
    public function get_links($linkarray) {
        global $DB, $CFG;

        static $students, $context, $can_show; // these static variables are just for catching purpose

        $cmid = $linkarray['cmid'];
        $student_id = $linkarray['userid'];
        if ($can_show==null) { //those computed values are cached in static variables and reused
            $can_show = $this->is_plugin_enabled($cmid);
            if ($can_show) {
                $setting = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
                $can_show = $setting!=null;
            }
            if ($can_show) {
                if ($setting->moss) {
                    $moss_param = $DB->get_record('programming_moss', array('settingid'=>$setting->id));
                }
                if ($setting->jplag) {
                    $jplag_param = $DB->get_record('programming_jplag', array('settingid'=>$setting->id));
                }
                $can_show = (isset($moss_param) && $moss_param->status=='finished') ||
                    (isset($jplag_param) && $jplag_param->status=='finished');
            }
            if ($can_show) {
                $context = get_context_instance(CONTEXT_MODULE, $cmid);
                $is_teacher = has_capability('mod/assignment:grade', $context);
                $can_show = $is_teacher || ($setting->auto_publish && has_capability('mod/assignment:view', $context));

                if ($is_teacher) {
                    $students = get_students_similarity_info($cmid);
                } else {
                    $students = get_students_similarity_info($cmid, $student_id);
                }
            }
        }

        $output = '';
        if ($can_show) {
            if (isset($students[$student_id])) {
                $link = get_report_link($cmid, $student_id, $students[$student_id]['detector'], 0);
                $max_rate = $students[$student_id]['max'];
                $output = get_string('max_similarity', 'plagiarism_programming').': '.html_writer::link($link, "$max_rate%");
                if ($students[$student_id]['mark']=='Y') {
                    $output .= ' '.html_writer::tag('span', get_string('suspicious', 'plagiarism_programming'),
                        array('class' => 'programming_result_warning'));
                }
            } else {
                $output .= get_string('no_similarity', 'plagiarism_programming');
            }
        }
        return $output;
    }

    /**
     * Print some information on the assignment page
     */
    public function print_disclosure($cmid) {
        global $OUTPUT, $DB, $USER, $CFG, $PAGE, $detection_tools;
        $setting = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));

        // the plugin is enabled for this course ?
        if (!$this->is_plugin_enabled($cmid)) {
            return;
        }

        if (!$setting) { // plagiarism scanning turned off
            return;
        }

        $content = '';

        // if plagiarism report available, display link to report
        $context = get_context_instance(CONTEXT_MODULE, $cmid);
        $already_scanned = false;
        $is_teacher = has_capability('mod/assignment:grade', $context, $USER->id);
        if ($is_teacher) {
            $check = array();
            foreach ($detection_tools as $tool => $tool_info) {
                // if the tool is selected
                if (!$setting->$tool) {
                    continue;
                }

                $toolname = $tool_info['name'];
                $scanning_info = $DB->get_record('programming_'.$tool, array('settingid' => $setting->id));

                $info = $scanning_info->status;
                switch ($scanning_info->status) {
                    case null: case 'pending':
                        $info='not started';
                        break;
                    case 'finished':
                        include_once($tool_info['code_file']);
                        $class_name = $tool_info['class_name'];
                        $tool_class = new $class_name();
                        $info = $tool_class->display_link($setting);
                        break;
                    case 'error':
                        $info = "Error: $scanning_info->message";
                        break;
                }

                $info_tag=html_writer::tag('span', $info, array('id' => $tool.'_status'));
                $content .= html_writer::tag('div', "<span style='font-weight: bold'>$toolname</span>: $info_tag",
                        array('class' => 'text_to_html'));
                $content .= html_writer::tag('div', '', array('id' => $tool.'_tool', 'class' => 'yui-skin-sam'));
                $need_checking = (
                        $scanning_info->status!='pending'  &&
                        $scanning_info->status!='finished' &&
                        $scanning_info->status!='error');
                $check[$tool] = $need_checking;
                $already_scanned |= $scanning_info->status=='finished'||$scanning_info->status=='error';
            }

            if ($setting->latestscan) {
                $content .= get_string('latestscan', 'plagiarism_programming').' '.  date('h.i A D j M', $setting->latestscan);
            }
            $scan_dates = $DB->get_records('programming_scan_date', array('settingid'=>$setting->id, 'finished'=>0),
                    'scan_date ASC');
            if (count($scan_dates)>0) {
                // get the first scan date
                $scan_date = array_shift($scan_dates);
                $content .= html_writer::tag('div', get_string('scheduled_scanning', 'plagiarism_programming').' '.
                    date('D j M', $scan_date->scan_date));
            } else {
                $content .= html_writer::tag('div', get_string('no_scheduled_scanning', 'plagiarism_programming'));
            }

            $button_disabled = false;
            // check at least one detector is selected
            if (!$setting->moss && !$setting->jplag) {
                $content .= html_writer::tag('div', get_string('no_tool_selected', 'plagiarism_programming'),
                    array('class' => 'programming_result_warning'));
                $button_disabled = true;
            }
            $content .= html_writer::tag('div', get_string('manual_scheduling_help', 'plagiarism_programming'),
                array('style'=>'margin-top:5px'));
            // check at least two assignments submitted
            $fs = get_file_storage();
            $file_records = $fs->get_area_files($context->id, 'mod_assignment', 'submission', false, 'userid', false);
            if (count($file_records) < 2) {
                $content .= html_writer::tag('div', get_string('not_enough_submission', 'plagiarism_programming'));
                $button_disabled = true;
            }
            // write the rescan button
            $button_label = ($already_scanned)?
                    get_string('rescanning', 'plagiarism_programming'):
                    get_string('start_scanning', 'plagiarism_programming');
            $button_attr = array('type' => 'button',
                  'id' => 'plagiarism_programming_scan',
                  'value' => $button_label);
            if ($button_disabled) {
                $button_attr['disabled'] = 'disabled';
            }
            $content .= html_writer::empty_tag('input', $button_attr);

            $PAGE->requires->yui2_lib('progressbar');
            $PAGE->requires->yui2_lib('json');

            // include the javascript
            $jsmodule = array(
                'name' => 'plagiarism_programming',
                'fullpath' => '/plagiarism/programming/scanning.js',
                'strings' => array()
            );

            $PAGE->requires->js_init_call('M.plagiarism_programming.initialise',
                    array('cmid' => $setting->courseid, 'checkprogress' => $check), true, $jsmodule);

        }

        // if this is a student
        if (!$is_teacher && has_capability('mod/assignment:submit', $context, $USER->id)) {
            if ($setting->notification) {
                $content = format_text($setting->notification_text, FORMAT_MOODLE);
                $scan_dates = $DB->get_records('programming_scan_date', array('settingid'=>$setting->id, 'finished'=>0),
                        'scan_date ASC');
                if (count($scan_dates)>0) {
                    // get the first scan date
                    $scan_date = array_shift($scan_dates);
                    $content .= html_writer::tag('div', get_string('scheduled_scanning', 'plagiarism_programming').' '.
                        date('D j M', $scan_date->scan_date));
                }
            }
            if ($setting->auto_publish && count(get_suspicious_works($USER->id, $cmid))>0) {
                $warning = get_string('high_similarity_warning', 'plagiarism_programming');
                $content .= html_writer::tag('span', $warning, array('class' => 'programming_result_warning'));
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
    public function is_plugin_enabled($cmid, $course_id=null) {
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
            ($DB->get_record('programming_course_enabled', array('course' => $course_id))!=false);
        return $enabled;
    }

    /**
     * This function will setup multiple scan date of the form
     */
    private function setup_multiple_scandate($mform, $plagiarism_config) {
        global $DB;

        $scan_dates = array();
        if ($plagiarism_config) {
            $scan_dates = $DB->get_records('programming_scan_date', array('settingid'=>$plagiarism_config->id), 'scan_date ASC');
        }
        $db_scandate = count($scan_dates);

        $date_num = optional_param('submit_date_num', max($db_scandate, 1), PARAM_INT);
        $is_add_date = optional_param('add_new_date', '', PARAM_TEXT);
        if (!empty($is_add_date)) {
            $date_num++;
        }

        $i = 0;
        foreach ($scan_dates as $scan_date) {
            if ($scan_date->finished) {
                $name = "scan_date_finished[$i]";
                $mform->addElement('date_selector', $name, get_string('scan_date_finished', 'plagiarism_programming'),
                        null, array('disabled'=>'disabled'));
            } else {
                $name = "scan_date[$i]";
                $mform->addElement('date_selector', "scan_date[$i]", get_string('scan_date', 'plagiarism_programming'),
                    array('optional'=>true));
                $mform->disabledIf($name, 'programmingYN', 'eq', 0);
            }
            $mform->setDefault($name, $scan_date->scan_date);
            $mform->addHelpButton($name, 'date_selector_hlp', 'plagiarism_programming');
            $i++;
        }
        for ($i=$db_scandate; $i<$date_num; $i++) {
            $mform->addElement('date_selector', "scan_date[$i]", get_string('scan_date', 'plagiarism_programming'),
                array('optional'=>true));
            $mform->addHelpButton("scan_date[$i]", 'date_selector_hlp', 'plagiarism_programming');
        }

        $mform->addElement('hidden', 'submit_date_num', $date_num);
        $mform->setConstants(array('submit_date_num'=>$date_num));
        $mform->addElement('submit', 'add_new_date', get_string('new_scan_date', 'plagiarism_programming'));
        $mform->disabledIf('add_new_date', 'programmingYN', 'eq', 0);
        $mform->registerNoSubmitButton('add_new_date');
    }
}
