<?php

/**
 * Autoscan task
 *
 * @package    plagiarism
 * @subpackage programming
 * @copyright  &copy; 2016 Kineo Pacific {@link http://kineo.com.au}
 * @author     tri.le
 * @version    1.0
 */

namespace plagiarism_programming\task;

class autoscan_task extends \core\task\task_base{

    public function execute() {
        global $DB, $CFG, $detection_tools;
        require_once($CFG->dirroot.'/plagiarism/programming/utils.php');
        require_once(__DIR__.'/plagiarism/programming/scan_assignment.php');
        require_once(__DIR__.'/plagiarism/programming/detection_tools.php');

        // select the assignments needed to be scanned
        plagiarism_programming_create_temp_dir();
        $current_time = time();
        $settngids = $DB->get_fieldset_select('plagiarism_programming_date', 'settingid', "finished=0 AND scan_date<=$current_time");
        $settngids = array_unique($settngids);

        echo "Start sending submissions to plagiarism tools\n";
        foreach ($settngids as $setting_id) {
            $assignment_config = $DB->get_record('plagiarism_programming', array('id'=>$setting_id));

            // check whether the assignment is already deleted or not (for safety)
            $assignment_ctx = context_module::instance($assignment_config->cmid, IGNORE_MISSING);
            if (!$assignment_ctx) {
                plagiarism_programming_delete_config($assignment_config->cmid);
                continue;
            }

            if (!$this->scanning_in_progress($assignment_config)) { // reset the scanning
                foreach ($detection_tools as $toolname => $toolinfo) {
                    if ($assignment_config->$toolname) {
                        $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
                        $tool_status->status = 'pending';
                        $tool_status->message = '';
                        $tool_status->error_detail = '';
                        $DB->update_record('plagiarism_programming_'.$toolname, $tool_status);
                    }
                }
            }
            // do not wait for result, the next cron script will check the status and download the result
            // send an email when scanning complete
            plagiarism_programming_scan_assignment($assignment_config, false, true);

            $all_tools_finished = true;

            // check if the scanning has been done to mark the date as finished
            foreach ($detection_tools as $toolname => $toolinfo) {
                if ($assignment_config->$toolname) {
                    $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
                    if ($tool_status->status!='finished' && $tool_status->status!='error') {
                        $all_tools_finished = false;
                        break;
                    }
                }
            }
            if ($all_tools_finished) {
                $scan_dates = $DB->get_records_select('plagiarism_programming_date',
                    "settingid=$assignment_config->id AND finished=0 AND scan_date<$current_time", null, 'scan_date ASC');
                $scan_date = array_shift($scan_dates);
                $scan_date->finished=1;
                $DB->update_record('plagiarism_programming_date', $scan_date);
            }

        }
        echo "Finished sending submissions to plagiarism tools\n";

    }

    function scanning_in_progress($assignment_config) {
        global $DB, $detection_tools;
        foreach ($detection_tools as $toolname => $toolinfo) {
            if ($assignment_config->$toolname) {
                $tool_status = $DB->get_record('plagiarism_programming_'.$toolname, array('settingid'=>$assignment_config->id));
                if ($tool_status->status!='pending' && $tool_status->status!='finished' && $tool_status->status!='error') {
                    return true;
                }
            }
        }
        return false;
    }
}

