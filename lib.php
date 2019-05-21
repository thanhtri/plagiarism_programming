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
 *
 * Provide the site-wide setting and specific configuration for each assignment.
 *
 * @package    plagiarism_programming
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


// Get global class.
global $CFG;
require_once($CFG->dirroot.'/plagiarism/lib.php');
require_once(__DIR__.'/detection_tools.php');
require_once(__DIR__.'/reportlib.php');
require_once(__DIR__.'/scan_assignment.php');

/**
 * Class to integrate the plugin in the moodle submission workflow. See https://docs.moodle.org/dev/Plagiarism_plugins#Interfacing_to_APIs
 *
 * @copyright  2015 thanhtri, 2019 Benedikt Schneider (@Nullmann)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plagiarism_plugin_programming extends plagiarism_plugin {

    /**
     * @var $filemanageroption moodle filemanager to upload and delete files.
     */
    private $filemanageroption;

    /**
     * Constructor which initializes the options.
     */
    public function __construct() {
        $this->filemanageroption = array('subdir' => 0, 'maxbytes' => 20 * 1024 * 1024, 'maxfiles' => 50,
            'accepted_type' => array('*.zip', '*.rar'));
    }

    /**
     * Define the configuration block of plagiarism detection in assignment setting form.
     * This method will be called by mod_assignment_mod_form class, in its definition method
     *
     * @param object $mform  - Moodle form
     * @param object $context - current context
     * @param string $modulename - Name of the module
     */
    public function get_form_elements_module($mform, $context, $modulename='') {
        global $DB, $PAGE;

        // When updating an assignment, cmid of the assignment is passed by "update" param.
        // When creating an assignment, cmid does not exist, but course id is provided via "course" param.
        $cmid = optional_param('update', 0, PARAM_INT);
        $courseid = optional_param('course', 0, PARAM_INT);
        if (!$this->is_plugin_enabled($cmid, $courseid)) {
            return;
        }

        $plagiarismconfig = null;
        $assignmentcontext = null;
        if ($cmid) {
            $plagiarismconfig = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
            $assignmentcontext = context_module::instance($cmid);
        }

        $mform->addElement('header', 'programming_header',  get_string('plagiarism_header', 'plagiarism_programming'));

        // Enable or disable plagiarism checking.
        $enablechecking = array();
        $enablechecking[] = &$mform->createElement('radio', 'programmingYN', '', get_string('disable'), 0);
        $enablechecking[] = &$mform->createElement('radio', 'programmingYN', '', get_string('enable'), 1);
        $mform->addGroup($enablechecking, 'similarity_checking',
            get_string('programmingYN', 'plagiarism_programming'), array(' '), false);

        // Select the used programming language.
        $programminglanguages = array(
            'java' => 'Java',
            'c' => 'C/C++',
            'c#' => 'C#',
            'scheme' => 'Scheme',
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
            get_string('programming_language', 'plagiarism_programming'), $programminglanguages);

        // Disable the tools when no credentials are provided.
        $settings = get_config('plagiarism_programming');
        $jplagdisabled = null;
        if (empty($settings->jplag_user) || empty($settings->jplag_pass)) {
            $jplagdisabled = array('disabled' => true);
        }
        $mossdisabled = null;
        if (empty($settings->moss_user_id)) {
            $mossdisabled = array('disabled' => true);
        }

        // Check box for selecting the tools.
        $selectedtools = array();
        $selectedtools[] = &$mform->createElement('checkbox', 'jplag', '',
            get_string('jplag', 'plagiarism_programming'), $jplagdisabled);
        $selectedtools[] = &$mform->createElement('checkbox', 'moss', '',
            get_string('moss', 'plagiarism_programming'), $mossdisabled);
        $mform->addGroup($selectedtools, 'detection_tools', get_string('detection_tools', 'plagiarism_programming'));

        $this->setup_multiple_scandate($mform, $plagiarismconfig);

        $mform->addElement('checkbox', 'auto_publish', get_string('auto_publish', 'plagiarism_programming'));
        $mform->addElement('checkbox', 'notification', get_string('notification', 'plagiarism_programming'));
        $mform->addElement('textarea', 'notification_text', get_string('notification_text', 'plagiarism_programming'),
            'wrap="virtual" rows="4" cols="50"');
        $this->setup_code_seeding_filemanager($mform, $plagiarismconfig, $assignmentcontext);

        $mform->disabledIf('programming_language', 'programmingYN', 'eq', 0);
        $mform->disabledIf('auto_publish', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification_text', 'programmingYN', 'eq', 0);
        $mform->disabledIf('notification_text', 'notification', 'notchecked');
        /* jplag and moss checkbox is enabled and disabled by custom javascript */

        $mform->addHelpButton('similarity_checking', 'programmingYN_hlp', 'plagiarism_programming');
        $mform->addHelpButton('programming_language', 'programmingLanguage_hlp', 'plagiarism_programming');
        $mform->addHelpButton('detection_tools', 'detection_tools_hlp', 'plagiarism_programming');
        $mform->addHelpButton('auto_publish', 'auto_publish_hlp', 'plagiarism_programming');
        $mform->addHelpButton('notification', 'notification_hlp', 'plagiarism_programming');
        $mform->addHelpButton('notification_text', 'notification_text_hlp', 'plagiarism_programming');

        if ($plagiarismconfig) { // Update mode, populate the form with current values.
            $mform->setDefault('programmingYN', 1);
            $mform->setDefault('programming_language', $plagiarismconfig->language);
            $mform->setDefault('detection_tools[jplag]', $plagiarismconfig->jplag);
            $mform->setDefault('detection_tools[moss]', $plagiarismconfig->moss);
            $mform->setDefault('auto_publish', $plagiarismconfig->auto_publish);
            $mform->setDefault('notification', $plagiarismconfig->notification);
            $mform->setDefault('notification_text', $plagiarismconfig->notification_text);
        }
        if (empty($plagiarismconfig->notification_text)) {
            $mform->setDefault('notification_text',  get_string('notification_text_default', 'plagiarism_programming'));
        }

        // Disable tool if it doesn't support the selected language.
        include_once(__DIR__.'/jplag_tool.php');
        include_once(__DIR__.'/moss_tool.php');
        $jplagsupport = $jplagdisabled ? false : jplag_tool::get_supported_language();
        $mosssupport = $mossdisabled ? false : moss_tool::get_supported_language();
        // Include the javascript for doing some minor interface adjustment to improve user experience.
        $jsmodule = array(
            'name' => 'plagiarism_programming',
            'fullpath' => '/plagiarism/programming/assignment_setting.js',
            'requires' => array('base', 'node'),
            'strings' => array(
                array('no_tool_selected_error', 'plagiarism_programming'),
                array('invalid_submit_date_error', 'plagiarism_programming')
            )
        );
        $PAGE->requires->js_init_call('M.plagiarism_programming.assignment_setting.init',
            array($jplagsupport, $mosssupport), true, $jsmodule);
    }

    /**
     * Save the form into db
     * @param stdClass $data the data object retrieved from the form
     * @return void
     */
    public function save_form_elements($data) {

        global $DB, $detectiontools;

        $cmid = $data->coursemodule;
        $context = context_module::instance($cmid);
        if (!$this->is_plugin_enabled($cmid)) {
            return;
        }

        if (!empty($data->programmingYN)) { // The plugin is enabled for this assignment.
            $setting = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
            $new = false;
            if (!$setting) {
                $new = true;
                $setting = new stdClass();
                $setting->cmid = $cmid;
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
                $setting->id = $DB->insert_record('plagiarism_programming', $setting);
            } else {
                $DB->update_record('plagiarism_programming', $setting);
            }
            file_postupdate_standard_filemanager($data, 'code', $this->filemanageroption,
                $context, 'plagiarism_programming', 'codeseeding', $setting->id);

            $datenum = $data->submit_date_num;
            // Delete all unfinished records. They will be added again later when they are still enabled.
            $DB->delete_records('plagiarism_programming_date', array('settingid' => $setting->id, 'finished' => 0));

            // Save dates.
            for ($i = 0; $i < $datenum; $i++) {
                if (isset($data->scan_date[$i]) && $data->scan_date[$i] > 0) {
                    $scandateobj = new stdClass();
                    $scandateobj->scan_date = $data->scan_date[$i];
                    $scandateobj->finished = 0;
                    $scandateobj->settingid = $setting->id;

                    $DB->insert_record('plagiarism_programming_date', $scandateobj);
                }
            }

            // Either save in *_jplag or *_moss table.
            foreach ($detectiontools as $toolname => $info) {
                if ($setting->$toolname && !$DB->get_record('plagiarism_programming_'.$toolname,
                    array('settingid' => $setting->id))) {

                    $jplagrec = new stdClass();
                    $jplagrec->settingid = $setting->id;
                    $jplagrec->status = 'pending';
                    $DB->insert_record('plagiarism_programming_'.$toolname, $jplagrec);
                }
            }

        } else { // Plugin not enabled, delete the records if there are.
            plagiarism_programming_delete_config($cmid);
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
        global $DB;

        // These static variables are for caching.
        // As this function will be called a lot of time in grade listing.
        static $students = null, $context = null, $canshow = null;

        $cmid = $linkarray['cmid'];
        $studentid = $linkarray['userid'];
        if ($canshow == null) { // Those computed values are cached in static variables and reused.
            $canshow = $this->is_plugin_enabled($cmid);
            if ($canshow) {
                $setting = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));
                $canshow = $setting != null;
            }
            if ($canshow) {
                if ($setting->moss) {
                    $mossparam = $DB->get_record('plagiarism_programming_moss', array('settingid' => $setting->id));
                }
                if ($setting->jplag) {
                    $jplagparam = $DB->get_record('plagiarism_programming_jplag', array('settingid' => $setting->id));
                }
                $canshow = (isset($mossparam) && $mossparam->status == 'finished') ||
                    (isset($jplagparam) && $jplagparam->status == 'finished');
            }
            if ($canshow) {
                $context = context_module::instance($cmid);
                $isteacher = has_capability('mod/assignment:grade', $context);
                $canshow = $isteacher || ($setting->auto_publish && has_capability('mod/assignment:view', $context));

                if ($isteacher) {
                    $students = plagiarism_programming_get_students_similarity_info($cmid);
                } else {
                    $students = plagiarism_programming_get_students_similarity_info($cmid, $studentid);
                }
            }
        }

        $output = '';
        if ($canshow) {
            if (isset($students[$studentid])) {
                $link = plagiarism_programming_get_report_link($cmid, $studentid, $students[$studentid]['detector'], 0);
                $maxrate = round($students[$studentid]['max'], 2);
                $output = get_string('max_similarity', 'plagiarism_programming').': '.html_writer::link($link, "$maxrate%");
                if ($students[$studentid]['mark'] == 'Y') {
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
     * Print the disclosure on the assignment page
     * @param int $cmid - course module id
     * @return string
     */
    public function print_disclosure($cmid) {
        global $OUTPUT, $DB, $USER;
        $setting = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));

        // Is the plugin enabled for this course?
        if (!$this->is_plugin_enabled($cmid)) {
            return '';
        }

        if (!$setting) { // Plagiarism scanning turned off.
            return '';
        }

        $context = context_module::instance($cmid);

        // The user must be a student (or teacher).
        if (!has_capability('mod/assignment:submit', $context, $USER->id)) {
            return '';
        }

        $content = '';
        if ($setting->notification) {
            $content = format_text($setting->notification_text, FORMAT_MOODLE);
            $scandates = $DB->get_records('plagiarism_programming_date', array('settingid' => $setting->id, 'finished' => 0),
                    'scan_date ASC');
            if (count($scandates) > 0) {
                // Get the first scan date.
                $scandate = array_shift($scandates);
                $content .= html_writer::tag('div', get_string('scheduled_scanning', 'plagiarism_programming').' '.
                    date('D j M', $scandate->scan_date));
            }
        }
        if ($setting->auto_publish && count(plagiarism_programming_get_suspicious_works($USER->id, $cmid)) > 0) {
            $warning = get_string('high_similarity_warning', 'plagiarism_programming');
            $content .= html_writer::tag('span', $warning, array('class' => 'programming_result_warning'));
        }

        if ($content) {
            return $OUTPUT->box_start('generalbox boxaligncenter', 'plagiarism_info')
                .$content
                .$OUTPUT->box_end();
        } else {
            return '';
        }
    }

    /**
     * Integrates the similarity check into the grading-page of moodle.
     *
     * @param object $course - full course object
     * @param object $cm - full context module object
     */
    public function update_status($course, $cm) {
        global $OUTPUT, $DB, $USER, $CFG, $PAGE, $detectiontools;
        $cmid = $cm->id;
        $setting = $DB->get_record('plagiarism_programming', array('cmid' => $cmid));

        // Is the plugin enabled for this course?
        if (!$this->is_plugin_enabled($cmid)) {
            return '';
        }

        if (!$setting) { // Plagiarism scanning turned off for this assignment.
            return '';
        }

        $context = context_module::instance($cmid);
        // Not a teacher.
        if (!has_capability('mod/assignment:grade', $context, $USER->id)) {
            return '';
        }
        $content = '';

        $alreadyscanned = false;

        $buttondisabled = false;
        // Check at least one detector is selected.
        if (!$setting->moss && !$setting->jplag) {
            $content .= $OUTPUT->notification(get_string('no_tool_selected', 'plagiarism_programming'), 'notifyproblem');
            $buttondisabled = true;
        }

        $check = array();
        foreach ($detectiontools as $tool => $toolinfo) {
            // If the tool is selected.
            if (!$setting->$tool) {
                continue;
            }

            $toolname = $toolinfo['name'];
            $scanninginfo = $DB->get_record('plagiarism_programming_'.$tool, array('settingid' => $setting->id));

            $info = $scanninginfo->status;
            switch ($scanninginfo->status) {
                case null:
                case 'pending':
                        $info = get_string('pending', 'plagiarism_programming');
                    break;
                case 'finished':
                    include_once($toolinfo['code_file']);
                    $classname = $toolinfo['class_name'];
                    $toolclass = new $classname();
                    $info = $toolclass->display_link($setting);
                    break;
                case 'error':
                    $info = "Error: $scanninginfo->message";
                    break;
            }

            $infotag = html_writer::tag('span', $info, array('id' => $tool.'_status'));
            $content .= html_writer::tag('div', "<span style='font-weight: bold'>$toolname</span>: $infotag",
                    array('class' => 'text_to_html'));
            $content .= html_writer::tag('div', '', array('id' => $tool.'_tool', 'class' => 'yui-skin-sam'));
            $needchecking = (
                    $scanninginfo->status != 'pending'  &&
                    $scanninginfo->status != 'finished' &&
                    $scanninginfo->status != 'error');
            $check[$tool] = $needchecking;
            $alreadyscanned |= $scanninginfo->status == 'finished'||$scanninginfo->status == 'error';
        }

        if ($setting->latestscan) {
            $content .= get_string('latestscan', 'plagiarism_programming').' '.  date('h.i A D j M', $setting->latestscan);
        }
        $scandates = $DB->get_records('plagiarism_programming_date', array('settingid' => $setting->id, 'finished' => 0),
                'scan_date ASC');
        if (count($scandates) > 0) {
            // Get the first scan date.
            $scandate = array_shift($scandates);
            $content .= html_writer::tag('div', get_string('scheduled_scanning', 'plagiarism_programming').' '.
                date('D j M', $scandate->scan_date));
        } else {
            $content .= html_writer::tag('div', get_string('no_scheduled_scanning', 'plagiarism_programming'));
        }

        $content .= html_writer::tag('div', get_string('manual_scheduling_help', 'plagiarism_programming'),
            array('style' => 'margin-top:5px'));
        // Check at least two assignments submitted.

        $filerecords = plagiarism_programming_get_submitted_files($context);
        if (count($filerecords) < 2) {
            $content .= html_writer::tag('div', get_string('not_enough_submission', 'plagiarism_programming'));
            $buttondisabled = true;
        }
        // Write the rescan button.
        $buttonlabel = ($alreadyscanned) ?
                get_string('rescanning', 'plagiarism_programming') :
                get_string('start_scanning', 'plagiarism_programming');
        $buttonattr = array('type' => 'submit',
                'id' => 'plagiarism_programming_scan',
                'value' => $buttonlabel);
        if ($buttondisabled) {
            $buttonattr['disabled'] = 'disabled';
        }
        $scanbutton = html_writer::empty_tag('input', $buttonattr);
        $content .= html_writer::tag('form', $scanbutton, array('method' => 'post',
            'action' => "$CFG->wwwroot/plagiarism/programming/start_scanning.php?task=scan&cmid=$cmid"));
        $content .= html_writer::tag('span', get_string('scanning_in_progress', 'plagiarism_programming'),
            array('style' => 'display:none', 'id' => 'scan_message'));

        // Include the javascript.
        $PAGE->requires->js('/plagiarism/programming/progressbar.js');
        $jsmodule = array(
            'name' => 'plagiarism_programming',
            'fullpath' => '/plagiarism/programming/scanning.js',
            'requires' => array('progressbar', 'json', 'io'),
            'strings' => array(
                array('pending_start', 'plagiarism_programming'),
                array('uploading', 'plagiarism_programming'),
                array('scanning', 'plagiarism_programming'),
                array('downloading', 'plagiarism_programming')
            )
        );
        $PAGE->requires->js_init_call('M.plagiarism_programming.initialise',
                array('cmid' => $setting->cmid, 'checkprogress' => $check), false, $jsmodule);

        return $OUTPUT->box_start('generalbox boxaligncenter', 'plagiarism_info')
              .$content
              .$OUTPUT->box_end();
    }

    /**
     * If the plugin is enabled or not (at Moodle level or at course level)
     * @param Number $cmid The course module id (can provide the course id instead)
     * @param Number $courseid The course id. If course_id is passed, cmid is ignored
     * @return boolean
     */
    public function is_plugin_enabled($cmid, $courseid=null) {
        global $DB;

        $settings = (array) get_config('plagiarism');
        if (!$settings['programming_use']) { // Globaly disabled.
            return false;
        }

        $plagiarismprogrammingsetting = (array) get_config('plagiarism_programming');
        if ($plagiarismprogrammingsetting['level_enabled'] == 'global') { // Globally enabled.
            return true;
        }

        // Specifically enabled for some courses.
        if (!$courseid) {
            $coursemodule = get_coursemodule_from_id('', $cmid);
            $courseid = ($coursemodule) ? $coursemodule->course : 0;
        }
        return $DB->get_record('plagiarism_programming_cours', array('course' => $courseid)) != false;
    }

    /**

     */

    /**
     * This function will setup multiple scan dates for the form.
     * This will be similar to the repeat group of moodle form.
     * However, since just an instance of $mform is passed in,
     * it is not possible to call the protected function repeat_elements
     *
     * @param object $mform
     * @param object $plagiarismconfig
     */
    private function setup_multiple_scandate($mform, $plagiarismconfig) {
        global $DB;

        $scandates = array();
        $constantvars = array();
        if ($plagiarismconfig) {
            $scandates = $DB->get_records('plagiarism_programming_date',
                array('settingid' => $plagiarismconfig->id), 'scan_date ASC');
        }
        $dbscandate = count($scandates);

        $datenum = optional_param('submit_date_num', max($dbscandate, 1), PARAM_INT);
        $isadddate = optional_param('add_new_date', '', PARAM_TEXT); // Add another form element if new date button was pushed.
        if (!empty($isadddate)) { // The hidden element, combined with javascript, makes the form jump to the date position.
            // Add another date picker.
            $datenum++;
            $mform->addElement('hidden', 'is_add_date', 1);
            $constantvars['is_add_date'] = 1;
        } else {
            // Add first date picker if this is the first time the settings page is called.
            $mform->addElement('hidden', 'is_add_date', 0);
            $constantvars['is_add_date'] = 0;
        }
        $mform->setType('is_add_date', PARAM_BOOL);

        $i = 0;
        foreach ($scandates as $scandate) {
            if ($scandate->finished) {
                $name = "scan_date_finished[$i]";
                $mform->addElement('date_selector', $name, get_string('scan_date_finished', 'plagiarism_programming'),
                        null, array('disabled' => 'disabled'));
                $constantvars[$name] = $scandate->scan_date;
            } else {
                $name = "scan_date[$i]";
                $mform->addElement('date_selector', "scan_date[$i]", get_string('scan_date', 'plagiarism_programming'),
                    array('optional' => true));
                $mform->disabledIf($name, 'programmingYN', 'eq', 0);
            }
            $mform->setDefault($name, $scandate->scan_date);
            $mform->addHelpButton($name, 'date_selector_hlp', 'plagiarism_programming');
            $i++;
        }
        for ($i = $dbscandate; $i < $datenum; $i++) {
            $mform->addElement('date_selector', "scan_date[$i]", get_string('scan_date', 'plagiarism_programming'),
                array('optional' => true));
            $mform->addHelpButton("scan_date[$i]", 'date_selector_hlp', 'plagiarism_programming');
        }

        $mform->addElement('hidden', 'submit_date_num', $datenum);
        $mform->setType('submit_date_num', PARAM_INT);
        $mform->setConstants(array('submit_date_num' => $datenum));
        $mform->addElement('submit', 'add_new_date', get_string('new_scan_date', 'plagiarism_programming'));
        $mform->disabledIf('add_new_date', 'programmingYN', 'eq', 0);
        $mform->registerNoSubmitButton('add_new_date');
        $mform->setConstants($constantvars);
    }

    /**
     * Sets up the filemanager for uploading additional libraries to compare against.
     *
     * @param object $mform
     * @param object $plagiarismconfig contents of the table plagiarism_programming
     * @param object $assignmentcontext context_module
     */
    private function setup_code_seeding_filemanager($mform, $plagiarismconfig, $assignmentcontext) {

        $mform->addElement('filemanager', 'code_filemanager', get_string('additional_code', 'plagiarism_programming'),
            null, $this->filemanageroption);
        $mform->addHelpButton('code_filemanager', 'additional_code_hlp', 'plagiarism_programming');
        $data = new stdClass();
        file_prepare_standard_filemanager($data, 'code', $this->filemanageroption,
                $assignmentcontext, 'plagiarism_programming', 'codeseeding',
                ($plagiarismconfig) ? $plagiarismconfig->id : null);
        $mform->setDefault('code_filemanager', $data->code_filemanager);
    }
}