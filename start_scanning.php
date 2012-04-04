<?php
include dirname(__FILE__).'/../../config.php';
include_once dirname(__FILE__).'/scan_assignment.php';
include_once dirname(__FILE__).'/detection_tools.php';
global $DB;
session_write_close();

$cmid = required_param('cmid', PARAM_INT);
$task = required_param('task', PARAM_TEXT);
$assignment = $DB->get_record('programming_plagiarism', array('courseid'=>$cmid));
if (!$assignment) {
    echo 'Invalid assignment!';
}
// possible values are scan, check and download
if ($task=='scan') {
    ignore_user_abort();
    set_time_limit(0);
    start_scan_assignment($assignment);
} elseif ($task=='check') {
    $starttime = optional_param('time', 0, PARAM_INT);
    check_status($assignment,$starttime);
} elseif ($task=='download') {
    ignore_user_abort();
    set_time_limit(0);
    download_assignment($assignment);
}

function start_scan_assignment($assignment) {
    global $DB,$detection_tools;
    $assignment->starttime = time();
    $DB->update_record('programming_plagiarism',$assignment);

    // update the status of all tools to pending if it is finished or error
    foreach ($detection_tools as $toolname=>$tool) {
        if (isset($assignment->$toolname)) {
            $tool_record = $DB->get_record('programming_'.$toolname, array('settingid'=>$assignment->id));
            if ($tool_record && ($tool_record->status=='finished' || $tool_record->status=='error')) {
                $tool_record->status = 'pending';
                $DB->update_record('programming_'.$toolname,$tool_record);
            }
        }
    }

    create_temporary_dir();
    scan_assignment($assignment);
}

function check_status($assignment,$time) {
    global $DB, $detection_tools;
    
    $status = array();
    if ($time==$assignment->starttime) {
        // this means that the scanning hasn't been started by the other request yet
        // (this request come faster than the other)
        foreach ($detection_tools as $tool_name=>$tool_info) {
            if ($assignment->$tool_name)
            $status[$tool_name] = array('stage'=>'...','progress'=>0);
        }
        echo json_encode($status);
        return;
    }
    
    // the scanning has been initiated
    foreach ($detection_tools as $tool_name=>$tool_info) {
        if (!$assignment->$tool_name)
            continue;
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=NULL);

        $tool_class_name = $tool_info['class_name'];
        $tool_class = new $tool_class_name();
        $scan_info = check_scanning_status($assignment, $tool_class, $scan_info);
        $status[$tool_name] = array('stage'=>$scan_info->status,'progress'=>$scan_info->progress);
        if ($scan_info->status=='finished') { // send back the link
            $status[$tool_name]['link'] = $tool_class->display_link($assignment);
        }
    }
    echo json_encode($status);
}

function download_assignment($assignment) {
    global $DB, $detection_tools;
    $status = array();
    foreach ($detection_tools as $tool_name=>$tool_info) {
        if (!$assignment->$tool_name)
            continue;
        $scan_info = $DB->get_record('programming_'.$tool_name, array('settingid'=>$assignment->id));
        assert($scan_info!=NULL);

        if ($scan_info->status=='done') {
            $tool_class_name = $tool_info['class_name'];
            $tool_class = new $tool_class_name();
            download_result($assignment, $tool_class, $scan_info);
        }
    }
}
